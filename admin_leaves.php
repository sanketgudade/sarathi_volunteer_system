<?php
session_start();
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = getDBConnection();

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['leave_id'])) {
        $leave_id = intval($_POST['leave_id']);
        $action = $_POST['action'];
        $admin_notes = isset($_POST['admin_notes']) ? mysqli_real_escape_string($conn, $_POST['admin_notes']) : '';
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $update_sql = "UPDATE leave_requests SET 
                      status = ?, 
                      admin_notes = ?, 
                      processed_by = ?, 
                      processed_at = NOW() 
                      WHERE id = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssii", 
            $status,
            $admin_notes,
            $_SESSION['admin_id'],
            $leave_id
        );
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Get leave details for notification
            $leave_sql = "SELECT lr.*, v.full_name as volunteer_name, v.email as volunteer_email 
                         FROM leave_requests lr 
                         JOIN volunteers v ON lr.volunteer_id = v.id 
                         WHERE lr.id = ?";
            $leave_stmt = mysqli_prepare($conn, $leave_sql);
            mysqli_stmt_bind_param($leave_stmt, "i", $leave_id);
            mysqli_stmt_execute($leave_stmt);
            $leave_result = mysqli_stmt_get_result($leave_stmt);
            $leave_data = mysqli_fetch_assoc($leave_result);
            
            // Create notification for volunteer
            $notification_title = "Leave Request " . ucfirst($status);
            $notification_message = "Your leave request for " . date('d M Y', strtotime($leave_data['start_date'])) . 
                                   " to " . date('d M Y', strtotime($leave_data['end_date'])) . 
                                   " has been " . $status . ". " . ($admin_notes ? "Note: " . $admin_notes : "");
            
            $notif_sql = "INSERT INTO notifications (title, message, type, target, created_at) 
                         VALUES (?, ?, 'info', CONCAT('volunteer_', ?), NOW())";
            $notif_stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($notif_stmt, "ssi", 
                $notification_title,
                $notification_message,
                $leave_data['volunteer_id']
            );
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
            
            $_SESSION['success_message'] = "Leave request " . $status . " successfully!";
        } else {
            $_SESSION['error_message'] = "Error processing leave request.";
        }
        
        mysqli_stmt_close($update_stmt);
        header("Location: admin_leaves.php");
        exit();
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_leaves']) && is_array($_POST['selected_leaves'])) {
    $selected_leaves = $_POST['selected_leaves'];
    $bulk_action = $_POST['bulk_action'];
    
    if ($bulk_action === 'delete') {
        $placeholders = implode(',', array_fill(0, count($selected_leaves), '?'));
        $delete_sql = "DELETE FROM leave_requests WHERE id IN ($placeholders)";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        
        // Dynamically bind parameters
        $types = str_repeat('i', count($selected_leaves));
        mysqli_stmt_bind_param($delete_stmt, $types, ...$selected_leaves);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['success_message'] = count($selected_leaves) . " leave request(s) deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting leave requests.";
        }
        
        mysqli_stmt_close($delete_stmt);
        header("Location: admin_leaves.php");
        exit();
    }
}

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$leave_sql = "SELECT lr.*, v.full_name as volunteer_name, v.email as volunteer_email, 
                     v.mobile_number as volunteer_phone, a.username as processed_by_name
              FROM leave_requests lr
              JOIN volunteers v ON lr.volunteer_id = v.id
              LEFT JOIN admins a ON lr.processed_by = a.id
              WHERE 1=1";

$params = [];
$types = "";

