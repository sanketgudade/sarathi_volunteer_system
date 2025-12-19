<?php
session_start();
require_once 'config/config.php';

// Check if logged in
if (!isset($_SESSION['volunteer_id'])) {
    header("Location: volunteer_login.php");
    exit();
}

$conn = getDBConnection();
$volunteer_id = $_SESSION['volunteer_id'];
$name = $_SESSION['volunteer_name'];
$email = $_SESSION['volunteer_email'];

// Get today's task
$today_task_sql = "SELECT t.*, a.username as assigned_by 
                   FROM tasks t 
                   LEFT JOIN admins a ON t.admin_id = a.id 
                   WHERE t.assigned_to = ? 
                   AND DATE(t.created_at) = CURDATE() 
                   ORDER BY t.id DESC 
                   LIMIT 1";
$today_stmt = mysqli_prepare($conn, $today_task_sql);
if (!$today_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($today_stmt, "i", $volunteer_id);
if (!mysqli_stmt_execute($today_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($today_stmt));
}
$today_result = mysqli_stmt_get_result($today_stmt);
$today_task_data = mysqli_fetch_assoc($today_result);

// If created_at doesn't exist or no tasks today, try without date filter
if (!$today_task_data) {
    // Try getting any assigned task
    $today_task_sql2 = "SELECT t.*, a.username as assigned_by 
                       FROM tasks t 
                       LEFT JOIN admins a ON t.admin_id = a.id 
                       WHERE t.assigned_to = ? 
                       AND t.status IN ('assigned', 'in_progress')
                       ORDER BY t.id DESC 
                       LIMIT 1";
    $today_stmt2 = mysqli_prepare($conn, $today_task_sql2);
    if ($today_stmt2) {
        mysqli_stmt_bind_param($today_stmt2, "i", $volunteer_id);
        if (mysqli_stmt_execute($today_stmt2)) {
            $today_result2 = mysqli_stmt_get_result($today_stmt2);
            $today_task_data = mysqli_fetch_assoc($today_result2);
        }
        mysqli_stmt_close($today_stmt2);
    }
}

// Get today's attendance status - using volunteer_checkins table
$attendance_sql = "SELECT * FROM volunteer_checkins 
                   WHERE volunteer_id = ? 
                   AND DATE(checkin_time) = CURDATE() 
                   ORDER BY id DESC 
                   LIMIT 1";
$attendance_stmt = mysqli_prepare($conn, $attendance_sql);
if (!$attendance_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($attendance_stmt, "i", $volunteer_id);
if (!mysqli_stmt_execute($attendance_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($attendance_stmt));
}
$attendance_result = mysqli_stmt_get_result($attendance_stmt);
$attendance_data = mysqli_fetch_assoc($attendance_result);

// Close previous statement before next query
if (isset($today_stmt)) mysqli_stmt_close($today_stmt);

// Get pending leave requests - using volunteer_leave_requests table
$leave_sql = "SELECT * FROM volunteer_leave_requests 
              WHERE volunteer_id = ? 
              AND status = 'pending' 
              ORDER BY id DESC 
              LIMIT 1";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
if (!$leave_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($leave_stmt, "i", $volunteer_id);
if (!mysqli_stmt_execute($leave_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($leave_stmt));
}
$leave_result = mysqli_stmt_get_result($leave_stmt);
$leave_data = mysqli_fetch_assoc($leave_result);

// Close previous statement
if (isset($attendance_stmt)) mysqli_stmt_close($attendance_stmt);

// Get recent reports - using volunteer_reports table
$reports_sql = "SELECT * FROM volunteer_reports 
                WHERE volunteer_id = ? 
                ORDER BY id DESC 
                LIMIT 3";
$reports_stmt = mysqli_prepare($conn, $reports_sql);
if (!$reports_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($reports_stmt, "i", $volunteer_id);
if (!mysqli_stmt_execute($reports_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($reports_stmt));
}
$reports_result = mysqli_stmt_get_result($reports_stmt);
$reports_data = [];
while($row = mysqli_fetch_assoc($reports_result)) {
    $reports_data[] = $row;
}

// Close previous statement
if (isset($leave_stmt)) mysqli_stmt_close($leave_stmt);

// Get volunteer stats
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed') as completed_tasks,
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('assigned', 'in_progress')) as pending_tasks,
    (SELECT COUNT(*) FROM volunteer_checkins WHERE volunteer_id = ?) as total_checkins";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
if (!$stats_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stats_stmt, "iii", $volunteer_id, $volunteer_id, $volunteer_id);
if (!mysqli_stmt_execute($stats_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($stats_stmt));
}
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats_data = mysqli_fetch_assoc($stats_result);

// Close statements
if (isset($reports_stmt)) mysqli_stmt_close($reports_stmt);
if (isset($stats_stmt)) mysqli_stmt_close($stats_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - Sarathi Field Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== CLEAN COLOR PALETTE ===== */
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

        /* ===== RESET & BASE ===== */
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

        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ===== VOLUNTEER HEADER ===== */
        .volunteer-header {
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

        .volunteer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .volunteer-logo-icon {
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

        .volunteer-logo-text {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .volunteer-logo-text span {
            color: var(--primary);
        }

        .volunteer-user-info {
            display: flex;
            align-items: center;
            gap: 20px;
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

        .user-badge i {
            color: var(--primary);
        }

        /* ===== BUTTONS ===== */
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

        /* ===== MAIN CONTENT ===== */
        .volunteer-content {
            padding: 30px 0;
        }

        /* ===== WELCOME BANNER ===== */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border-radius: var(--radius);
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .welcome-banner h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* ===== STATS CARDS ===== */
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

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

        /* ===== DASHBOARD GRID ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        /* ===== FEATURE CARDS ===== */
        .feature-card {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .feature-card.large {
            grid-column: span 2;
        }

        @media (max-width: 768px) {
            .feature-card.large {
                grid-column: span 1;
            }
        }

        .feature-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .feature-title {
            font-size: 1.25rem;
            color: var(--text-dark);
        }

        /* ===== TASK CARD ===== */
        .task-info {
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 20px;
        }

        .task-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .task-item:last-child {
            margin-bottom: 0;
        }

        .task-item i {
            width: 20px;
            color: var(--primary);
        }

        .task-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in-progress { background: #dbeafe; color: var(--primary); }
        .status-assigned { background: #dbeafe; color: var(--primary); }
        .status-completed { background: #dcfce7; color: var(--secondary); }

        /* ===== ATTENDANCE CARD ===== */
        .attendance-status {
            text-align: center;
            margin-bottom: 20px;
        }

        .attendance-indicator {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
        }

        .attendance-indicator.present {
            background: var(--secondary);
        }

        .attendance-indicator.absent {
            background: var(--text-light);
        }

        .location-info {
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
        }

        .location-info i {
            color: var(--danger);
            margin-right: 8px;
        }

        /* ===== LEAVE CARD ===== */
        .leave-status {
            text-align: center;
            margin-bottom: 20px;
        }

        .leave-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            margin-top: 10px;
        }

        .leave-pending { background: #fef3c7; color: #d97706; }
        .leave-approved { background: #dcfce7; color: var(--secondary); }
        .leave-rejected { background: #fee2e2; color: var(--danger); }

        /* ===== REPORTS CARD ===== */
        .reports-list {
            display: grid;
            gap: 12px;
        }

        .report-item {
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .report-item:hover {
            background: #e2e8f0;
            transform: translateX(4px);
        }

        .report-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .report-content {
            flex: 1;
        }

        .report-date {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        /* ===== EMERGENCY CARD ===== */
        .emergency-card {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: var(--radius);
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .emergency-card:hover {
            transform: scale(1.02);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
        }

        .emergency-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .emergency-btn {
            background: white;
            color: var(--danger);
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: var(--radius-sm);
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .emergency-btn:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        /* ===== PROFILE CARD ===== */
        .profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }

        .profile-details {
            flex: 1;
        }

        .profile-details h4 {
            margin-bottom: 8px;
        }

        .profile-details p {
            color: var(--text-light);
            margin-bottom: 4px;
        }

        /* ===== MODALS ===== */
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

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'Nunito', sans-serif;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* ===== FOOTER ===== */
        .volunteer-footer {
            background: white;
            padding: 30px 0;
            border-top: 1px solid var(--border);
            margin-top: 50px;
        }

        .footer-content {
            text-align: center;
            color: var(--text-light);
        }

        .footer-content p {
            margin-bottom: 8px;
        }

        .support-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .support-link:hover {
            text-decoration: underline;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .volunteer-user-info {
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-banner {
                padding: 30px 20px;
            }
            
            .welcome-banner h2 {
                font-size: 1.5rem;
            }
            
            .feature-card {
                padding: 24px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 16px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .volunteer-user-info {
                align-items: center;
                width: 100%;
            }
            
            .user-badge {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
        }

        /* ===== UTILITY CLASSES ===== */
        .text-center { text-align: center; }
        .mb-1 { margin-bottom: 8px; }
        .mb-2 { margin-bottom: 16px; }
        .mb-3 { margin-bottom: 24px; }
        .mb-4 { margin-bottom: 32px; }
        .mt-1 { margin-top: 8px; }
        .mt-2 { margin-top: 16px; }
        .mt-3 { margin-top: 24px; }
        .mt-4 { margin-top: 32px; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
    </style>
</head>
<body>
    <!-- Volunteer Header -->
    <header class="volunteer-header">
        <div class="container header-content">
            <a href="volunteer_dashboard.php" class="volunteer-logo">
                <div class="volunteer-logo-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="volunteer-logo-text">
                    Sarathi <span>Volunteer</span>
                </div>
            </a>

            <div class="volunteer-user-info">
                <div class="user-badge">
                    <i class="fas fa-user"></i>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-light);"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="volunteer-content">
        <div class="container">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2>Welcome back, <?php echo explode(' ', $name)[0]; ?>! 👋</h2>
                <p>Ready to make a difference today? Check your tasks and mark your attendance.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['completed_tasks'] ?: 0; ?></div>
                    <div class="stat-label">Tasks Completed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['pending_tasks'] ?: 0; ?></div>
                    <div class="stat-label">Active Tasks</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['total_checkins'] ?: 0; ?></div>
                    <div class="stat-label">Total Check-ins</div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Today's Task Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Today's Task</h3>
                        </div>
                    </div>
                    
                    <?php if($today_task_data): ?>
                        <div class="task-info">
                            <div class="task-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($today_task_data['title']); ?></span>
                            </div>
                            <div class="task-item">
                                <i class="fas fa-user-tie"></i>
                                <span>Assigned by: <?php echo htmlspecialchars($today_task_data['assigned_by']); ?></span>
                            </div>
                            <div class="task-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($today_task_data['location_name']); ?></span>
                            </div>
                            <div class="task-item">
                                <i class="fas fa-calendar"></i>
                                <span>Deadline: <?php echo date('d M Y', strtotime($today_task_data['deadline'])); ?></span>
                            </div>
                        </div>
                        
                        <span class="task-status status-<?php echo str_replace('_', '-', $today_task_data['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $today_task_data['status'])); ?>
                        </span>
                        
                        <div class="mt-3">
                            <button class="btn btn-primary" onclick="openTaskModal()">
                                <i class="fas fa-eye"></i> View Task Details
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: var(--text-light);">
                            <i class="fas fa-clipboard" style="font-size: 48px; margin-bottom: 16px;"></i>
                            <p>No tasks assigned for today</p>
                            <p class="mt-2" style="font-size: 0.9rem;">Check back later or contact your coordinator</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attendance Card -->
               <!-- Attendance Card -->
<div class="feature-card">
    <div class="feature-header">
        <div class="feature-icon">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div>
            <h3 class="feature-title">Attendance</h3>
        </div>
    </div>
    
    <?php 
    // Get today's attendance with check-in/out
    $attendance_sql = "SELECT * FROM volunteer_attendance 
                       WHERE volunteer_id = ? 
                       AND DATE(check_in_time) = CURDATE() 
                       ORDER BY id DESC LIMIT 1";
    $attendance_stmt = mysqli_prepare($conn, $attendance_sql);
    mysqli_stmt_bind_param($attendance_stmt, "i", $volunteer_id);
    mysqli_stmt_execute($attendance_stmt);
    $attendance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($attendance_stmt));
    ?>
    
    <div class="attendance-status">
        <?php if($attendance_data): ?>
            <?php if($attendance_data['check_out_time']): ?>
                <div class="attendance-indicator present">
                    <i class="fas fa-check-double"></i>
                </div>
                <h4>Checked Out</h4>
                <p style="color: var(--text-light);">
                    In: <?php echo date('h:i A', strtotime($attendance_data['check_in_time'])); ?><br>
                    Out: <?php echo date('h:i A', strtotime($attendance_data['check_out_time'])); ?>
                </p>
            <?php else: ?>
                <div class="attendance-indicator present">
                    <i class="fas fa-check"></i>
                </div>
                <h4>Checked In</h4>
                <p style="color: var(--text-light);">
                    At: <?php echo date('h:i A', strtotime($attendance_data['check_in_time'])); ?>
                </p>
            <?php endif; ?>
            
            <p style="color: var(--text-light); font-size: 0.9rem;">
                Location: <?php echo htmlspecialchars($attendance_data['location_name'] ?? 'Recorded'); ?>
            </p>
        <?php else: ?>
            <div class="attendance-indicator absent">
                <i class="fas fa-clock"></i>
            </div>
            <h4>Not Checked In</h4>
            <p style="color: var(--text-light);">Mark your attendance for today</p>
        <?php endif; ?>
    </div>
    
    <div class="location-info">
        <i class="fas fa-info-circle"></i>
        <span style="font-size: 0.9rem;">
            Must be within 100m of task location for check-in/out
        </span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <?php if(!$attendance_data): ?>
            <button class="btn btn-success" onclick="checkInOut('check_in')">
                <i class="fas fa-sign-in-alt"></i> Check In
            </button>
        <?php elseif(!$attendance_data['check_out_time']): ?>
            <button class="btn btn-warning" onclick="checkInOut('check_out')">
                <i class="fas fa-sign-out-alt"></i> Check Out
            </button>
        <?php else: ?>
            <button class="btn btn-outline" disabled style="width: 100%;">
                <i class="fas fa-check-circle"></i> Day Completed
            </button>
        <?php endif; ?>
    </div>
</div>

                <!-- Leave Request Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Leave Request</h3>
                        </div>
                    </div>
                    
                    <?php if($leave_data): ?>
                        <div style="background: var(--light-bg); border-radius: var(--radius-sm); padding: 20px; margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-weight: 600;"><?php echo date('d M Y', strtotime($leave_data['start_date'])); ?> - <?php echo date('d M Y', strtotime($leave_data['end_date'])); ?></span>
                                <span class="leave-indicator leave-<?php echo $leave_data['status']; ?>">
                                    <?php echo ucfirst($leave_data['status']); ?>
                                </span>
                            </div>
                            <p style="color: var(--text-light); font-size: 0.9rem;">
                                <?php echo htmlspecialchars(substr($leave_data['reason'], 0, 100)); ?>...
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline" style="width: 100%;" onclick="openLeaveModal()">
                        <i class="fas fa-plus"></i> Apply for Leave
                    </button>
                </div>

                <!-- Reports Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Daily Reports</h3>
                        </div>
                    </div>
                    
                    <?php if(count($reports_data) > 0): ?>
                        <div class="reports-list">
                            <?php foreach($reports_data as $report): ?>
                                <div class="report-item">
                                    <div class="report-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="report-content">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($report['title'] ?? 'Untitled Report'); ?></div>
                                        <div class="report-date">
                                            <?php echo date('d M Y, h:i A', strtotime($report['created_at'] ?? $report['date'] ?? 'now')); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 0; color: var(--text-light);">
                            <i class="fas fa-file" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>No reports submitted yet</p>
                        </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline mt-3" style="width: 100%;" onclick="openReportsModal()">
                        <i class="fas fa-eye"></i> View All Reports
                    </button>
                    <!-- Add Report Button to Task Card -->
<?php if($today_task_data): ?>
    <div class="task-actions mt-3" style="display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="openTaskModal()">
            <i class="fas fa-eye"></i> View Details
        </button>
        <button class="btn btn-success" onclick="openReportModal()">
            <i class="fas fa-file-upload"></i> Submit Report
        </button>
    </div>
<?php endif; ?>
                </div>

                <!-- Profile Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">My Profile</h3>
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-details">
                            <h4><?php echo htmlspecialchars($name); ?></h4>
                            <p><?php echo htmlspecialchars($email); ?></p>
                            <p style="font-size: 0.9rem;">Volunteer ID: <?php echo $volunteer_id; ?></p>
                        </div>
                    </div>
                    
                    <button class="btn btn-outline" style="width: 100%;" onclick="openProfileModal()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>

                <!-- Emergency Card -->
                <div class="feature-card large" style="grid-column: span 2;">
                    <div class="emergency-card">
                        <div class="emergency-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 style="margin-bottom: 10px; font-size: 1.5rem;">Emergency Alert</h3>
                        <p style="margin-bottom: 20px; opacity: 0.9;">
                            In case of emergency, press this button to send an instant alert to your coordinator with your live location.
                        </p>
                        <button class="emergency-btn" onclick="sendEmergencyAlert()">
                            <i class="fas fa-bell"></i> SOS EMERGENCY
                        </button>
                        <p style="margin-top: 20px; font-size: 0.9rem; opacity: 0.8;">
                            Only use in genuine emergencies
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="volunteer-footer">
        <div class="container footer-content">
            <p>© 2024 Sarathi Volunteer Management System</p>
            <p>Need help? Contact <a href="mailto:support@sarathi.org" class="support-link">support@sarathi.org</a></p>
        </div>
    </footer>

    <!-- Task Details Modal -->
    <div class="modal" id="taskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-tasks"></i> Task Details</h2>
                <button class="close-modal" onclick="closeTaskModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if($today_task_data): ?>
                    <div class="form-group">
                        <label class="form-label">Task Title</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($today_task_data['title']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="4" readonly><?php echo htmlspecialchars($today_task_data['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($today_task_data['location_name']); ?>" readonly>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Assigned By</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($today_task_data['assigned_by']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deadline</label>
                            <input type="text" class="form-control" value="<?php echo date('d M Y, h:i A', strtotime($today_task_data['deadline'])); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span class="task-status status-<?php echo str_replace('_', '-', $today_task_data['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $today_task_data['status'])); ?>
                            </span>
                            <?php if($today_task_data['status'] !== 'completed'): ?>
                                <button class="btn btn-success btn-sm" onclick="updateTaskStatus('completed')">
                                    <i class="fas fa-check"></i> Mark as Complete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if($today_task_data['location_name']): ?>
                        <div class="mt-3">
                            <a href="https://maps.google.com/?q=<?php echo urlencode($today_task_data['location_name']); ?>" 
                               target="_blank" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-map-marker-alt"></i> Open in Google Maps
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 0; color: var(--text-light);">
                        <i class="fas fa-clipboard" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <p>No task details available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Leave Request Modal -->
    <div class="modal" id="leaveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-plus"></i> Apply for Leave</h2>
                <button class="close-modal" onclick="closeLeaveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="leaveForm" action="volunteer_leave.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type" class="form-control" required>
                            <option value="">Select Leave Type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="emergency">Emergency</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="4" required placeholder="Please provide a reason for your leave"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeLeaveModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal" id="profileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                <button class="close-modal" onclick="closeProfileModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="profileForm" action="update_profile.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="tel" name="emergency_contact" class="form-control" placeholder="Emergency contact number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Your current address"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeProfileModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openTaskModal() {
            document.getElementById('taskModal').classList.add('active');
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').classList.remove('active');
        }
        
        function openLeaveModal() {
            document.getElementById('leaveModal').classList.add('active');
        }
        
        function closeLeaveModal() {
            document.getElementById('leaveModal').classList.remove('active');
        }
        
        function openProfileModal() {
            document.getElementById('profileModal').classList.add('active');
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
        }
        
        function openReportsModal() {
            window.location.href = 'volunteer_reports.php';
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
        
        // Attendance function
        function markAttendance() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        // Send location to server
                        fetch('mark_attendance.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                latitude: lat,
                                longitude: lng,
                                volunteer_id: <?php echo $volunteer_id; ?>
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Attendance marked successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('Error marking attendance. Please try again.');
                        });
                    },
                    function(error) {
                        alert('Please enable location services to mark attendance.');
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }
        
        // Emergency Alert function
        function sendEmergencyAlert() {
            if (confirm('Are you sure you want to send an emergency alert? This will notify your coordinator immediately.')) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            fetch('send_emergency_alert.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    latitude: lat,
                                    longitude: lng,
                                    volunteer_id: <?php echo $volunteer_id; ?>,
                                    volunteer_name: "<?php echo addslashes($name); ?>"
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Emergency alert sent successfully! Help is on the way.');
                                } else {
                                    alert('Error sending alert: ' + data.message);
                                }
                            })
                            .catch(error => {
                                alert('Error sending emergency alert. Please try again or contact your coordinator directly.');
                            });
                        },
                        function(error) {
                            alert('Unable to get your location. Alert sent without location data.');
                            fetch('send_emergency_alert.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    volunteer_id: <?php echo $volunteer_id; ?>,
                                    volunteer_name: "<?php echo addslashes($name); ?>"
                                })
                            });
                        }
                    );
                } else {
                    alert('Emergency alert sent! Your coordinator has been notified.');
                }
            }
        }
        
        // Task status update
        function updateTaskStatus(status) {
            if (confirm('Mark this task as completed?')) {
                const taskId = <?php echo $today_task_data['id'] ?? 'null'; ?>;
                if (taskId) {
                    fetch('update_task_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            status: status,
                            volunteer_id: <?php echo $volunteer_id; ?>
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Task marked as completed!');
                            location.reload();
                        } else {
                            alert('Error updating task status.');
                        }
                    });
                }
            }
        }
        // Check In/Out Function
function checkInOut(action) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const taskId = <?php echo $today_task_data['id'] ?? 'null'; ?>;
                
                fetch('check_in_out.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: action,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(action === 'check_in' ? 
                            'Checked in successfully!' : 
                            'Checked out successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message + 
                              (data.distance ? ' (Distance: ' + data.distance + 'm)' : ''));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            },
            function(error) {
                let errorMessage = 'Please enable location services to check ' + 
                                  (action === 'check_in' ? 'in' : 'out') + '. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage += 'Location permission denied.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage += 'Location information unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage += 'Location request timed out.';
                        break;
                }
                alert(errorMessage);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Report Modal Functions
function openReportModal() {
    document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

// Submit Report Form
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('submit_report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Report submitted successfully!');
            closeReportModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error submitting report: ' + error);
    });
});

// Rank Modal Functions
function openRankModal() {
    document.getElementById('rankModal').classList.add('active');
}

function closeRankModal() {
    document.getElementById('rankModal').classList.remove('active');
}

// Add Rank Button to Dashboard
// Add this button somewhere in your dashboard HTML:
// <button class="btn btn-warning" onclick="openRankModal()">
//     <i class="fas fa-trophy"></i> My Rank
// </button>

// View task location on OpenStreetMap
function viewTaskLocation(lat, lng) {
    window.open(`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=15`, '_blank');
}

// Update markAttendance function to use checkInOut
// Replace the existing markAttendance function with:
function markAttendance() {
    const hasCheckedIn = <?php echo isset($attendance_data) ? 'true' : 'false'; ?>;
    const hasCheckedOut = <?php echo isset($attendance_data['check_out_time']) ? 'true' : 'false'; ?>;
    
    if (!hasCheckedIn) {
        checkInOut('check_in');
    } else if (!hasCheckedOut) {
        checkInOut('check_out');
    } else {
        alert('You have already completed attendance for today.');
    }
}
        // Initialize today's date for leave form
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];
            
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            
            if (startDateInput) {
                startDateInput.min = today;
                startDateInput.value = today;
            }
            
            if (endDateInput) {
                endDateInput.min = tomorrowStr;
                endDateInput.value = tomorrowStr;
            }
        });

        
    </script>
    <!-- Report Submission Modal -->
<div class="modal" id="reportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-upload"></i> Submit Task Report</h2>
            <button class="close-modal" onclick="closeReportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportForm" enctype="multipart/form-data">
                <input type="hidden" name="task_id" value="<?php echo $today_task_data['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label class="form-label">Report Description</label>
                    <textarea name="report_text" class="form-control" rows="4" 
                              placeholder="Describe what you did, any challenges faced, results achieved..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Upload Images (Proof)</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                    <small class="text-muted">Upload before/after photos (max 5 images, 2MB each)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Upload Documents (PDF/Docs)</label>
                    <input type="file" name="documents[]" class="form-control" multiple accept=".pdf,.doc,.docx">
                    <small class="text-muted">Upload reports, receipts, forms (max 3 files, 5MB each)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Upload Videos</label>
                    <input type="file" name="videos[]" class="form-control" multiple accept="video/*">
                    <small class="text-muted">Short video evidence (max 2 videos, 10MB each)</small>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Submit Report
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeReportModal()" style="flex: 1;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- My Rank Modal -->
<div class="modal" id="rankModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-trophy"></i> My Ranking</h2>
            <button class="close-modal" onclick="closeRankModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php
            // Get volunteer ranking
            $rank_sql = "SELECT v.*, 
                        (SELECT COUNT(*) FROM volunteers v2 WHERE v2.total_points > v.total_points) + 1 as rank_position
                        FROM volunteers v 
                        WHERE v.id = ?";
            $rank_stmt = mysqli_prepare($conn, $rank_sql);
            mysqli_stmt_bind_param($rank_stmt, "i", $volunteer_id);
            mysqli_stmt_execute($rank_stmt);
            $rank_data = mysqli_fetch_assoc(mysqli_stmt_get_result($rank_stmt));
            ?>
            
            <div style="text-align: center; padding: 20px;">
                <div class="rank-display" style="font-size: 4rem; color: var(--warning); margin-bottom: 20px;">
                    #<?php echo $rank_data['rank_position'] ?? 'N/A'; ?>
                </div>
                
                <div style="background: var(--light-bg); padding: 20px; border-radius: var(--radius-sm);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: left;">
                        <div>
                            <h4>Points</h4>
                            <div style="font-size: 2rem; color: var(--primary); font-weight: 700;">
                                <?php echo $rank_data['total_points'] ?? 0; ?>
                            </div>
                            <small>Total Points Earned</small>
                        </div>
                        
                        <div>
                            <h4>Tasks Completed</h4>
                            <div style="font-size: 2rem; color: var(--secondary); font-weight: 700;">
                                <?php echo $rank_data['tasks_completed'] ?? 0; ?>
                            </div>
                            <small>Successful Tasks</small>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h4>How Points Work:</h4>
                    <ul style="text-align: left; color: var(--text-light);">
                        <li>✓ Task completion: 10 points</li>
                        <li>✓ On-time submission: 2 bonus points</li>
                        <li>✓ Quality work (admin rated): 3-5 bonus points</li>
                        <li>✓ Emergency response: 15 points</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php 
mysqli_close($conn); 
?>