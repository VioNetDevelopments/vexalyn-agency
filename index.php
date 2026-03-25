<?php
session_start();
require_once 'config/database.php';

if(isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit;
}

$error = "";
$attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;

if($attempts >= 3) {
    $error = "ACCESS DENIED: Too many failed attempts. System locked.";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_attempts'] = 0;
        sleep(1); 
        header("Location: pages/dashboard.php");
        exit;
    } else {
        $_SESSION['login_attempts'] = $attempts + 1;
        $error = "AUTHENTICATION FAILED: Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Secure Access</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box fade-in">
            <div class="login-header">
                <h1>NEXA</h1>
                <p>Intelligence System // Level 5 Clearance</p>
            </div>

            <form id="loginForm" action="index.php" method="POST">
                <div class="form-group">
                    <label>Agent ID</label>
                    <input type="text" name="username" required autocomplete="off" placeholder="ENTER ID...">
                </div>
                <div class="form-group">
                    <label>Passcode</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-system">Initialize Session</button>
            </form>

            <div id="statusMsg" class="status-msg <?php echo !empty($error) ? 'status-error' : ''; ?>">
                <?php echo $error; ?>
            </div>
            
            <div style="margin-top: 30px; border-top: 1px solid #222; padding-top: 15px; display:flex; justify-content:space-between;">
                <div>
                    <p class="text-mono">SYS: <span style="color:var(--neon-green)">ONLINE</span></p>
                </div>
                <div style="text-align:right;">
                    <p class="text-mono">ENC: <span style="color:var(--neon-green)">AES-256</span></p>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>