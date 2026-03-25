<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Command Center</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="//unpkg.com/globe.gl"></script>
    <script src="//unpkg.com/three"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="content-body fade-in">
                <div class="dashboard-grid">
                    <div class="card" style="grid-column: span 2; padding: 0; border: none; background: transparent;">
                        <div class="card-header" style="padding: 0 24px 20px 24px; border:none;">
                            <div>
                                <span class="card-title">Global Surveillance Network</span>
                                <div class="text-mono" style="margin-top:5px;">LIVE DATA STREAM // ENCRYPTION ACTIVE</div>
                            </div>
                            <div style="text-align:right;">
                                <span class="text-mono" style="color:var(--neon-green)">● LIVE</span>
                            </div>
                        </div>
                        <div id="globe-container"></div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Agent Status</span>
                            <i data-lucide="user-check" style="width:16px; color:var(--text-secondary)"></i>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value" style="color:#fff">NX-01</div>
                                <div class="stat-label">Agent ID</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value" style="color:var(--neon-green)">ACT</div>
                                <div class="stat-label">Status</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">03</div>
                                <div class="stat-label">Active Ops</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">128</div>
                                <div class="stat-label">Intel Pkts</div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">System Logs</span>
                            <i data-lucide="terminal" style="width:16px; color:var(--text-secondary)"></i>
                        </div>
                        <ul class="log-list">
                            <li class="log-item">
                                <div class="log-meta">
                                    <span class="log-title">Access Granted</span>
                                    <span class="log-sub">Command Center Module</span>
                                </div>
                                <span class="log-time">10:42:05</span>
                            </li>
                            <li class="log-item">
                                <div class="log-meta">
                                    <span class="log-title">New Directive</span>
                                    <span class="log-sub">Operation: Black Echo</span>
                                </div>
                                <span class="log-time">09:15:22</span>
                            </li>
                            <li class="log-item">
                                <div class="log-meta">
                                    <span class="log-title">Security Scan</span>
                                    <span class="log-sub">Perimeter Check Complete</span>
                                </div>
                                <span class="log-time">Yesterday</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>
</html>