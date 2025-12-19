<?php
// admin_contact.php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];

// Handle actions
if (isset($_GET['action'])) {
    $message_id = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'mark_read':
            markMessageRead($conn, $message_id, $admin_id);
            break;
            
        case 'mark_replied':
            markMessageReplied($conn, $message_id, $admin_id);
            break;
            
        case 'delete':
            deleteMessage($conn, $message_id, $admin_id);
            break;
            
        case 'add_note':
            if (isset($_POST['note'])) {
                addMessageNote($conn, $message_id, $_POST['note'], $admin_id);
            }
            break;
    }
}

// Function to mark message as read
function markMessageRead($conn, $message_id, $admin_id) {
    $sql = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'contact_update', 'Marked contact message #{$message_id} as read')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Message marked as read";
    } else {
        $_SESSION['error'] = "Failed to update message";
    }
    
    header("Location: admin_contact.php?id=" . $message_id);
    exit();
}

// Function to mark message as replied
function markMessageReplied($conn, $message_id, $admin_id) {
    $sql = "UPDATE contact_messages SET status = 'replied', replied_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'contact_update', 'Marked contact message #{$message_id} as replied')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Message marked as replied";
    } else {
        $_SESSION['error'] = "Failed to update message";
    }
    
    header("Location: admin_contact.php?id=" . $message_id);
    exit();
}

// Function to add note to message
function addMessageNote($conn, $message_id, $note, $admin_id) {
    $note = sanitizeInput($note);
    $sql = "UPDATE contact_messages SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[Admin Note - " . date('Y-m-d H:i:s') . "]:\n{$note}\n') WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log activity
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'contact_note', 'Added note to contact message #{$message_id}')";
        mysqli_query($conn, $log_sql);
        
        $_SESSION['success'] = "Note added successfully";
    } else {
        $_SESSION['error'] = "Failed to add note";
    }
    
    header("Location: admin_contact.php?id=" . $message_id);
    exit();
}

// Get message details if ID is provided
$message = null;
if (isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    $sql = "SELECT * FROM contact_messages WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $message = mysqli_fetch_assoc($result);
    
    // Mark as read if viewing for first time
    if ($message && $message['status'] == 'new') {
        $update_sql = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $message_id);
        mysqli_stmt_execute($update_stmt);
    }
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') as new_messages,
    (SELECT COUNT(*) FROM contact_messages WHERE status = 'read') as read_messages,
    (SELECT COUNT(*) FROM contact_messages WHERE status = 'replied') as replied_messages,
    (SELECT COUNT(*) FROM contact_messages) as total_messages";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent messages
