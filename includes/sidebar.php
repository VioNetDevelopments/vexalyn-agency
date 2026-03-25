<?php
$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-text">NEXA</div>
        <button class="toggle-btn" style="background:none; border:none; color:#fff; cursor:pointer;">
            <i data-lucide="menu"></i>
        </button>
    </div>
    
    <ul class="nav-links">
        <li>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <i data-lucide="layout-dashboard"></i>
                <span>Command Center</span>
            </a>
        </li>
        <li>
            <a href="operations.php" class="<?php echo $current_page == 'operations' ? 'active' : ''; ?>">
                <i data-lucide="crosshair"></i>
                <span>Operations</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="<?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                <i data-lucide="folder-lock"></i>
                <span>Intel Reports</span>
            </a>
        </li>
        <li>
            <a href="channel.php" class="<?php echo $current_page == 'channel' ? 'active' : ''; ?>">
                <i data-lucide="radio"></i>
                <span>Secure Channel</span>
            </a>
        </li>
        <li>
            <a href="personnel.php" class="<?php echo $current_page == 'personnel' ? 'active' : ''; ?>">
                <i data-lucide="users"></i>
                <span>Personnel</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
            <i data-lucide="log-out" style="width:18px; height:18px; margin-right:8px;"></i>
            <span>Terminate Session</span>
        </a>
    </div>
</aside>