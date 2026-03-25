<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
require_once '../config/database.php';

$message = '';
$messageType = '';

// CREATE Report
if(isset($_POST['create_report'])) {
    try {
        $report_id = 'RPT-' . strtoupper(substr(uniqid(), -3));
        $title = $_POST['title'];
        $classification = $_POST['classification'];
        $category = $_POST['category'];
        $author = 'Agent ' . strtoupper($_SESSION['username']);
        $content = $_POST['content'];
        
        $stmt = $conn->prepare("INSERT INTO reports (report_id, title, classification, category, author, author_id, content) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$report_id, $title, $classification, $category, $author, $_SESSION['user_id'], $content]);
        
        $message = "Report created successfully: $report_id";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error creating report: " . $e->getMessage();
        $messageType = 'error';
    }
}

// UPDATE Report
if(isset($_POST['update_report'])) {
    try {
        $id = $_POST['report_id'];
        $title = $_POST['title'];
        $classification = $_POST['classification'];
        $category = $_POST['category'];
        $content = $_POST['content'];
        
        $stmt = $conn->prepare("UPDATE reports SET title=?, classification=?, category=?, content=? WHERE id=?");
        $stmt->execute([$title, $classification, $category, $content, $id]);
        
        $message = "Report updated successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error updating report: " . $e->getMessage();
        $messageType = 'error';
    }
}

// DELETE Report
if(isset($_POST['delete_report'])) {
    try {
        $id = $_POST['report_id'];
        $stmt = $conn->prepare("DELETE FROM reports WHERE id=?");
        $stmt->execute([$id]);
        
        $message = "Report deleted successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error deleting report: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get Filter & Search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : null;
$edit = isset($_GET['edit']) ? true : false;

// Fetch Reports
$query = "SELECT r.*, u.username as creator_username FROM reports r LEFT JOIN users u ON r.author_id = u.id WHERE 1=1";
$params = [];

if($filter !== 'all') {
    $query .= " AND r.category = ?";
    $params[] = $filter;
}

if(!empty($search)) {
    $query .= " AND (r.title LIKE ? OR r.report_id LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get Single Report
$selectedReport = null;
if($view) {
    $stmt = $conn->prepare("SELECT r.*, u.username as creator_username FROM reports r LEFT JOIN users u ON r.author_id = u.id WHERE r.id = ?");
    $stmt->execute([$view]);
    $selectedReport = $stmt->fetch();
}

// Get Stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN classification = 'top-secret' THEN 1 ELSE 0 END) as top_secret,
    SUM(CASE WHEN classification = 'secret' THEN 1 ELSE 0 END) as secret,
    SUM(CASE WHEN classification = 'confidential' THEN 1 ELSE 0 END) as confidential
    FROM reports");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Intelligence Reports</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(10px);
            z-index: 1000; display: none; align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            width: 700px; max-width: 90%; max-height: 85vh;
            background: var(--bg-panel); border: 1px solid var(--border-highlight);
            border-radius: 8px; overflow: hidden;
        }
        .modal-header {
            padding: 20px; border-bottom: 1px solid var(--border-subtle);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto; }
        .modal-footer {
            padding: 20px; border-top: 1px solid var(--border-subtle);
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block; font-size: 0.75rem; margin-bottom: 8px;
            color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px; background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-subtle); color: var(--text-primary);
            font-family: var(--font-mono); font-size: 0.85rem; border-radius: 4px; outline: none;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--text-primary);
        }
        .form-group textarea { resize: vertical; min-height: 200px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .doc-preview {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.95); backdrop-filter: blur(10px);
            z-index: 1000; display: none; align-items: center; justify-content: center;
        }
        .doc-preview.active { display: flex; }
        .doc-container {
            width: 800px; max-width: 90%; max-height: 80vh;
            background: var(--bg-panel); border: 1px solid var(--border-highlight);
            border-radius: 8px; overflow: hidden; position: relative;
        }
        .doc-header {
            padding: 20px; border-bottom: 1px solid var(--border-subtle);
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(0, 0, 0, 0.3);
        }
        .doc-body {
            padding: 30px; max-height: 60vh; overflow-y: auto;
            font-family: var(--font-mono); font-size: 0.85rem; line-height: 1.8;
        }
        .doc-watermark {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 4rem; color: rgba(255, 59, 59, 0.1);
            font-weight: 900; pointer-events: none; white-space: nowrap;
        }
        .doc-close {
            background: none; border: none; color: var(--text-secondary);
            cursor: pointer; font-size: 1.5rem;
        }
        .doc-close:hover { color: var(--text-primary); }
        
        .btn-secondary {
            padding: 10px 20px; background: transparent;
            border: 1px solid var(--border-highlight); color: var(--text-primary);
            cursor: pointer; border-radius: 4px; text-transform: uppercase;
            font-size: 0.75rem; letter-spacing: 1px;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); }
        
        .alert {
            padding: 15px 20px; border-radius: 4px; margin-bottom: 20px;
            font-size: 0.85rem; font-family: var(--font-mono);
        }
        .alert-success {
            background: rgba(0, 255, 157, 0.1);
            border: 1px solid var(--neon-green); color: var(--neon-green);
        }
        .alert-error {
            background: rgba(255, 59, 59, 0.1);
            border: 1px solid var(--neon-red); color: var(--neon-red);
        }
        
        .btn-icon {
            padding: 8px 12px; background: transparent;
            border: 1px solid var(--border-subtle); color: var(--text-secondary);
            cursor: pointer; border-radius: 4px; display: flex;
            align-items: center; gap: 5px; font-size: 0.75rem; text-transform: uppercase;
        }
        .btn-icon:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
        .btn-icon.danger:hover {
            background: rgba(255, 59, 59, 0.2);
            border-color: var(--neon-red); color: var(--neon-red);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="content-body fade-in">
                
                <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                    <div>
                        <h2 style="font-size:1.5rem;">Intelligence Reports</h2>
                        <p class="text-mono">CLASSIFIED DOCUMENT ARCHIVE // ACCESS LOGGED</p>
                    </div>
                    <button class="btn-system" style="width:auto; padding: 10px 20px;" onclick="openModal('create')">
                        <i data-lucide="upload" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i> Upload Report
                    </button>
                </div>

                <div class="filter-bar">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="searchInput" placeholder="Search reports..." value="<?php echo htmlspecialchars($search); ?>" onkeypress="if(event.key==='Enter') searchReports()">
                    </div>
                    <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="filterReports('all')">All</button>
                    <button class="filter-btn <?php echo $filter === 'surveillance' ? 'active' : ''; ?>" onclick="filterReports('surveillance')">Surveillance</button>
                    <button class="filter-btn <?php echo $filter === 'financial' ? 'active' : ''; ?>" onclick="filterReports('financial')">Financial</button>
                    <button class="filter-btn <?php echo $filter === 'cyber' ? 'active' : ''; ?>" onclick="filterReports('cyber')">Cyber</button>
                    <button class="filter-btn <?php echo $filter === 'field' ? 'active' : ''; ?>" onclick="filterReports('field')">Field</button>
                    <button class="filter-btn <?php echo $filter === 'personnel' ? 'active' : ''; ?>" onclick="filterReports('personnel')">Personnel</button>
                </div>
                
                <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:25px;">
                    <div class="stat-item"><div class="stat-value"><?php echo $stats['total']; ?></div><div class="stat-label">Total Reports</div></div>
                    <div class="stat-item"><div class="stat-value" style="color: var(--neon-red);"><?php echo $stats['top_secret']; ?></div><div class="stat-label">Top Secret</div></div>
                    <div class="stat-item"><div class="stat-value" style="color: orange;"><?php echo $stats['secret']; ?></div><div class="stat-label">Secret</div></div>
                    <div class="stat-item"><div class="stat-value" style="color: var(--neon-green);"><?php echo $stats['confidential']; ?></div><div class="stat-label">Confidential</div></div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th width="12%">Report ID</th>
                                <th width="30%">Title</th>
                                <th width="15%">Classification</th>
                                <th width="12%">Category</th>
                                <th width="13%">Date</th>
                                <th width="10%">Author</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reports)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:60px; color:var(--text-secondary);">
                                    <i data-lucide="folder-open" style="width:60px; height:60px; margin:0 auto 20px; opacity:0.3;"></i>
                                    <p>No reports found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($reports as $report): ?>
                            <tr onclick="viewReport(<?php echo $report['id']; ?>)">
                                <td><span class="text-mono"><?php echo htmlspecialchars($report['report_id']); ?></span></td>
                                <td><?php echo htmlspecialchars($report['title']); ?></td>
                                <td>
                                    <span class="classification-badge class-<?php echo $report['classification']; ?>">
                                        <?php echo strtoupper(str_replace('-', ' ', $report['classification'])); ?>
                                    </span>
                                </td>
                                <td class="text-mono"><?php echo ucfirst($report['category']); ?></td>
                                <td class="text-mono"><?php echo date('Y-m-d', strtotime($report['created_at'])); ?></td>
                                <td class="text-mono"><?php echo htmlspecialchars($report['author']); ?></td>
                                <td>
                                    <button class="btn-small" onclick="event.stopPropagation(); viewReport(<?php echo $report['id']; ?>)">
                                        <i data-lucide="eye" style="width:14px; display:inline; vertical-align:middle;"></i>
                                    </button>
                                    <button class="btn-small" onclick="event.stopPropagation(); openModal('edit', <?php echo $report['id']; ?>)">
                                        <i data-lucide="edit-2" style="width:14px; display:inline; vertical-align:middle;"></i>
                                    </button>
                                    <button class="btn-small" onclick="event.stopPropagation(); deleteReport(<?php echo $report['id']; ?>)">
                                        <i data-lucide="trash-2" style="width:14px; display:inline; vertical-align:middle;"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" style="font-size:1.1rem;">Upload Report</h3>
                <button class="doc-close" onclick="closeModal()"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="reports.php">
                <div class="modal-body">
                    <input type="hidden" name="report_id" id="report_db_id">
                    <input type="hidden" name="create_report" id="create_report" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Report Title *</label>
                            <input type="text" name="title" id="title" required placeholder="e.g., Surveillance Log: Sector 7">
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="category" required>
                                <option value="surveillance">Surveillance</option>
                                <option value="financial">Financial</option>
                                <option value="cyber">Cyber</option>
                                <option value="field">Field</option>
                                <option value="personnel">Personnel</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Classification *</label>
                        <select name="classification" id="classification" required>
                            <option value="unclassified">Unclassified</option>
                            <option value="confidential">Confidential</option>
                            <option value="secret">Secret</option>
                            <option value="top-secret">Top Secret</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Content *</label>
                        <textarea name="content" id="content" required placeholder="Enter classified content..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;" id="submitBtn">Upload Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Document Preview -->
    <div class="doc-preview" id="docPreview">
        <div class="doc-container">
            <div class="doc-header">
                <div>
                    <h3 id="previewTitle" style="font-size: 1.1rem;">Document Title</h3>
                    <span class="text-mono" id="previewId">RPT-XXX</span>
                </div>
                <button class="doc-close" onclick="closePreview()"><i data-lucide="x"></i></button>
            </div>
            <div class="doc-body" id="previewContent"></div>
            <div style="padding: 15px 30px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                <span class="text-mono" id="previewMeta"></span>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-small" onclick="closePreview()">Close</button>
                    <button class="btn-small" style="border-color: var(--neon-green); color: var(--neon-green);">
                        <i data-lucide="download" style="width:14px; display:inline; vertical-align:middle;"></i> Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header">
                <h3 style="font-size:1.1rem; color:var(--neon-red);">Confirm Delete</h3>
                <button class="doc-close" onclick="closeDeleteModal()"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="reports.php">
                <div class="modal-body">
                    <input type="hidden" name="report_id" id="delete_report_id">
                    <input type="hidden" name="delete_report" value="1">
                    <p style="color:var(--text-secondary); margin-bottom:20px;">
                        Are you sure you want to delete this report? This action cannot be undone.
                    </p>
                    <div class="alert alert-error">
                        <i data-lucide="alert-triangle" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i>
                        WARNING: All data will be permanently deleted
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto; background:var(--neon-red); color:#fff;">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function filterReports(filter) { window.location.href = 'reports.php?filter=' + filter; }
        function searchReports() {
            const search = document.getElementById('searchInput').value;
            window.location.href = 'reports.php?search=' + encodeURIComponent(search);
        }
        function viewReport(id) { window.location.href = 'reports.php?view=' + id; }
        
        function openModal(type, id = null) {
            const modal = document.getElementById('reportModal');
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const createField = document.getElementById('create_report');
            
            if(type === 'edit' && id) {
                window.location.href = 'reports.php?view=' + id + '&edit=1';
            } else {
                modalTitle.textContent = 'Upload Report';
                submitBtn.textContent = 'Upload Report';
                createField.value = '1';
                document.getElementById('report_db_id').value = '';
                document.getElementById('title').value = '';
                document.getElementById('category').value = 'surveillance';
                document.getElementById('classification').value = 'confidential';
                document.getElementById('content').value = '';
            }
            modal.classList.add('active');
            lucide.createIcons();
        }
        
        function closeModal() { document.getElementById('reportModal').classList.remove('active'); }
        
        function closePreview() { document.getElementById('docPreview').classList.remove('active'); }
        
        function openDeleteModal(id) {
            document.getElementById('delete_report_id').value = id;
            document.getElementById('deleteModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
        
        function deleteReport(id) {
            if(confirm('Are you sure you want to delete this report?')) {
                const form = document.createElement('form');
                form.method = 'POST'; form.action = 'reports.php';
                const opId = document.createElement('input');
                opId.type = 'hidden'; opId.name = 'report_id'; opId.value = id;
                const deleteOp = document.createElement('input');
                deleteOp.type = 'hidden'; deleteOp.name = 'delete_report'; deleteOp.value = '1';
                form.appendChild(opId); form.appendChild(deleteOp);
                document.body.appendChild(form); form.submit();
            }
        }
        
        document.querySelectorAll('.modal, .doc-preview').forEach(modal => {
            modal.addEventListener('click', function(e) { if(e.target === this) this.classList.remove('active'); });
        });
        
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                document.querySelectorAll('.modal, .doc-preview').forEach(modal => modal.classList.remove('active'));
            }
        });
        
        <?php if($selectedReport): ?>
        document.getElementById('previewTitle').textContent = '<?php echo addslashes($selectedReport['title']); ?>';
        document.getElementById('previewId').textContent = '<?php echo $selectedReport['report_id']; ?> // <?php echo strtoupper($selectedReport['classification']); ?>';
        const content = `<?php echo addslashes(nl2br(htmlspecialchars($selectedReport['content']))); ?>`;
        document.getElementById('previewContent').innerHTML = content;
        const watermark = document.createElement('div');
        watermark.className = 'doc-watermark';
        watermark.textContent = '<?php echo strtoupper(str_replace('-', ' ', $selectedReport['classification'])); ?>';
        document.getElementById('previewContent').appendChild(watermark);
        document.getElementById('previewMeta').textContent = 'Author: <?php echo $selectedReport['author']; ?> | Date: <?php echo date('Y-m-d H:i', strtotime($selectedReport['created_at'])); ?>';
        document.getElementById('docPreview').classList.add('active');
        
        <?php if($edit): ?>
        document.getElementById('report_db_id').value = '<?php echo $selectedReport['id']; ?>';
        document.getElementById('title').value = '<?php echo addslashes($selectedReport['title']); ?>';
        document.getElementById('category').value = '<?php echo $selectedReport['category']; ?>';
        document.getElementById('classification').value = '<?php echo $selectedReport['classification']; ?>';
        document.getElementById('content').value = `<?php echo addslashes($selectedReport['content']); ?>`;
        document.getElementById('create_report').value = '';
        document.getElementById('modalTitle').textContent = 'Edit Report';
        document.getElementById('submitBtn').textContent = 'Update Report';
        document.getElementById('reportModal').classList.add('active');
        <?php endif; ?>
        <?php endif; ?>
        
        lucide.createIcons();
    </script>
</body>
</html>