<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
require_once '../config/database.php';

// Handle Form Submissions
$message = '';
$messageType = '';

// CREATE Operation
if(isset($_POST['create_operation'])) {
    try {
        $code = 'OP-' . strtoupper(substr(uniqid(), -3));
        $title = $_POST['title'];
        $location = $_POST['location'];
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $progress = $_POST['progress'];
        $team = $_POST['team'];
        $target = $_POST['target'];
        $deadline = $_POST['deadline'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO operations (code, title, location, status, priority, progress, team, target, deadline, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $title, $location, $status, $priority, $progress, $team, $target, $deadline, $description, $_SESSION['user_id']]);
        
        $message = "Operation created successfully: $code";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error creating operation: " . $e->getMessage();
        $messageType = 'error';
    }
}

// UPDATE Operation
if(isset($_POST['update_operation'])) {
    try {
        $id = $_POST['op_id'];
        $title = $_POST['title'];
        $location = $_POST['location'];
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $progress = $_POST['progress'];
        $team = $_POST['team'];
        $target = $_POST['target'];
        $deadline = $_POST['deadline'];
        $description = $_POST['description'];
        
        $stmt = $conn->prepare("UPDATE operations SET title=?, location=?, status=?, priority=?, progress=?, team=?, target=?, deadline=?, description=? WHERE id=?");
        $stmt->execute([$title, $location, $status, $priority, $progress, $team, $target, $deadline, $description, $id]);
        
        $message = "Operation updated successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error updating operation: " . $e->getMessage();
        $messageType = 'error';
    }
}

// DELETE Operation
if(isset($_POST['delete_operation'])) {
    try {
        $id = $_POST['op_id'];
        $stmt = $conn->prepare("DELETE FROM operations WHERE id=?");
        $stmt->execute([$id]);
        
        $message = "Operation deleted successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error deleting operation: " . $e->getMessage();
        $messageType = 'error';
    }
}

