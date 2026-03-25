<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
require_once '../config/database.php';

$message = '';
$messageType = '';

// LOG ACTIVITY
function logActivity($conn, $user_id, $agent_id, $action, $description) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, agent_id, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->execute([$user_id, $agent_id, $action, $description, $ip]);
    } catch(PDOException $e) {}
}

// CREATE Agent
if(isset($_POST['create_agent'])) {
    try {
        $codename = strtoupper($_POST['codename']);
        $rank = $_POST['rank'];
        $status = $_POST['status'];
        $specialization = $_POST['specialization'];
        $skills = $_POST['skills'];
        $joined_date = $_POST['joined_date'];
        
        // Create user account for agent
        $username = strtolower(str_replace(' ', '', $codename)) . '_' . substr(uniqid(), -3);
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $email = strtolower($codename) . '@nexa.agency';
        
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $email]);
        $user_id = $conn->lastInsertId();
        
        $stmt = $conn->prepare("INSERT INTO agents (user_id, codename, rank, status, specialization, skills, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $codename, $rank, $status, $specialization, $skills, $joined_date]);
        $agent_id = $conn->lastInsertId();
        
        $conn->commit();
        
        logActivity($conn, $_SESSION['user_id'], $agent_id, 'AGENT_CREATED', "Created new agent: $codename");
        
        $message = "Agent created successfully: $codename (Username: $username)";
        $messageType = 'success';
    } catch(PDOException $e) {
        $conn->rollBack();
        $message = "Error creating agent: " . $e->getMessage();
        $messageType = 'error';
    }
}

