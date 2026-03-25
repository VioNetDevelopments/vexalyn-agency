<header class="top-bar">
    <div style="display:flex; align-items:center;">
        <button class="toggle-btn-mobile" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i data-lucide="menu"></i>
        </button>
        <div class="user-info">
            <span class="text-mono" style="color: #fff; font-weight:600;">
                AGT. <?php echo strtoupper($_SESSION['username']); ?>
            </span>
            <span style="font-size:10px; color:#444; margin-left:5px;">// CLEARANCE L5</span>
        </div>
    </div>
    
    <div class="sys-status">
        <div class="status-item">
            <span class="status-dot"></span> NET: SECURE
        </div>
        <div class="status-item">
            <i data-lucide="shield-check" style="width:12px; height:12px;"></i> ENC: AES-256
        </div>
        <div class="status-item hidden-mobile">
            <i data-lucide="cpu" style="width:12px; height:12px;"></i> SYS: ONLINE
        </div>
    </div>
</header>