// UPDATE Progress
if(isset($_POST['update_progress'])) {
    try {
        $id = $_POST['op_id'];
        $progress = $_POST['progress'];
        
        $stmt = $conn->prepare("UPDATE operations SET progress=? WHERE id=?");
        $stmt->execute([$progress, $id]);
        
        $message = "Progress updated to {$progress}%";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error updating progress: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get Filter & Search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch Operations
$query = "SELECT * FROM operations WHERE 1=1";
$params = [];

if($filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter;
}

if(!empty($search)) {
    $query .= " AND (title LIKE ? OR code LIKE ? OR location LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// Get Single Operation for Edit/View
$selectedOp = null;
if(isset($_GET['view'])) {
    $stmt = $conn->prepare("SELECT * FROM operations WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $selectedOp = $stmt->fetch();
}

// Get Stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    AVG(progress) as avg_progress
    FROM operations");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Operations</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            width: 600px;
            max-width: 90%;
            max-height: 85vh;
            background: var(--bg-panel);
            border: 1px solid var(--border-highlight);
            border-radius: 8px;
            overflow: hidden;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 25px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            margin-bottom: 8px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
            font-family: var(--font-mono);
            font-size: 0.85rem;
            border-radius: 4px;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--text-primary);
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
        .btn-secondary {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border-highlight);
            color: var(--text-primary);
            cursor: pointer;
            border-radius: 4px;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); }
        
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-family: var(--font-mono);
        }
        .alert-success {
            background: rgba(0, 255, 157, 0.1);
            border: 1px solid var(--neon-green);
            color: var(--neon-green);
        }
        .alert-error {
            background: rgba(255, 59, 59, 0.1);
            border: 1px solid var(--neon-red);
            color: var(--neon-red);
        }
        
        .progress-slider {
            width: 100%;
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            background: #222;
            border-radius: 3px;
            outline: none;
        }
        .progress-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: var(--text-primary);
            border-radius: 50%;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-icon {
            padding: 8px 12px;
            background: transparent;
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .btn-icon:hover {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
        }
        .btn-icon.danger:hover {
            background: rgba(255, 59, 59, 0.2);
            border-color: var(--neon-red);
            color: var(--neon-red);
        }
        .btn-icon.success:hover {
            background: rgba(0, 255, 157, 0.2);
            border-color: var(--neon-green);
            color: var(--neon-green);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="content-body fade-in">
                
                <!-- Alert Messages -->
                <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Page Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                    <div>
                        <h2 style="font-size:1.5rem;">Active Operations</h2>
                        <p class="text-mono">REAL-TIME MISSION TRACKING // AUTHORIZED PERSONNEL ONLY</p>
                    </div>
                    <button class="btn-system" style="width:auto; padding: 10px 20px;" onclick="openModal('create')">
                        <i data-lucide="plus" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i> New Op
                    </button>
                </div>

                <!-- Stats Row -->
                <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom:30px;">
                    <div class="stat-item">
                        <div class="stat-value" style="color:#fff"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Ops</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:var(--neon-green)"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:var(--neon-red)"><?php echo $stats['critical']; ?></div>
                        <div class="stat-label">Critical</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:var(--text-secondary)"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo round($stats['avg_progress']); ?>%</div>
                        <div class="stat-label">Avg Progress</div>
                    </div>
                </div>

                <!-- Filter & Search -->
                <div class="filter-bar">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="searchInput" placeholder="Search operations..." value="<?php echo htmlspecialchars($search); ?>" onkeypress="if(event.key==='Enter') searchOps()">
                    </div>
                    <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="filterOps('all')">All</button>
                    <button class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>" onclick="filterOps('active')">Active</button>
                    <button class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="filterOps('pending')">Pending</button>
                    <button class="filter-btn <?php echo $filter === 'critical' ? 'active' : ''; ?>" onclick="filterOps('critical')">Critical</button>
                    <button class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>" onclick="filterOps('completed')">Completed</button>
                </div>

                <div class="dashboard-grid">
                    <!-- Operations List -->
                    <div style="grid-column: span 2;">
                        <div class="ops-grid">
                            <?php if(empty($operations)): ?>
                            <div style="grid-column: span 3; text-align:center; padding:60px; color:var(--text-secondary);">
                                <i data-lucide="folder-open" style="width:60px; height:60px; margin:0 auto 20px; opacity:0.3;"></i>
                                <p>No operations found</p>
                                <p class="text-mono">Create a new operation to get started</p>
                            </div>
                            <?php else: ?>
                            <?php foreach($operations as $op): ?>
                            <div class="ops-card priority-<?php echo $op['priority']; ?>" onclick="viewOperation(<?php echo $op['id']; ?>)">
                                <div class="ops-header">
                                    <span class="ops-code"><?php echo htmlspecialchars($op['code']); ?></span>
                                    <?php
                                    $badgeClass = 'badge-pending';
                                    $badgeText = $op['status'];
                                    $showPulse = false;
                                    if($op['status'] == 'active') { $badgeClass = 'badge-active'; $showPulse = true; }
                                    elseif($op['status'] == 'critical') { $badgeClass = 'badge-critical'; $showPulse = true; }
                                    elseif($op['status'] == 'completed') { $badgeClass = 'badge-completed'; }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php if($showPulse): ?><span class="pulse-dot"></span><?php endif; ?>
                                        <?php echo strtoupper($badgeText); ?>
                                    </span>
                                </div>
                                <div class="ops-title"><?php echo htmlspecialchars($op['title']); ?></div>
                                <div class="ops-location">
                                    <i data-lucide="map-pin" style="width:12px;"></i> <?php echo htmlspecialchars($op['location']); ?>
                                </div>
                                
                                <div class="ops-progress">
                                    <div class="progress-label">
                                        <span>PROGRESS</span>
                                        <span><?php echo $op['progress']; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $op['progress']; ?>%; background: <?php echo $op['status']=='critical'?'var(--neon-red)':'var(--text-primary)'; ?>"></div>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick="event.stopPropagation(); openModal('edit', <?php echo $op['id']; ?>)">
                                        <i data-lucide="edit-2" style="width:14px;"></i> Edit
                                    </button>
                                    <button class="btn-icon success" onclick="event.stopPropagation(); openProgressModal(<?php echo $op['id']; ?>, <?php echo $op['progress']; ?>)">
                                        <i data-lucide="trending-up" style="width:14px;"></i> Update
                                    </button>
                                    <button class="btn-icon danger" onclick="event.stopPropagation(); deleteOperation(<?php echo $op['id']; ?>)">
                                        <i data-lucide="trash-2" style="width:14px;"></i> Delete
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Detail Panel -->
                    <div class="detail-panel" id="detail-panel">
                        <div class="card-header">
                            <span class="card-title">MISSION DETAILS</span>
                            <i data-lucide="file-text" style="width:16px;"></i>
                        </div>
                        
                        <div id="op-details-content">
                            <?php if($selectedOp): ?>
                            <div style="margin-bottom:20px;">
                                <h3 style="font-size:1.2rem; margin-bottom:5px;"><?php echo htmlspecialchars($selectedOp['title']); ?></h3>
                                <div class="text-mono" style="color:var(--text-secondary)"><?php echo htmlspecialchars($selectedOp['code']); ?> // <?php echo htmlspecialchars($selectedOp['location']); ?></div>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">STATUS</span>
                                <span class="detail-value <?php echo $selectedOp['status']=='active'?'text-accent':($selectedOp['status']=='critical'?'text-danger':''); ?>"><?php echo strtoupper($selectedOp['status']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PRIORITY</span>
                                <span class="detail-value" style="color:<?php echo $selectedOp['priority']=='high'?'var(--neon-red)':'#fff'; ?>"><?php echo strtoupper($selectedOp['priority']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">ASSIGNED TEAM</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selectedOp['team']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PRIMARY OBJECTIVE</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selectedOp['target']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">TIME REMAINING</span>
                                <span class="detail-value highlight"><?php echo htmlspecialchars($selectedOp['deadline']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">PROGRESS</span>
                                <span class="detail-value"><?php echo $selectedOp['progress']; ?>%</span>
                            </div>
                            <div class="detail-row" style="display:block;">
                                <span class="detail-label" style="display:block; margin-bottom:10px;">DESCRIPTION</span>
                                <p class="text-mono" style="color:var(--text-secondary); line-height:1.8;"><?php echo nl2br(htmlspecialchars($selectedOp['description'])); ?></p>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">CREATED</span>
                                <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($selectedOp['created_at'])); ?></span>
                            </div>

                            <button class="btn-action" onclick="openProgressModal(<?php echo $selectedOp['id']; ?>, <?php echo $selectedOp['progress']; ?>)">Update Progress</button>
                            <button class="btn-action" onclick="openModal('edit', <?php echo $selectedOp['id']; ?>)">Edit Operation</button>
                            <button class="btn-action danger" onclick="deleteOperation(<?php echo $selectedOp['id']; ?>)">Abort Mission</button>
                            <?php else: ?>
                            <div style="text-align:center; padding:40px 0; color:var(--text-secondary);">
                                <i data-lucide="shield-alert" style="width:40px; height:40px; margin-bottom:10px; opacity:0.5;"></i>
                                <p class="text-mono">SELECT AN OPERATION TO VIEW CLASSIFIED DATA</p>
                                <p class="text-mono" style="margin-top:10px;">OR CREATE A NEW OPERATION</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal" id="operationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" style="font-size:1.1rem;">Create Operation</h3>
                <button class="doc-close" onclick="closeModal()"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="operations.php">
                <div class="modal-body">
                    <input type="hidden" name="op_id" id="op_id">
                    <input type="hidden" name="create_operation" id="create_operation" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Operation Title *</label>
                            <input type="text" name="title" id="title" required placeholder="e.g., Operation Black Echo">
                        </div>
                        <div class="form-group">
                            <label>Location *</label>
                            <input type="text" name="location" id="location" required placeholder="e.g., Berlin, Germany">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" id="status" required>
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="critical">Critical</option>
                                <option value="completed">Completed</option>
                                <option value="aborted">Aborted</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority *</label>
                            <select name="priority" id="priority" required>
                                <option value="low">Low</option>
                                <option value="med">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Progress (%) *</label>
                            <input type="number" name="progress" id="progress" required min="0" max="100" value="0">
                        </div>
                        <div class="form-group">
                            <label>Deadline *</label>
                            <input type="text" name="deadline" id="deadline" required placeholder="e.g., 24 HRS">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Assigned Team *</label>
                            <input type="text" name="team" id="team" required placeholder="e.g., Alpha Squad">
                        </div>
                        <div class="form-group">
                            <label>Primary Target *</label>
                            <input type="text" name="target" id="target" required placeholder="e.g., Asset Retrieval">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" placeholder="Operation details and objectives..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;" id="submitBtn">Create Operation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Progress Update Modal -->
    <div class="modal" id="progressModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header">
                <h3 style="font-size:1.1rem;">Update Progress</h3>
                <button class="doc-close" onclick="closeProgressModal()"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="operations.php">
                <div class="modal-body">
                    <input type="hidden" name="op_id" id="progress_op_id">
                    <input type="hidden" name="update_progress" value="1">
                    
                    <div class="form-group">
                        <label>Progress: <span id="progressValue" style="color:var(--neon-green)">0%</span></label>
                        <input type="range" name="progress" id="progressSlider" class="progress-slider" min="0" max="100" value="0" oninput="document.getElementById('progressValue').textContent=this.value+'%'">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeProgressModal()">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header">
                <h3 style="font-size:1.1rem; color:var(--neon-red);">Confirm Abort</h3>
                <button class="doc-close" onclick="closeDeleteModal()"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="operations.php">
                <div class="modal-body">
                    <input type="hidden" name="op_id" id="delete_op_id">
                    <input type="hidden" name="delete_operation" value="1">
                    
                    <p style="color:var(--text-secondary); margin-bottom:20px;">
                        Are you sure you want to abort this operation? This action cannot be undone.
                    </p>
                    <div class="alert alert-error">
                        <i data-lucide="alert-triangle" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i>
                        WARNING: All mission data will be permanently deleted
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto; background:var(--neon-red); color:#fff;">Abort Mission</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Filter Operations
        function filterOps(filter) {
            window.location.href = 'operations.php?filter=' + filter;
        }
        
        // Search Operations
        function searchOps() {
            const search = document.getElementById('searchInput').value;
            window.location.href = 'operations.php?search=' + encodeURIComponent(search);
        }
        
        // View Operation Details
        function viewOperation(id) {
            window.location.href = 'operations.php?view=' + id;
        }
        
        // Modal Functions
        function openModal(type, id = null) {
            const modal = document.getElementById('operationModal');
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const createField = document.getElementById('create_operation');
            
            if(type === 'edit' && id) {
                modalTitle.textContent = 'Edit Operation';
                submitBtn.textContent = 'Update Operation';
                createField.value = '';
                
                // Fetch operation data
                fetch('operations.php?view=' + id)
                    .then(response => response.text())
                    .then(html => {
                        // Parse the HTML to extract operation data
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // For simplicity, we'll use a data attribute approach
                        // In production, create an API endpoint
                        window.location.href = 'operations.php?view=' + id + '&edit=1';
                    });
            } else {
                modalTitle.textContent = 'Create Operation';
                submitBtn.textContent = 'Create Operation';
                createField.value = '1';
                
                // Reset form
                document.getElementById('op_id').value = '';
                document.getElementById('title').value = '';
                document.getElementById('location').value = '';
                document.getElementById('status').value = 'pending';
                document.getElementById('priority').value = 'med';
                document.getElementById('progress').value = '0';
                document.getElementById('deadline').value = '';
                document.getElementById('team').value = '';
                document.getElementById('target').value = '';
                document.getElementById('description').value = '';
            }
            
            modal.classList.add('active');
            lucide.createIcons();
        }
        
        function closeModal() {
            document.getElementById('operationModal').classList.remove('active');
        }
        
        function openProgressModal(id, currentProgress) {
            document.getElementById('progress_op_id').value = id;
            document.getElementById('progressSlider').value = currentProgress;
            document.getElementById('progressValue').textContent = currentProgress + '%';
            document.getElementById('progressModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeProgressModal() {
            document.getElementById('progressModal').classList.remove('active');
        }
        
        function openDeleteModal(id) {
            document.getElementById('delete_op_id').value = id;
            document.getElementById('deleteModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function deleteOperation(id) {
            if(confirm('Are you sure you want to abort this operation? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'operations.php';
                
                const opId = document.createElement('input');
                opId.type = 'hidden';
                opId.name = 'op_id';
                opId.value = id;
                
                const deleteOp = document.createElement('input');
                deleteOp.type = 'hidden';
                deleteOp.name = 'delete_operation';
                deleteOp.value = '1';
                
                form.appendChild(opId);
                form.appendChild(deleteOp);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if(e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
        
        // Pre-fill edit form if edit mode
        <?php if(isset($_GET['edit']) && $selectedOp): ?>
        document.getElementById('op_id').value = '<?php echo $selectedOp['id']; ?>';
        document.getElementById('title').value = '<?php echo htmlspecialchars($selectedOp['title']); ?>';
        document.getElementById('location').value = '<?php echo htmlspecialchars($selectedOp['location']); ?>';
        document.getElementById('status').value = '<?php echo $selectedOp['status']; ?>';
        document.getElementById('priority').value = '<?php echo $selectedOp['priority']; ?>';
        document.getElementById('progress').value = '<?php echo $selectedOp['progress']; ?>';
        document.getElementById('deadline').value = '<?php echo htmlspecialchars($selectedOp['deadline']); ?>';
        document.getElementById('team').value = '<?php echo htmlspecialchars($selectedOp['team']); ?>';
        document.getElementById('target').value = '<?php echo htmlspecialchars($selectedOp['target']); ?>';
        document.getElementById('description').value = '<?php echo htmlspecialchars($selectedOp['description']); ?>';
        document.getElementById('create_operation').value = '';
        document.getElementById('modalTitle').textContent = 'Edit Operation';
        document.getElementById('submitBtn').textContent = 'Update Operation';
        document.getElementById('operationModal').classList.add('active');
        <?php endif; ?>
        
        lucide.createIcons();
    </script>
</body>
</html>