// UPDATE Agent Status
if(isset($_POST['update_status'])) {
    try {
        $id = $_POST['agent_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE agents SET status = ?, last_active = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Get agent codename for log
        $stmt = $conn->prepare("SELECT codename FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        $agent = $stmt->fetch();
        
        logActivity($conn, $_SESSION['user_id'], $id, 'STATUS_UPDATE', "Updated status to: $status");
        
        $message = "Agent status updated to: " . ucfirst($status);
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error updating status: " . $e->getMessage();
        $messageType = 'error';
    }
}

// UPDATE Agent Info
if(isset($_POST['update_agent'])) {
    try {
        $id = $_POST['agent_id'];
        $rank = $_POST['rank'];
        $specialization = $_POST['specialization'];
        $skills = $_POST['skills'];
        
        $stmt = $conn->prepare("UPDATE agents SET rank = ?, specialization = ?, skills = ?, last_active = NOW() WHERE id = ?");
        $stmt->execute([$rank, $specialization, $skills, $id]);
        
        logActivity($conn, $_SESSION['user_id'], $id, 'PROFILE_UPDATE', "Updated agent profile");
        
        $message = "Agent profile updated successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error updating profile: " . $e->getMessage();
        $messageType = 'error';
    }
}

// DELETE Agent
if(isset($_POST['delete_agent'])) {
    try {
        $id = $_POST['agent_id'];
        
        // Get agent info for log
        $stmt = $conn->prepare("SELECT codename, user_id FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        $agent = $stmt->fetch();
        
        $conn->beginTransaction();
        
        // Delete assignments
        $stmt = $conn->prepare("DELETE FROM agent_assignments WHERE agent_id = ?");
        $stmt->execute([$id]);
        
        // Delete agent
        $stmt = $conn->prepare("DELETE FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$agent['user_id']]);
        
        $conn->commit();
        
        logActivity($conn, $_SESSION['user_id'], $id, 'AGENT_DELETED', "Deleted agent: " . $agent['codename']);
        
        $message = "Agent terminated successfully";
        $messageType = 'success';
        $selectedAgent = null;
    } catch(PDOException $e) {
        $conn->rollBack();
        $message = "Error terminating agent: " . $e->getMessage();
        $messageType = 'error';
    }
}

// ASSIGN to Operation
if(isset($_POST['assign_operation'])) {
    try {
        $agent_id = $_POST['agent_id'];
        $operation_id = $_POST['operation_id'];
        $role = $_POST['role'];
        
        $stmt = $conn->prepare("INSERT INTO agent_assignments (agent_id, operation_id, role) VALUES (?, ?, ?)");
        $stmt->execute([$agent_id, $operation_id, $role]);
        
        logActivity($conn, $_SESSION['user_id'], $agent_id, 'MISSION_ASSIGN', "Assigned to operation ID: $operation_id");
        
        $message = "Agent assigned to operation successfully";
        $messageType = 'success';
    } catch(PDOException $e) {
        $message = "Error assigning agent: " . $e->getMessage();
        $messageType = 'error';
    }
}

// EXPORT Data
if(isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agents_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Codename', 'Rank', 'Status', 'Specialization', 'Missions', 'Success Rate', 'Joined Date']);
    
    foreach($agents as $agent) {
        fputcsv($output, [
            $agent['id'],
            $agent['codename'],
            $agent['rank'],
            $agent['status'],
            $agent['specialization'],
            $agent['missions_completed'],
            $agent['success_rate'],
            $agent['joined_date']
        ]);
    }
    fclose($output);
    exit;
}

// Get Filter & Search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'missions_completed';

// Fetch Agents
$query = "SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id WHERE 1=1";
$params = [];

if($filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $filter;
}

if(!empty($search)) {
    $query .= " AND (a.codename LIKE ? OR a.rank LIKE ? OR a.specialization LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Sort options
$valid_sorts = ['missions_completed', 'success_rate', 'codename', 'joined_date'];
if(in_array($sort, $valid_sorts)) {
    $query .= " ORDER BY a.$sort DESC";
} else {
    $query .= " ORDER BY a.missions_completed DESC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$agents = $stmt->fetchAll();

// Get Single Agent
$selectedAgent = null;
$agentAssignments = [];
$agentLogs = [];
if($view) {
    $stmt = $conn->prepare("SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$view]);
    $selectedAgent = $stmt->fetch();
    
    // Get assignments
    if($selectedAgent) {
        $stmt = $conn->prepare("SELECT aa.*, o.title as operation_title, o.code as operation_code 
            FROM agent_assignments aa 
            JOIN operations o ON aa.operation_id = o.id 
            WHERE aa.agent_id = ? 
            ORDER BY aa.assigned_at DESC");
        $stmt->execute([$view]);
        $agentAssignments = $stmt->fetchAll();
        
        // Get activity logs for this agent
        $stmt = $conn->prepare("SELECT al.*, u.username as actor FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.agent_id = ? 
            ORDER BY al.created_at DESC 
            LIMIT 10");
        $stmt->execute([$view]);
        $agentLogs = $stmt->fetchAll();
    }
}

// Get All Operations (for assignment)
$stmt = $conn->query("SELECT id, code, title, status FROM operations WHERE status IN ('active', 'pending', 'critical')");
$operations = $stmt->fetchAll();

// Get Stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'deployed' THEN 1 ELSE 0 END) as deployed,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status = 'mia' THEN 1 ELSE 0 END) as mia,
    SUM(missions_completed) as total_missions,
    AVG(success_rate) as avg_success,
    MAX(missions_completed) as top_missions
    FROM agents");
$stats = $stmt->fetch();

// Get Top Performers
$stmt = $conn->query("SELECT codename, missions_completed, success_rate FROM agents ORDER BY missions_completed DESC LIMIT 5");
$topPerformers = $stmt->fetchAll();

// Get Recent Activity
$stmt = $conn->query("SELECT al.*, a.codename, u.username as actor 
    FROM activity_logs al 
    LEFT JOIN agents a ON al.agent_id = a.id 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10");
$recentActivity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Personnel</title>
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
            width: 600px; max-width: 90%; max-height: 85vh;
            background: var(--bg-panel); border: 1px solid var(--border-highlight);
            border-radius: 8px; overflow: hidden;
        }
        .modal-content.large { width: 900px; }
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
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-row.three { grid-template-columns: 1fr 1fr 1fr; }
        
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
        
        .agent-detail-panel {
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid var(--border-highlight);
            padding: 25px; border-radius: 8px;
        }
        .detail-row {
            display: flex; justify-content: space-between;
            padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
        }
        .detail-label { color: var(--text-secondary); }
        .detail-value { color: var(--text-primary); font-family: var(--font-mono); text-align: right; }
        
        .skill-tag {
            display: inline-block; padding: 4px 10px;
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-subtle);
            border-radius: 4px; font-size: 0.7rem; color: var(--text-secondary);
            font-family: var(--font-mono); margin: 3px;
        }
        
        /* Activity Log Styles */
        .activity-log {
            list-style: none; margin-top: 20px;
        }
        .activity-log li {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .activity-log li:last-child { border-bottom: none; }
        .activity-icon {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .activity-content { flex: 1; }
        .activity-action { font-size: 0.85rem; color: var(--text-primary); font-weight: 600; }
        .activity-desc { font-size: 0.75rem; color: var(--text-secondary); margin-top: 3px; }
        .activity-time { font-size: 0.65rem; color: var(--text-secondary); font-family: var(--font-mono); }
        
        /* Assignment Table */
        .assignment-table {
            width: 100%; border-collapse: collapse; margin-top: 15px;
        }
        .assignment-table th, .assignment-table td {
            padding: 10px; text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.8rem;
        }
        .assignment-table th {
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 1px;
        }
        
        /* Performance Card */
        .performance-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .performance-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 10px;
        }
        .performance-bar {
            height: 6px; background: #222; border-radius: 3px; overflow: hidden;
        }
        .performance-fill {
            height: 100%; background: linear-gradient(90deg, var(--neon-green), var(--neon-blue));
            border-radius: 3px;
        }
        
        /* Top Performers */
        .top-performers {
            background: rgba(15, 15, 15, 0.6);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .performer-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .performer-item:last-child { border-bottom: none; }
        .performer-rank {
            width: 24px; height: 24px;
            background: var(--text-primary); color: #000;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700;
        }
        .performer-info { flex: 1; }
        .performer-name { font-size: 0.85rem; font-weight: 600; }
        .performer-stats { font-size: 0.7rem; color: var(--text-secondary); }
        
        /* Search & Sort Bar */
        .action-bar {
            display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .sort-select {
            padding: 10px 15px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
            border-radius: 4px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            cursor: pointer;
        }
        
        /* Tabs */
        .tabs {
            display: flex; gap: 5px; margin-bottom: 20px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid transparent;
            transition: 0.3s;
        }
        .tab:hover { color: var(--text-primary); }
        .tab.active {
            color: var(--text-primary);
            border-bottom-color: var(--neon-green);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
                        <h2 style="font-size:1.5rem;">Personnel Database</h2>
                        <p class="text-mono">AUTHORIZED AGENTS // CLEARANCE VERIFIED</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <a href="?export=1" class="btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                            <i data-lucide="download" style="width:16px;"></i> Export
                        </a>
                        <button class="btn-system" style="width:auto; padding: 10px 20px;" onclick="openModal('create')">
                            <i data-lucide="user-plus" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i> Add Agent
                        </button>
                    </div>
                </div>
                
                <div class="stats-grid" style="grid-template-columns: repeat(6, 1fr); margin-bottom:30px;">
                    <div class="stat-item"><div class="stat-value" style="color:#fff"><?php echo $stats['total']; ?></div><div class="stat-label">Total Agents</div></div>
                    <div class="stat-item"><div class="stat-value" style="color:var(--neon-green)"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
                    <div class="stat-item"><div class="stat-value" style="color:var(--neon-blue)"><?php echo $stats['deployed']; ?></div><div class="stat-label">Deployed</div></div>
                    <div class="stat-item"><div class="stat-value" style="color:var(--text-secondary)"><?php echo $stats['inactive']; ?></div><div class="stat-label">Inactive</div></div>
                    <div class="stat-item"><div class="stat-value" style="color:var(--neon-red)"><?php echo $stats['mia']; ?></div><div class="stat-label">MIA</div></div>
                    <div class="stat-item"><div class="stat-value"><?php echo round($stats['avg_success'], 1); ?>%</div><div class="stat-label">Avg Success</div></div>
                </div>
                
                <!-- Top Performers -->
                <div class="top-performers">
                    <div class="card-header" style="border:none; padding:0 0 15px 0;">
                        <span class="card-title">🏆 Top Performers</span>
                        <span class="text-mono">BASED ON MISSIONS COMPLETED</span>
                    </div>
                    <?php foreach($topPerformers as $index => $performer): ?>
                    <div class="performer-item">
                        <div class="performer-rank"><?php echo $index + 1; ?></div>
                        <div class="performer-info">
                            <div class="performer-name"><?php echo htmlspecialchars($performer['codename']); ?></div>
                            <div class="performer-stats"><?php echo $performer['missions_completed']; ?> missions // <?php echo $performer['success_rate']; ?>% success</div>
                        </div>
                        <div class="performance-bar" style="width: 150px;">
                            <div class="performance-fill" style="width: <?php echo $performer['success_rate']; ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="action-bar">
                    <div class="search-box" style="max-width:250px;">
                        <i data-lucide="search"></i>
                        <input type="text" id="searchInput" placeholder="Search agents..." value="<?php echo htmlspecialchars($search); ?>" onkeypress="if(event.key==='Enter') searchAgents()">
                    </div>
                    <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="filterAgents('all')">All</button>
                    <button class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>" onclick="filterAgents('active')">Active</button>
                    <button class="filter-btn <?php echo $filter === 'deployed' ? 'active' : ''; ?>" onclick="filterAgents('deployed')">Deployed</button>
                    <button class="filter-btn <?php echo $filter === 'inactive' ? 'active' : ''; ?>" onclick="filterAgents('inactive')">Inactive</button>
                    
                    <select class="sort-select" onchange="sortAgents(this.value)">
                        <option value="missions_completed" <?php echo $sort === 'missions_completed' ? 'selected' : ''; ?>>Sort by Missions</option>
                        <option value="success_rate" <?php echo $sort === 'success_rate' ? 'selected' : ''; ?>>Sort by Success Rate</option>
                        <option value="codename" <?php echo $sort === 'codename' ? 'selected' : ''; ?>>Sort by Name</option>
                        <option value="joined_date" <?php echo $sort === 'joined_date' ? 'selected' : ''; ?>>Sort by Join Date</option>
                    </select>
                </div>
                
                <div class="dashboard-grid">
                    <div style="grid-column: span 2;">
                        <div class="agents-grid">
                            <?php if(empty($agents)): ?>
                            <div style="grid-column: span 3; text-align:center; padding:60px; color:var(--text-secondary);">
                                <i data-lucide="users" style="width:60px; height:60px; margin:0 auto 20px; opacity:0.3;"></i>
                                <p>No agents found</p>
                            </div>
                            <?php else: ?>
                            <?php foreach($agents as $agent): ?>
                            <div class="agent-card" onclick="viewAgent(<?php echo $agent['id']; ?>)">
                                <div class="agent-avatar"><?php echo strtoupper(substr($agent['codename'], 0, 2)); ?></div>
                                <div class="agent-name"><?php echo htmlspecialchars($agent['codename']); ?></div>
                                <div class="agent-codename"><?php echo htmlspecialchars($agent['rank']); ?> // ID: <?php echo str_pad($agent['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                <span class="badge badge-<?php echo $agent['status']; ?>">
                                    <?php if($agent['status'] == 'active'): ?><span class="pulse-dot"></span><?php endif; ?>
                                    <?php echo ucfirst($agent['status']); ?>
                                </span>
                                <div class="agent-stats">
                                    <div class="agent-stat"><div class="agent-stat-value"><?php echo $agent['missions_completed']; ?></div><div class="agent-stat-label">Missions</div></div>
                                    <div class="agent-stat"><div class="agent-stat-value"><?php echo $agent['success_rate']; ?>%</div><div class="agent-stat-label">Success</div></div>
                                </div>
                                <div class="agent-skills">
                                    <?php 
                                    $skills = explode(',', $agent['skills']);
                                    foreach(array_slice($skills, 0, 3) as $skill): 
                                    ?>
                                    <span class="skill-tag"><?php echo trim($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <button class="btn-action" onclick="event.stopPropagation(); viewAgent(<?php echo $agent['id']; ?>)">View Profile</button>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="agent-detail-panel" id="detail-panel">
                        <?php if($selectedAgent): ?>
                        <div class="tabs">
                            <button class="tab active" onclick="switchTab('profile')">Profile</button>
                            <button class="tab" onclick="switchTab('assignments')">Assignments</button>
                            <button class="tab" onclick="switchTab('activity')">Activity</button>
                        </div>
                        
                        <div id="tab-profile" class="tab-content active">
                            <div style="text-align:center; margin-bottom:20px;">
                                <div class="agent-avatar" style="width:100px; height:100px; font-size:2.5rem; margin:0 auto 15px;">
                                    <?php echo strtoupper(substr($selectedAgent['codename'], 0, 2)); ?>
                                </div>
                                <h3 style="font-size:1.3rem;"><?php echo htmlspecialchars($selectedAgent['codename']); ?></h3>
                                <div class="text-mono"><?php echo htmlspecialchars($selectedAgent['rank']); ?></div>
                                <span class="badge badge-<?php echo $selectedAgent['status']; ?>" style="margin-top:10px;">
                                    <?php echo ucfirst($selectedAgent['status']); ?>
                                </span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">AGENT ID</span>
                                <span class="detail-value"><?php echo str_pad($selectedAgent['id'], 3, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">USERNAME</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selectedAgent['username']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">SPECIALIZATION</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selectedAgent['specialization']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">MISSIONS COMPLETED</span>
                                <span class="detail-value"><?php echo $selectedAgent['missions_completed']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">SUCCESS RATE</span>
                                <span class="detail-value" style="color:var(--neon-green)"><?php echo $selectedAgent['success_rate']; ?>%</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">JOINED DATE</span>
                                <span class="detail-value"><?php echo $selectedAgent['joined_date'] ? date('Y-m-d', strtotime($selectedAgent['joined_date'])) : 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">LAST ACTIVE</span>
                                <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($selectedAgent['last_active'])); ?></span>
                            </div>
                            
                            <div style="margin:20px 0;">
                                <span class="detail-label" style="display:block; margin-bottom:10px;">SKILLS</span>
                                <div>
                                    <?php 
                                    $skills = explode(',', $selectedAgent['skills']);
                                    foreach($skills as $skill): 
                                    ?>
                                    <span class="skill-tag"><?php echo trim($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="performance-card">
                                <div class="performance-header">
                                    <span class="text-mono">PERFORMANCE RATING</span>
                                    <span style="color:var(--neon-green); font-weight:700;"><?php echo $selectedAgent['success_rate']; ?>%</span>
                                </div>
                                <div class="performance-bar">
                                    <div class="performance-fill" style="width: <?php echo $selectedAgent['success_rate']; ?>%;"></div>
                                </div>
                            </div>
                            
                            <form method="POST" action="personnel.php">
                                <input type="hidden" name="agent_id" value="<?php echo $selectedAgent['id']; ?>">
                                <div class="form-group">
                                    <label>Update Status</label>
                                    <select name="status" onchange="this.form.submit()" style="width:100%; padding:10px; background:rgba(0,0,0,0.3); border:1px solid var(--border-subtle); color:#fff; border-radius:4px;">
                                        <option value="active" <?php echo $selectedAgent['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="deployed" <?php echo $selectedAgent['status'] == 'deployed' ? 'selected' : ''; ?>>Deployed</option>
                                        <option value="inactive" <?php echo $selectedAgent['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="mia" <?php echo $selectedAgent['status'] == 'mia' ? 'selected' : ''; ?>>MIA</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </div>
                            </form>
                            
                            <button class="btn-action" onclick="openEditModal(<?php echo $selectedAgent['id']; ?>)">Edit Profile</button>
                            <button class="btn-action" onclick="openAssignModal(<?php echo $selectedAgent['id']; ?>)">Assign to Operation</button>
                            <button class="btn-action" onclick="window.location.href='channel.php?contact=<?php echo $selectedAgent['user_id']; ?>'">Open Channel</button>
                            <button class="btn-action danger" onclick="openDeleteModal(<?php echo $selectedAgent['id']; ?>)">Terminate Agent</button>
                        </div>
                        
                        <div id="tab-assignments" class="tab-content">
                            <h4 style="margin-bottom:15px;">Mission Assignments</h4>
                            <?php if(empty($agentAssignments)): ?>
                            <p class="text-mono" style="color:var(--text-secondary); text-align:center; padding:30px;">No active assignments</p>
                            <?php else: ?>
                            <table class="assignment-table">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Code</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Assigned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($agentAssignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['operation_title']); ?></td>
                                        <td class="text-mono"><?php echo htmlspecialchars($assignment['operation_code']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['role']); ?></td>
                                        <td><span class="badge badge-<?php echo $assignment['status']; ?>"><?php echo ucfirst($assignment['status']); ?></span></td>
                                        <td class="text-mono"><?php echo date('Y-m-d', strtotime($assignment['assigned_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                        
                        <div id="tab-activity" class="tab-content">
                            <h4 style="margin-bottom:15px;">Activity Log</h4>
                            <ul class="activity-log">
                                <?php if(empty($agentLogs)): ?>
                                <li style="text-align:center; color:var(--text-secondary); padding:30px;">No activity recorded</li>
                                <?php else: ?>
                                <?php foreach($agentLogs as $log): ?>
                                <li>
                                    <div class="activity-icon">
                                        <i data-lucide="activity" style="width:16px; height:16px;"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-action"><?php echo str_replace('_', ' ', $log['action']); ?></div>
                                        <div class="activity-desc"><?php echo htmlspecialchars($log['description']); ?></div>
                                        <div class="activity-time">By: <?php echo htmlspecialchars($log['actor'] ?? 'System'); ?> // <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <div style="text-align:center; padding:40px 0; color:var(--text-secondary);">
                            <i data-lucide="user" style="width:40px; height:40px; margin-bottom:10px; opacity:0.5;"></i>
                            <p class="text-mono">SELECT AN AGENT TO VIEW PROFILE</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity Section -->
                <div class="card" style="margin-top:30px;">
                    <div class="card-header">
                        <span class="card-title">Recent System Activity</span>
                        <span class="text-mono">LAST 10 ACTIONS</span>
                    </div>
                    <ul class="activity-log">
                        <?php foreach($recentActivity as $log): ?>
                        <li>
                            <div class="activity-icon">
                                <i data-lucide="activity" style="width:16px; height:16px;"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-action"><?php echo str_replace('_', ' ', $log['action']); ?></div>
                                <div class="activity-desc">
                                    <?php echo htmlspecialchars($log['description']); ?>
                                    <?php if($log['codename']): ?>
                                    <span style="color:var(--neon-green);"> // <?php echo htmlspecialchars($log['codename']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">By: <?php echo htmlspecialchars($log['actor'] ?? 'System'); ?> // <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Agent Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="createModalTitle" style="font-size:1.1rem;">Add New Agent</h3>
                <button class="doc-close" onclick="closeModal('create')"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="personnel.php">
                <div class="modal-body">
                    <input type="hidden" name="create_agent" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Codename *</label>
                            <input type="text" name="codename" required placeholder="e.g., PHANTOM">
                        </div>
                        <div class="form-group">
                            <label>Rank *</label>
                            <select name="rank" required>
                                <option value="Recruit">Recruit</option>
                                <option value="Operative">Operative</option>
                                <option value="Field Agent">Field Agent</option>
                                <option value="Senior Agent">Senior Agent</option>
                                <option value="Tech Specialist">Tech Specialist</option>
                                <option value="Intelligence Analyst">Intelligence Analyst</option>
                                <option value="Team Leader">Team Leader</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" required>
                                <option value="active">Active</option>
                                <option value="deployed">Deployed</option>
                                <option value="inactive">Inactive</option>
                                <option value="mia">MIA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Joined Date *</label>
                            <input type="date" name="joined_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Specialization *</label>
                        <input type="text" name="specialization" required placeholder="e.g., Infiltration, Surveillance, Combat">
                    </div>
                    
                    <div class="form-group">
                        <label>Skills (comma-separated) *</label>
                        <textarea name="skills" required placeholder="e.g., Stealth,Hacking,Combat,Driving,Recon"></textarea>
                    </div>
                    
                    <div class="alert" style="background:rgba(0,212,255,0.1); border:1px solid var(--neon-blue); color:var(--neon-blue);">
                        <i data-lucide="info" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i>
                        Default password will be set to: 123456 (Agent must change on first login)
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('create')">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;">Create Agent</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Agent Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editModalTitle" style="font-size:1.1rem;">Edit Agent Profile</h3>
                <button class="doc-close" onclick="closeModal('edit')"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="personnel.php">
                <div class="modal-body">
                    <input type="hidden" name="agent_id" id="edit_agent_id">
                    <input type="hidden" name="update_agent" value="1">
                    
                    <div class="form-group">
                        <label>Rank</label>
                        <input type="text" name="rank" id="edit_rank" required>
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization" required>
                    </div>
                    <div class="form-group">
                        <label>Skills (comma-separated)</label>
                        <textarea name="skills" id="edit_skills" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('edit')">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assign Operation Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size:1.1rem;">Assign to Operation</h3>
                <button class="doc-close" onclick="closeModal('assign')"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="personnel.php">
                <div class="modal-body">
                    <input type="hidden" name="agent_id" id="assign_agent_id">
                    <input type="hidden" name="assign_operation" value="1">
                    
                    <div class="form-group">
                        <label>Select Operation *</label>
                        <select name="operation_id" id="assign_operation_id" required>
                            <option value="">-- Select Operation --</option>
                            <?php foreach($operations as $op): ?>
                            <option value="<?php echo $op['id']; ?>"><?php echo htmlspecialchars($op['code']); ?> - <?php echo htmlspecialchars($op['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Role in Operation *</label>
                        <input type="text" name="role" required placeholder="e.g., Team Leader, Surveillance, Combat">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('assign')">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto;">Assign Agent</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header">
                <h3 style="font-size:1.1rem; color:var(--neon-red);">Confirm Termination</h3>
                <button class="doc-close" onclick="closeModal('delete')"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" action="personnel.php">
                <div class="modal-body">
                    <input type="hidden" name="agent_id" id="delete_agent_id">
                    <input type="hidden" name="delete_agent" value="1">
                    <p style="color:var(--text-secondary); margin-bottom:20px;">
                        Are you sure you want to terminate this agent? This will:
                    </p>
                    <ul style="color:var(--text-secondary); margin-left:20px; margin-bottom:20px;">
                        <li>Permanently delete agent profile</li>
                        <li>Remove all operation assignments</li>
                        <li>Delete associated user account</li>
                        <li>Log this action in system records</li>
                    </ul>
                    <div class="alert alert-error">
                        <i data-lucide="alert-triangle" style="width:16px; display:inline; vertical-align:middle; margin-right:5px;"></i>
                        WARNING: This action cannot be undone
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('delete')">Cancel</button>
                    <button type="submit" class="btn-system" style="width:auto; background:var(--neon-red); color:#fff;">Terminate</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function filterAgents(filter) { window.location.href = 'personnel.php?filter=' + filter; }
        function searchAgents() {
            const search = document.getElementById('searchInput').value;
            window.location.href = 'personnel.php?search=' + encodeURIComponent(search);
        }
        function sortAgents(sort) { window.location.href = 'personnel.php?sort=' + sort; }
        function viewAgent(id) { window.location.href = 'personnel.php?view=' + id; }
        
        function openModal(type) {
            document.getElementById(type + 'Modal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeModal(type) {
            document.getElementById(type + 'Modal').classList.remove('active');
        }
        
        function openEditModal(id) {
            document.getElementById('edit_agent_id').value = id;
            <?php if($selectedAgent): ?>
            document.getElementById('edit_rank').value = '<?php echo addslashes($selectedAgent['rank']); ?>';
            document.getElementById('edit_specialization').value = '<?php echo addslashes($selectedAgent['specialization']); ?>';
            document.getElementById('edit_skills').value = '<?php echo addslashes($selectedAgent['skills']); ?>';
            <?php endif; ?>
            openModal('edit');
        }
        
        function openAssignModal(id) {
            document.getElementById('assign_agent_id').value = id;
            openModal('assign');
        }
        
        function openDeleteModal(id) {
            document.getElementById('delete_agent_id').value = id;
            openModal('delete');
        }
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) { if(e.target === this) this.classList.remove('active'); });
        });
        
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') { document.querySelectorAll('.modal').forEach(modal => modal.classList.remove('active')); }
        });
        
        lucide.createIcons();
    </script>
</body>
</html>