if ($filter_status !== 'all') {
    $leave_sql .= " AND lr.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_type !== 'all') {
    $leave_sql .= " AND lr.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_date_from) {
    $leave_sql .= " AND DATE(lr.start_date) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if ($filter_date_to) {
    $leave_sql .= " AND DATE(lr.end_date) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$leave_sql .= " ORDER BY lr.created_at DESC";
$leave_stmt = mysqli_prepare($conn, $leave_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($leave_stmt, $types, ...$params);
}

mysqli_stmt_execute($leave_stmt);
$leave_result = mysqli_stmt_get_result($leave_stmt);
$leave_data = [];
while($row = mysqli_fetch_assoc($leave_result)) {
    $leave_data[] = $row;
}

// Get leave statistics
$stats_sql = "SELECT 
    COUNT(*) as total_leaves,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_leaves
    FROM leave_requests";
$stats_result = mysqli_query($conn, $stats_sql);
$stats_data = mysqli_fetch_assoc($stats_result);

// Get leave types for filter
$types_sql = "SELECT DISTINCT leave_type FROM leave_requests ORDER BY leave_type";
$types_result = mysqli_query($conn, $types_sql);
$leave_types = [];
while($row = mysqli_fetch_assoc($types_result)) {
    $leave_types[] = $row['leave_type'];
}

// Close statement
mysqli_stmt_close($leave_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Sarathi Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #06D6A0;
            --accent: #8b5cf6;
            --warning: #f59e0b;
            --danger: #ef4444;
            --white: #FFFFFF;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 32px rgba(0, 0, 0, 0.12);
            --radius: 16px;
            --radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            color: var(--text-dark);
            background: var(--light-bg);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header */
        .admin-header {
            background: white;
            padding: 18px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .admin-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .admin-logo-text {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .admin-logo-text span {
            color: var(--primary);
        }

        .admin-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: var(--light-bg);
            color: var(--primary);
        }

        .nav-link.active {
            background: var(--primary);
            color: white;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--light-bg);
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: 2px solid var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--secondary);
            color: white;
            border: 2px solid var(--secondary);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: 2px solid var(--danger);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
            border: 2px solid var(--warning);
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-dark);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Main Content */
        .admin-content {
            padding: 30px 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-icon.pending { background: var(--warning); }
        .stat-icon.approved { background: var(--secondary); }
        .stat-icon.rejected { background: var(--danger); }
        .stat-icon.total { background: var(--primary); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 8px;
        }

        /* Filters */
        .filters-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .filters-title {
            font-size: 1.25rem;
            margin-bottom: 20px;
            color: var(--text-dark);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-family: 'Nunito', sans-serif;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            color: var(--text-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--light-bg);
        }

        th {
            padding: 16px 24px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:hover {
            background: var(--light-bg);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: var(--secondary); }
        .status-rejected { background: #fee2e2; color: var(--danger); }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .checkbox-cell {
            width: 50px;
            text-align: center;
        }

        /* Bulk Actions */
        .bulk-actions {
            padding: 16px 24px;
            background: var(--light-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .bulk-select-all {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
            border: 1px solid var(--border);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--text-dark);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: var(--light-bg);
            color: var(--danger);
        }

        .modal-body {
            padding: 24px;
        }

        /* Messages */
        .message {
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .message.success {
            background: #dcfce7;
            color: var(--secondary);
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        /* Footer */
        .admin-footer {
            background: white;
            padding: 30px 0;
            border-top: 1px solid var(--border);
            margin-top: 50px;
        }

        .footer-content {
            text-align: center;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 16px;
            }
            
            .admin-nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 16px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container header-content">
            <a href="admin_dashboard.php" class="admin-logo">
                <div class="admin-logo-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="admin-logo-text">
                    Sarathi <span>Admin</span>
                </div>
            </a>

            <nav class="admin-nav">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="admin_volunteers.php" class="nav-link">
                    <i class="fas fa-users"></i> Volunteers
                </a>
                <a href="admin_tasks.php" class="nav-link">
                    <i class="fas fa-tasks"></i> Tasks
                </a>
                <a href="admin_reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="admin_leaves.php" class="nav-link active">
                    <i class="fas fa-calendar-alt"></i> Leaves
                </a>
                <a href="admin_notifications.php" class="nav-link">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                
                <div class="user-badge">
                    <i class="fas fa-user"></i>
                    <div>
                        <div style="font-weight: 600;"><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-light);">Administrator</div>
                    </div>
                </div>
                
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-content">
        <div class="container">
            <!-- Page Title -->
            <div style="margin-bottom: 30px;">
                <h1 style="font-size: 2rem; color: var(--text-dark); margin-bottom: 8px;">
                    <i class="fas fa-calendar-alt"></i> Leave Management
                </h1>
                <p style="color: var(--text-light);">Manage volunteer leave requests and approvals</p>
            </div>

            <!-- Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['total_leaves']; ?></div>
                    <div class="stat-label">Total Leaves</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['pending_leaves']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['approved_leaves']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['rejected_leaves']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <h3 class="filters-title">Filter Leaves</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach($leave_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $filter_date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $filter_date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_leaves.php" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Leaves Table -->
            <div class="table-container">
                <form id="bulkForm" method="POST">
                    <!-- Bulk Actions -->
                    <div class="bulk-actions" id="bulkActions" style="display: none;">
                        <div class="bulk-select-all">
                            <input type="checkbox" id="selectAllBulk" onchange="toggleBulkSelectAll()">
                            <label for="selectAllBulk" style="cursor: pointer;">
                                <span id="selectedCount">0</span> leaves selected
                            </label>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" name="bulk_action" value="delete" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete selected leave requests?')">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="clearBulkSelection()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>

                    <div class="table-header">
                        <h3 class="table-title">Leave Requests (<?php echo count($leave_data); ?>)</h3>
                        <div style="display: flex; gap: 12px;">
                            <button type="button" class="btn btn-outline" onclick="toggleBulkSelection()">
                                <i class="fas fa-list-check"></i> Bulk Actions
                            </button>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell" style="display: none;" id="bulkHeader">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Volunteer</th>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($leave_data) > 0): ?>
                                <?php foreach($leave_data as $leave): ?>
                                    <tr>
                                        <td class="checkbox-cell" style="display: none;">
                                            <input type="checkbox" name="selected_leaves[]" value="<?php echo $leave['id']; ?>" 
                                                   class="bulk-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($leave['volunteer_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-light);">
                                                <?php echo htmlspecialchars($leave['volunteer_email']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="text-transform: capitalize;"><?php echo $leave['leave_type']; ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo date('d M Y', strtotime($leave['start_date'])); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-light);">
                                                to <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                            </div>
                                        </td>
                                        <td><?php echo $leave['total_days']; ?></td>
                                        <td style="max-width: 200px;">
                                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($leave['reason']); ?>
                                            </div>
                                            <?php if($leave['admin_notes']): ?>
                                                <div style="font-size: 0.85rem; color: var(--warning); margin-top: 4px;">
                                                    <i class="fas fa-sticky-note"></i> Admin Note
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $leave['status']; ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo date('d M Y', strtotime($leave['created_at'])); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-light);">
                                                <?php echo date('h:i A', strtotime($leave['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-outline btn-sm" 
                                                        onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if($leave['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="openApproveModal(<?php echo $leave['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="openRejectModal(<?php echo $leave['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="deleteLeave(<?php echo $leave['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-light);">
                                        <i class="fas fa-calendar" style="font-size: 48px; margin-bottom: 16px;"></i>
                                        <p>No leave requests found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="admin-footer">
        <div class="container footer-content">
            <p>© 2024 Sarathi Volunteer Management System</p>
            <p>Admin Panel - Leave Management</p>
        </div>
    </footer>

    <!-- Leave Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-alt"></i> Leave Details</h2>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="leaveDetails"></div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal" id="approveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle"></i> Approve Leave</h2>
                <button class="close-modal" onclick="closeApproveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="approveForm" method="POST">
                    <input type="hidden" name="leave_id" id="approveLeaveId">
                    <input type="hidden" name="action" value="approve">
                    
                    <div class="form-group">
                        <label class="form-label">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Add any notes for the volunteer..."></textarea>
                    </div>
                    
                    <div style="background: #dcfce7; padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-info-circle" style="color: var(--secondary);"></i>
                            <span style="font-size: 0.9rem;">This will approve the leave request and notify the volunteer.</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-check"></i> Confirm Approval
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeApproveModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-times-circle"></i> Reject Leave</h2>
                <button class="close-modal" onclick="closeRejectModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="rejectForm" method="POST">
                    <input type="hidden" name="leave_id" id="rejectLeaveId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label class="form-label">Reason for Rejection *</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Please provide reason for rejection..." required></textarea>
                    </div>
                    
                    <div style="background: #fee2e2; padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                            <span style="font-size: 0.9rem;">This will reject the leave request and notify the volunteer.</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-danger" style="flex: 1;">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeRejectModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Bulk Selection
        let bulkMode = false;
        
        function toggleBulkSelection() {
            bulkMode = !bulkMode;
            const checkboxes = document.querySelectorAll('.checkbox-cell');
            const bulkHeader = document.getElementById('bulkHeader');
            const bulkActions = document.getElementById('bulkActions');
            
            checkboxes.forEach(cell => {
                cell.style.display = bulkMode ? 'table-cell' : 'none';
            });
            
            bulkHeader.style.display = bulkMode ? 'table-cell' : 'none';
            bulkActions.style.display = bulkMode ? 'flex' : 'none';
            
            if (!bulkMode) {
                clearBulkSelection();
            }
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }
        
        function toggleBulkSelectAll() {
            const selectAllBulk = document.getElementById('selectAllBulk');
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            
            selectAll.checked = selectAllBulk.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllBulk.checked;
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            const selectedCount = document.querySelectorAll('.bulk-checkbox:checked').length;
            const selectAll = document.getElementById('selectAll');
            const selectAllBulk = document.getElementById('selectAllBulk');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            selectedCountSpan.textContent = selectedCount;
            selectAll.checked = selectedCount === checkboxes.length;
            selectAllBulk.checked = selectedCount === checkboxes.length;
        }
        
        function clearBulkSelection() {
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            const selectAll = document.getElementById('selectAll');
            const selectAllBulk = document.getElementById('selectAllBulk');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            selectAll.checked = false;
            selectAllBulk.checked = false;
            updateBulkActions();
        }
        
        // Modal Functions
        function openApproveModal(leaveId) {
            document.getElementById('approveLeaveId').value = leaveId;
            document.getElementById('approveModal').classList.add('active');
        }
        
        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
        }
        
        function openRejectModal(leaveId) {
            document.getElementById('rejectLeaveId').value = leaveId;
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }
        
        function viewLeaveDetails(leaveId) {
            fetch('get_leave_details.php?id=' + leaveId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const details = data.leave;
                        let html = `
                            <div style="display: grid; gap: 16px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div>
                                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 4px;">Volunteer</div>
                                        <div style="font-weight: 600;">${details.volunteer_name}</div>
                                        <div style="font-size: 0.85rem; color: var(--text-light);">${details.volunteer_email}</div>
                                        <div style="font-size: 0.85rem; color: var(--text-light);">${details.volunteer_phone || 'No phone'}</div>
                                    </div>
                                    
                                    <div>
                                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 4px;">Status</div>
                                        <span class="status-badge status-${details.status}" style="margin-bottom: 8px;">
                                            ${details.status.charAt(0).toUpperCase() + details.status.slice(1)}
                                        </span>
                                        <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 8px;">
                                            Submitted: ${new Date(details.created_at).toLocaleDateString('en-US', { 
                                                day: 'numeric', 
                                                month: 'short', 
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="background: var(--light-bg); padding: 16px; border-radius: var(--radius-sm);">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                        <div>
                                            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 4px;">Leave Type</div>
                                            <div style="font-weight: 600; text-transform: capitalize;">${details.leave_type}</div>
                                        </div>
                                        
                                        <div>
                                            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 4px;">Days</div>
                                            <div style="font-weight: 600;">${details.total_days} day(s)</div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 12px;">
                                        <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 4px;">Dates</div>
                                        <div style="font-weight: 600;">
                                            ${new Date(details.start_date).toLocaleDateString('en-US', { 
                                                day: 'numeric', 
                                                month: 'short', 
                                                year: 'numeric'
                                            })} to ${new Date(details.end_date).toLocaleDateString('en-US', { 
                                                day: 'numeric', 
                                                month: 'short', 
                                                year: 'numeric'
                                            })}
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 8px;">Reason for Leave</div>
                                    <div style="background: var(--light-bg); padding: 16px; border-radius: var(--radius-sm);">
                                        ${details.reason}
                                    </div>
                                </div>
                        `;
                        
                        if (details.contact_number || details.emergency_contact) {
                            html += `
                                <div style="background: var(--light-bg); padding: 16px; border-radius: var(--radius-sm);">
                                    <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 8px;">Contact Information</div>
                                    <div style="display: grid; gap: 8px;">
                                        ${details.contact_number ? `<div><strong>Contact During Leave:</strong> ${details.contact_number}</div>` : ''}
                                        ${details.emergency_contact ? `<div><strong>Emergency Contact:</strong> ${details.emergency_contact}</div>` : ''}
                                    </div>
                                </div>
                            `;
                        }
                        
                        if (details.admin_notes) {
                            html += `
                                <div>
                                    <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 8px;">Admin Notes</div>
                                    <div style="background: #fef3c7; padding: 16px; border-radius: var(--radius-sm);">
                                        ${details.admin_notes}
                                    </div>
                                </div>
                            `;
                        }
                        
                        if (details.processed_by_name) {
                            html += `
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <i class="fas fa-user-check"></i> Processed by ${details.processed_by_name} on 
                                    ${new Date(details.processed_at).toLocaleDateString('en-US', { 
                                        day: 'numeric', 
                                        month: 'short', 
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </div>
                            `;
                        }
                        
                        html += `</div>`;
                        
                        document.getElementById('leaveDetails').innerHTML = html;
                        document.getElementById('detailsModal').classList.add('active');
                    }
                })
                .catch(error => {
                    alert('Error loading leave details');
                });
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }
        
        // Delete Leave
        function deleteLeave(leaveId) {
            if (confirm('Are you sure you want to delete this leave request?')) {
                fetch('delete_leave.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        leave_id: leaveId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Leave request deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting leave request: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting leave request');
                });
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.classList.remove('active');
                }
            });
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Leave management loaded');
        });
    </script>
</body>
</html>

<?php
// Close connection
mysqli_close($conn);
?>