<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
require_once '../config/database.php';

$message = '';
$messageType = '';

// SEND Message
if(isset($_POST['send_message'])) {
    try {
        $receiver_id = $_POST['receiver_id'];
        $message_text = $_POST['message'];
        
        if(!empty(trim($message_text))) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $message_text]);
            $message = "Message sent successfully";
            $messageType = 'success';
        }
    } catch(PDOException $e) {
        $message = "Error sending message: " . $e->getMessage();
        $messageType = 'error';
    }
}

// MARK as Read
if(isset($_GET['mark_read'])) {
    try {
        $msg_id = $_GET['mark_read'];
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$msg_id, $_SESSION['user_id']]);
    } catch(PDOException $e) {}
}

// Get Contacts
$stmt = $conn->prepare("SELECT u.id, u.username, a.codename, a.status, a.specialization,
    (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u 
    JOIN agents a ON u.id = a.user_id 
    WHERE u.id != ?
    ORDER BY a.status DESC, a.codename ASC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$contacts = $stmt->fetchAll();

// Get Selected Contact
$selected_contact = isset($_GET['contact']) ? $_GET['contact'] : ($contacts[0]['id'] ?? null);

// Get Messages
$messages = [];
if($selected_contact) {
    $stmt = $conn->prepare("SELECT m.*, u.username as sender_name, u.id as sender_id 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
        ORDER BY m.created_at ASC");
    $stmt->execute([$selected_contact, $_SESSION['user_id'], $_SESSION['user_id'], $selected_contact]);
    $messages = $stmt->fetchAll();
}

// Get Contact Info
$contact_info = null;
if($selected_contact) {
    $stmt = $conn->prepare("SELECT u.username, a.codename, a.status, a.specialization FROM users u JOIN agents a ON u.id = a.user_id WHERE u.id = ?");
    $stmt->execute([$selected_contact]);
    $contact_info = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXA | Secure Channel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .channel-container {
            display: grid; grid-template-columns: 320px 1fr; gap: 20px;
            height: calc(100vh - 200px);
        }
        .channel-list {
            background: rgba(15, 15, 15, 0.4); border: 1px solid var(--border-subtle);
            border-radius: 8px; overflow: hidden; display: flex; flex-direction: column;
        }
        .channel-header {
            padding: 20px; border-bottom: 1px solid var(--border-subtle);
            background: rgba(0, 0, 0, 0.3); display: flex; justify-content: space-between; align-items: center;
        }
        .contact-list {
            list-style: none; overflow-y: auto; flex: 1;
        }
        .contact-item {
            padding: 15px 20px; border-bottom: 1px solid var(--border-subtle);
            cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 12px;
        }
        .contact-item:hover { background: rgba(255,255,255,0.05); }
        .contact-item.active {
            background: rgba(255,255,255,0.08);
            border-left: 3px solid var(--neon-green);
        }
        .contact-avatar {
            width: 45px; height: 45px; background: rgba(255,255,255,0.1);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
        }
        .contact-info { flex: 1; min-width: 0; }
        .contact-name {
            font-size: 0.9rem; font-weight: 600; margin-bottom: 3px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .contact-status {
            font-size: 0.7rem; color: var(--text-secondary);
            display: flex; align-items: center; gap: 5px;
        }
        .contact-status .dot {
            width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
        }
        .contact-status .dot.online { background: var(--neon-green); }
        .contact-status .dot.offline { background: var(--text-secondary); }
        .contact-status .dot.deployed { background: var(--neon-blue); }
        .unread-badge {
            background: var(--neon-red); color: #fff; font-size: 0.65rem;
            padding: 2px 8px; border-radius: 10px; font-family: var(--font-mono);
            flex-shrink: 0;
        }
        .chat-window {
            background: rgba(15, 15, 15, 0.4); border: 1px solid var(--border-subtle);
            border-radius: 8px; display: flex; flex-direction: column; overflow: hidden;
        }
        .chat-header {
            padding: 20px; border-bottom: 1px solid var(--border-subtle);
            background: rgba(0, 0, 0, 0.3); display: flex; justify-content: space-between; align-items: center;
        }
        .chat-encryption {
            font-size: 0.7rem; color: var(--neon-green);
            display: flex; align-items: center; gap: 5px; font-family: var(--font-mono);
        }
        .chat-messages {
            flex: 1; padding: 20px; overflow-y: auto;
            display: flex; flex-direction: column; gap: 15px;
        }
        .message {
            max-width: 70%; padding: 12px 16px; border-radius: 8px;
            font-size: 0.85rem; line-height: 1.5;
        }
        .message.sent {
            align-self: flex-end; background: rgba(0, 255, 157, 0.1);
            border: 1px solid rgba(0, 255, 157, 0.3);
            border-bottom-right-radius: 2px;
        }
        .message.received {
            align-self: flex-start; background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            border-bottom-left-radius: 2px;
        }
        .message-meta {
            font-size: 0.65rem; color: var(--text-secondary);
            margin-top: 5px; font-family: var(--font-mono);
            display: flex; justify-content: space-between; gap: 10px;
        }
        .chat-input {
            padding: 20px; border-top: 1px solid var(--border-subtle);
            background: rgba(0, 0, 0, 0.3); display: flex; gap: 10px;
        }
        .chat-input input {
            flex: 1; padding: 12px 16px; background: rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border-subtle); color: var(--text-primary);
            border-radius: 4px; font-family: var(--font-mono);
            font-size: 0.85rem; outline: none;
        }
        .chat-input input:focus { border-color: var(--text-primary); }
        .chat-input button {
            padding: 12px 20px; background: var(--text-primary); color: #000;
            border: none; border-radius: 4px; cursor: pointer;
            font-weight: 600; text-transform: uppercase;
            font-size: 0.75rem; transition: 0.3s;
        }
        .chat-input button:hover {
            background: #fff; box-shadow: 0 0 15px rgba(255,255,255,0.2);
        }
        .empty-state {
            text-align: center; padding: 60px 20px; color: var(--text-secondary);
        }
        .empty-state i { opacity: 0.3; margin-bottom: 15px; }
        
        @media (max-width: 900px) {
            .channel-container { grid-template-columns: 1fr; }
            .channel-list { display: none; }
            .channel-list.mobile-show { display: flex; }
            .back-btn { display: block !important; }
        }
        .back-btn {
            display: none; background: none; border: none;
            color: var(--text-primary); cursor: pointer;
            margin-right: 15px;
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
                <div class="alert alert-<?php echo $messageType; ?>" style="padding: 15px 20px; border-radius: 4px; margin-bottom: 20px; font-size: 0.85rem; font-family: var(--font-mono); background: rgba(0, 255, 157, 0.1); border: 1px solid var(--neon-green); color: var(--neon-green);">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-bottom:20px;">
                    <h2 style="font-size:1.5rem;">Secure Channel</h2>
                    <p class="text-mono">ENCRYPTED COMMUNICATION // END-TO-END ENCRYPTION ACTIVE</p>
                </div>
                
                <div class="channel-container">
                    <!-- Contact List -->
                    <div class="channel-list" id="channelList">
                        <div class="channel-header">
                            <span class="card-title">Active Contacts</span>
                            <span class="text-mono"><?php echo count($contacts); ?> AGENTS</span>
                        </div>
                        <ul class="contact-list">
                            <?php foreach($contacts as $contact): ?>
                            <li class="contact-item <?php echo $selected_contact == $contact['id'] ? 'active' : ''; ?>" 
                                data-contact="<?php echo $contact['id']; ?>" 
                                onclick="selectContact(<?php echo $contact['id']; ?>)">
                                <div class="contact-avatar"><?php echo strtoupper(substr($contact['codename'], 0, 2)); ?></div>
                                <div class="contact-info">
                                    <div class="contact-name"><?php echo htmlspecialchars($contact['codename']); ?></div>
                                    <div class="contact-status">
                                        <span class="dot <?php echo $contact['status']; ?>"></span>
                                        <?php echo ucfirst($contact['status']); ?>
                                    </div>
                                </div>
                                <?php if($contact['unread_count'] > 0): ?>
                                <span class="unread-badge"><?php echo $contact['unread_count']; ?></span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Chat Window -->
                    <div class="chat-window">
                        <?php if($selected_contact && $contact_info): ?>
                        <div class="chat-header">
                            <div style="display: flex; align-items: center;">
                                <button class="back-btn" onclick="showContactList()">
                                    <i data-lucide="arrow-left"></i>
                                </button>
                                <div>
                                    <h3 style="font-size:1rem;"><?php echo htmlspecialchars($contact_info['codename']); ?></h3>
                                    <span class="text-mono">ID: AGT-<?php echo str_pad($selected_contact, 3, '0', STR_PAD_LEFT); ?> // <?php echo htmlspecialchars($contact_info['specialization']); ?></span>
                                </div>
                            </div>
                            <div class="chat-encryption">
                                <i data-lucide="lock" style="width:12px;"></i> AES-256 ENCRYPTED
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if(empty($messages)): ?>
                            <div class="empty-state">
                                <i data-lucide="message-square" style="width:60px; height:60px;"></i>
                                <p>NO MESSAGES YET</p>
                                <p class="text-mono">START ENCRYPTED CONVERSATION</p>
                            </div>
                            <?php else: ?>
                            <?php foreach($messages as $msg): ?>
                            <?php if($msg['receiver_id'] == $_SESSION['user_id'] && !$msg['is_read']): ?>
                                <!-- Mark as read via JS -->
                            <?php endif; ?>
                            <div class="message <?php echo $msg['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                                <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-meta">
                                    <span><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                    <span><?php echo $msg['is_read'] ? '✓✓ READ' : '✓ SENT'; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="channel.php<?php echo $selected_contact ? '?contact=' . $selected_contact : ''; ?>" class="chat-input">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_contact; ?>">
                            <input type="hidden" name="send_message" value="1">
                            <input type="text" name="message" id="messageInput" placeholder="Type encrypted message..." autocomplete="off">
                            <button type="submit"><i data-lucide="send" style="width:16px; display:inline; vertical-align:middle;"></i> Send</button>
                        </form>
                        <?php else: ?>
                        <div class="chat-header">
                            <div>
                                <h3 style="font-size:1rem;">Select Contact</h3>
                                <span class="text-mono">CHOOSE AN AGENT TO COMMUNICATE</span>
                            </div>
                        </div>
                        <div class="chat-messages">
                            <div class="empty-state">
                                <i data-lucide="radio" style="width:60px; height:60px;"></i>
                                <p>NO CONTACT SELECTED</p>
                                <p class="text-mono">SELECT AN AGENT FROM THE LIST</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
    <script>
        function selectContact(id) {
            window.location.href = 'channel.php?contact=' + id;
        }
        
        function showContactList() {
            document.getElementById('channelList').classList.add('mobile-show');
        }
        
        // Auto scroll to bottom
        const chatMessages = document.getElementById('chatMessages');
        if(chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Auto focus on input
        const messageInput = document.getElementById('messageInput');
        if(messageInput) {
            messageInput.focus();
        }
        
        // Mark messages as read
        <?php foreach($messages as $msg): ?>
        <?php if($msg['receiver_id'] == $_SESSION['user_id'] && !$msg['is_read']): ?>
        fetch('channel.php?mark_read=<?php echo $msg['id']; ?>');
        <?php endif; ?>
        <?php endforeach; ?>
        
        lucide.createIcons();
    </script>
</body>
</html>