$recent_sql = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 10";
$recent_result = mysqli_query($conn, $recent_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Sarathi Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #06D6A0;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card.new { border-top-color: var(--warning); }
        .stat-card.read { border-top-color: var(--info); }
        .stat-card.replied { border-top-color: var(--secondary); }
        .stat-card.total { border-top-color: var(--primary); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .table-container {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--secondary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-new { background: #fef3c7; color: #92400e; }
        .status-read { background: #dbeafe; color: #1e40af; }
        .status-replied { background: #d1fae5; color: #065f46; }
        .status-archived { background: #e5e7eb; color: #374151; }
        
        .message-detail {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'Nunito', sans-serif;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Contact Messages</h1>
                <p style="color: var(--text-light);">Manage contact form submissions</p>
            </div>
            <div>
                <a href="admin_panel.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #fca5a5;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card new">
                <div class="stat-number"><?php echo $stats['new_messages']; ?></div>
                <h3>New Messages</h3>
                <p style="color: var(--text-light); font-size: 0.9rem;">Awaiting review</p>
            </div>
            
            <div class="stat-card read">
                <div class="stat-number"><?php echo $stats['read_messages']; ?></div>
                <h3>Read</h3>
                <p style="color: var(--text-light); font-size: 0.9rem;">Messages read</p>
            </div>
            
            <div class="stat-card replied">
                <div class="stat-number"><?php echo $stats['replied_messages']; ?></div>
                <h3>Replied</h3>
                <p style="color: var(--text-light); font-size: 0.9rem;">Messages replied</p>
            </div>
            
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total_messages']; ?></div>
                <h3>Total</h3>
                <p style="color: var(--text-light); font-size: 0.9rem;">All messages</p>
            </div>
        </div>
        
        <!-- Recent Messages Table -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Recent Messages</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($recent_result)): ?>
                        <tr>
                            <td>CM-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <strong><?php echo $row['name']; ?></strong><br>
                                <small style="color: var(--text-light);"><?php echo $row['organization'] ?: 'No organization'; ?></small>
                            </td>
                            <td><?php echo $row['email']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="admin_contact.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if($row['status'] == 'new'): ?>
                                        <a href="admin_contact.php?action=mark_read&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Mark Read
                                        </a>
                                    <?php elseif($row['status'] == 'read'): ?>
                                        <a href="admin_contact.php?action=mark_replied&id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-reply"></i> Mark Replied
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Message Detail View -->
        <?php if($message): ?>
            <div class="message-detail">
                <div class="message-header">
                    <div>
                        <h2>Message Details</h2>
                        <p style="color: var(--text-light);">
                            ID: CM-<?php echo str_pad($message['id'], 6, '0', STR_PAD_LEFT); ?> | 
                            Status: <span class="status-badge status-<?php echo $message['status']; ?>">
                                <?php echo ucfirst($message['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <span style="color: var(--text-light); font-size: 0.9rem;">
                            <?php echo date('F j, Y, g:i a', strtotime($message['created_at'])); ?>
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h4 style="color: var(--text-light); margin-bottom: 5px;">Name</h4>
                            <p style="font-size: 1.1rem;"><?php echo $message['name']; ?></p>
                        </div>
                        <div>
                            <h4 style="color: var(--text-light); margin-bottom: 5px;">Email</h4>
                            <p style="font-size: 1.1rem;">
                                <a href="mailto:<?php echo $message['email']; ?>">
                                    <?php echo $message['email']; ?>
                                </a>
                            </p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: var(--text-light); margin-bottom: 5px;">Organization</h4>
                        <p style="font-size: 1.1rem;"><?php echo $message['organization'] ?: 'Not provided'; ?></p>
                    </div>
                    
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--text-light); margin-bottom: 10px;">Message</h4>
                        <div style="background: var(--light-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                    
                    <!-- Technical Details -->
                    <div style="background: var(--light-bg); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: var(--text-light); margin-bottom: 10px;">Technical Details</h4>
                        <div style="font-size: 0.9rem; color: var(--text-light);">
                            <div>IP Address: <?php echo $message['ip_address']; ?></div>
                            <div>User Agent: <?php echo substr($message['user_agent'], 0, 100) . '...'; ?></div>
                        </div>
                    </div>
                    
                    <!-- Admin Notes -->
                    <?php if($message['admin_notes']): ?>
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: var(--text-light); margin-bottom: 10px;">Admin Notes</h4>
                            <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border: 1px solid #fbbf24;">
                                <?php echo nl2br(htmlspecialchars($message['admin_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="message-actions">
                        <?php if($message['status'] == 'new'): ?>
                            <a href="admin_contact.php?action=mark_read&id=<?php echo $message['id']; ?>" class="btn btn-success">
                                <i class="fas fa-check"></i> Mark as Read
                            </a>
                        <?php elseif($message['status'] == 'read'): ?>
                            <a href="admin_contact.php?action=mark_replied&id=<?php echo $message['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-reply"></i> Mark as Replied
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="document.getElementById('noteForm').style.display='block'" class="btn btn-primary">
                            <i class="fas fa-sticky-note"></i> Add Note
                        </button>
                        
                        <a href="mailto:<?php echo $message['email']; ?>?subject=Re: Your message to Sarathi" class="btn btn-primary">
                            <i class="fas fa-envelope"></i> Reply via Email
                        </a>
                    </div>
                    
                    <!-- Add Note Form -->
                    <div id="noteForm" style="display: none; margin-top: 30px;">
                        <h4 style="margin-bottom: 15px;">Add Admin Note</h4>
                        <form method="POST" action="admin_contact.php?action=add_note&id=<?php echo $message['id']; ?>">
                            <div class="form-group">
                                <textarea name="note" rows="4" placeholder="Add your notes here..." required></textarea>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Note
                                </button>
                                <button type="button" onclick="document.getElementById('noteForm').style.display='none'" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Confirm before deleting
        function confirmDelete(messageId) {
            if (confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
                window.location.href = 'admin_contact.php?action=delete&id=' + messageId;
            }
        }
        
        // Show/Hide note form
        function toggleNoteForm() {
            const form = document.getElementById('noteForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>