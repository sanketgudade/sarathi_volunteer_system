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
                   AND t.status IN ('assigned', 'in_progress')
                   ORDER BY t.deadline ASC 
                   LIMIT 1";
$today_stmt = mysqli_prepare($conn, $today_task_sql);
mysqli_stmt_bind_param($today_stmt, "i", $volunteer_id);
mysqli_stmt_execute($today_stmt);
$today_result = mysqli_stmt_get_result($today_stmt);
$today_task_data = mysqli_fetch_assoc($today_result);

// Get today's attendance
$attendance_sql = "SELECT * FROM volunteer_attendance 
                   WHERE volunteer_id = ? 
                   AND DATE(check_in_time) = CURDATE() 
                   ORDER BY id DESC 
                   LIMIT 1";
$attendance_stmt = mysqli_prepare($conn, $attendance_sql);
mysqli_stmt_bind_param($attendance_stmt, "i", $volunteer_id);
mysqli_stmt_execute($attendance_stmt);
$attendance_result = mysqli_stmt_get_result($attendance_stmt);
$attendance_data = mysqli_fetch_assoc($attendance_result);

// Get pending leave requests
$leave_sql = "SELECT * FROM leave_requests 
              WHERE volunteer_id = ? 
              AND status = 'pending' 
              ORDER BY id DESC 
              LIMIT 1";
$leave_stmt = mysqli_prepare($conn, $leave_sql);
mysqli_stmt_bind_param($leave_stmt, "i", $volunteer_id);
mysqli_stmt_execute($leave_stmt);
$leave_result = mysqli_stmt_get_result($leave_stmt);
$leave_data = mysqli_fetch_assoc($leave_result);

// Get recent reports
$reports_sql = "SELECT dr.*, t.title as task_title 
                FROM daily_reports dr
                LEFT JOIN tasks t ON dr.task_id = t.id
                WHERE dr.volunteer_id = ? 
                ORDER BY dr.submitted_at DESC 
                LIMIT 3";
$reports_stmt = mysqli_prepare($conn, $reports_sql);
mysqli_stmt_bind_param($reports_stmt, "i", $volunteer_id);
mysqli_stmt_execute($reports_stmt);
$reports_result = mysqli_stmt_get_result($reports_stmt);
$reports_data = [];
while($row = mysqli_fetch_assoc($reports_result)) {
    $reports_data[] = $row;
}

// Get volunteer stats
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed') as completed_tasks,
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('assigned', 'in_progress')) as pending_tasks,
    (SELECT COUNT(*) FROM volunteer_attendance WHERE volunteer_id = ?) as total_checkins,
    v.total_points,
    v.current_rank,
    v.tasks_completed
    FROM volunteers v WHERE v.id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "iiii", 
    $volunteer_id,
    $volunteer_id,
    $volunteer_id,
    $volunteer_id
);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats_data = mysqli_fetch_assoc($stats_result);

// Get volunteer rank
$rank_sql = "SELECT current_rank as rank FROM volunteers WHERE id = ?";
$rank_stmt = mysqli_prepare($conn, $rank_sql);
mysqli_stmt_bind_param($rank_stmt, "i", $volunteer_id);
mysqli_stmt_execute($rank_stmt);
$rank_result = mysqli_stmt_get_result($rank_stmt);
$rank_data = mysqli_fetch_assoc($rank_result);

// Get notifications
$notifications_sql = "SELECT * FROM notifications 
                      WHERE target IN ('all', 'active', CONCAT('volunteer_', ?))
                      ORDER BY created_at DESC 
                      LIMIT 5";
$notifications_stmt = mysqli_prepare($conn, $notifications_sql);
mysqli_stmt_bind_param($notifications_stmt, "i", $volunteer_id);
mysqli_stmt_execute($notifications_stmt);
$notifications_result = mysqli_stmt_get_result($notifications_stmt);
$notifications_data = [];
while($row = mysqli_fetch_assoc($notifications_result)) {
    $notifications_data[] = $row;
}

// Get volunteer's latest location
$location_sql = "SELECT last_location_lat as latitude, 
                        last_location_lng as longitude,
                        location_updated_at as timestamp
                 FROM volunteers 
                 WHERE id = ? 
                 AND last_location_lat IS NOT NULL 
                 AND last_location_lng IS NOT NULL";
$location_stmt = mysqli_prepare($conn, $location_sql);
mysqli_stmt_bind_param($location_stmt, "i", $volunteer_id);
mysqli_stmt_execute($location_stmt);
$location_result = mysqli_stmt_get_result($location_stmt);
$location_data = mysqli_fetch_assoc($location_result);

// Get leave history
$leave_history_sql = "SELECT * FROM leave_requests 
                      WHERE volunteer_id = ? 
                      ORDER BY id DESC 
                      LIMIT 5";
$leave_history_stmt = mysqli_prepare($conn, $leave_history_sql);
mysqli_stmt_bind_param($leave_history_stmt, "i", $volunteer_id);
mysqli_stmt_execute($leave_history_stmt);
$leave_history_result = mysqli_stmt_get_result($leave_history_stmt);
$leave_history_data = [];
while($row = mysqli_fetch_assoc($leave_history_result)) {
    $leave_history_data[] = $row;
}

// Debugging - uncomment if needed
// echo "<pre>";
// echo "Stats SQL: " . htmlspecialchars($stats_sql) . "\n";
// echo "Volunteer ID: $volunteer_id\n";
// echo "Stats Data: ";
// print_r($stats_data);
// echo "</pre>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - Sarathi Field Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Leaflet CSS for Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Flatpickr for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        .btn-info {
            background: #0ea5e9;
            color: white;
            border: 2px solid #0ea5e9;
        }

        .btn-info:hover {
            background: #0284c7;
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
            padding: 30px;
            margin-bottom: 30px;
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
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 1rem;
            opacity: 0.9;
            max-width: 600px;
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
            padding: 24px;
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
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
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
        .status-present { background: #dcfce7; color: var(--secondary); }
        .status-absent { background: #fee2e2; color: var(--danger); }
        .status-approved { background: #dcfce7; color: var(--secondary); }
        .status-rejected { background: #fee2e2; color: var(--danger); }
        .status-pending { background: #fef3c7; color: #d97706; }

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

        /* ===== MAP STYLES ===== */
        #taskLocationMap, #reportMap {
            height: 250px;
            width: 100%;
            border-radius: var(--radius-sm);
            margin: 15px 0;
            border: 1px solid var(--border);
        }

        .leaflet-container {
            font-family: 'Nunito', sans-serif;
        }

        /* ===== NOTIFICATIONS ===== */
        .notifications-list {
            display: grid;
            gap: 12px;
        }

        .notification-item {
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: #e2e8f0;
        }

        .notification-icon {
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

        .notification-content {
            flex: 1;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 4px;
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
            max-width: 600px;
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

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'Nunito', sans-serif;
            transition: all 0.3s ease;
            background: var(--light-bg);
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        /* ===== LEAVE HISTORY TABLE ===== */
        .leave-history {
            margin-top: 20px;
        }

        .leave-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            border: 1px solid var(--border);
        }

        .leave-history-date {
            font-weight: 600;
            color: var(--text-dark);
        }

        .leave-history-reason {
            color: var(--text-light);
            font-size: 0.9rem;
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
                padding: 24px;
            }
            
            .welcome-banner h2 {
                font-size: 1.5rem;
            }
            
            .feature-card {
                padding: 20px;
            }
            
            .leave-history-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
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
        .w-full { width: 100%; }
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
                
                <!-- Leave Request Button -->
                <button class="btn btn-info" onclick="openLeaveModal()">
                    <i class="fas fa-calendar-plus"></i> Leave Request
                </button>
                
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
                <p>Ready to make a difference today? Check your assigned tasks and mark your attendance.</p>
                <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                    <div style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: var(--radius-sm);">
                        <div style="font-size: 1.5rem; font-weight: 700;">#<?php echo $rank_data['rank'] ?? 'N/A'; ?></div>
                        <div style="font-size: 0.9rem;">Your Rank</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: var(--radius-sm);">
                        <div style="font-size: 1.5rem; font-weight: 700;"><?php echo $stats_data['total_points'] ?? 0; ?></div>
                        <div style="font-size: 0.9rem;">Points</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: var(--radius-sm);">
                        <div style="font-size: 1.5rem; font-weight: 700;"><?php echo $stats_data['tasks_completed'] ?? 0; ?></div>
                        <div style="font-size: 0.9rem;">Tasks Completed</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['completed_tasks'] ?? 0; ?></div>
                    <div class="stat-label">Tasks Completed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['pending_tasks'] ?? 0; ?></div>
                    <div class="stat-label">Active Tasks</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['total_checkins'] ?? 0; ?></div>
                    <div class="stat-label">Total Check-ins</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats_data['total_points'] ?? 0; ?></div>
                    <div class="stat-label">Total Points</div>
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
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($today_task_data['title']); ?></span>
                            </div>
                            <?php if($today_task_data['assigned_by']): ?>
                            <div class="task-item">
                                <i class="fas fa-user-tie"></i>
                                <span>Assigned by: <?php echo htmlspecialchars($today_task_data['assigned_by']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="task-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($today_task_data['location_name']); ?></span>
                            </div>
                            <div class="task-item">
                                <i class="fas fa-calendar"></i>
                                <span>Deadline: <?php echo date('d M Y, h:i A', strtotime($today_task_data['deadline'])); ?></span>
                            </div>
                        </div>
                        
                        <span class="task-status status-<?php echo str_replace('_', '-', $today_task_data['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $today_task_data['status'])); ?>
                        </span>
                        
                        <!-- Task Location Map -->
                        <?php if($today_task_data['latitude'] && $today_task_data['longitude']): ?>
                        <div id="taskLocationMap" style="height: 200px; margin: 15px 0;"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const taskLat = <?php echo $today_task_data['latitude']; ?>;
                                const taskLng = <?php echo $today_task_data['longitude']; ?>;
                                const taskMap = L.map('taskLocationMap').setView([taskLat, taskLng], 15);
                                
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '© OpenStreetMap contributors'
                                }).addTo(taskMap);
                                
                                L.marker([taskLat, taskLng], {
                                    icon: L.divIcon({
                                        html: '<i class="fas fa-map-marker-alt" style="color: var(--secondary); font-size: 24px;"></i>',
                                        className: 'task-marker'
                                    })
                                }).addTo(taskMap)
                                  .bindPopup('Task Location: <?php echo addslashes($today_task_data["location_name"]); ?>')
                                  .openPopup();
                                  
                                L.circle([taskLat, taskLng], {
                                    color: 'var(--primary)',
                                    fillColor: 'var(--primary)',
                                    fillOpacity: 0.1,
                                    radius: <?php echo $today_task_data['geofence_radius'] ?? 100; ?>
                                }).addTo(taskMap);
                            });
                        </script>
                        <?php endif; ?>
                        
                        <div class="task-actions mt-3" style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="openTaskModal()">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn btn-success" onclick="openReportModal()">
                                <i class="fas fa-file-upload"></i> Submit Report
                            </button>
                            <?php if($today_task_data['latitude'] && $today_task_data['longitude']): ?>
                            <button class="btn btn-info" onclick="openDirectionsModal()">
                                <i class="fas fa-directions"></i> Get Directions
                            </button>
                            <?php endif; ?>
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
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Attendance</h3>
                        </div>
                    </div>
                    
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
                            Must be within <?php echo $today_task_data['geofence_radius'] ?? 100; ?>m of task location for check-in/out
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

                <!-- Leave Management Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Leave Status</h3>
                        </div>
                    </div>
                    
                    <?php if($leave_data): ?>
                        <div style="background: var(--light-bg); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                            <h4 style="margin-bottom: 10px;">Pending Request</h4>
                            <div style="display: grid; gap: 8px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">Leave Type:</span>
                                    <span style="font-weight: 600;"><?php echo ucfirst($leave_data['leave_type']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">From:</span>
                                    <span><?php echo date('d M Y', strtotime($leave_data['start_date'])); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">To:</span>
                                    <span><?php echo date('d M Y', strtotime($leave_data['end_date'])); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">Days:</span>
                                    <span><?php echo $leave_data['total_days']; ?> day(s)</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="task-status status-pending">Pending Approval</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Leave History -->
                    <?php if(count($leave_history_data) > 0): ?>
                        <div class="leave-history">
                            <h4 style="margin-bottom: 15px; font-size: 1rem;">Recent Leave History</h4>
                            <?php foreach($leave_history_data as $leave): ?>
                                <div class="leave-history-item">
                                    <div>
                                        <div class="leave-history-date">
                                            <?php echo date('d M', strtotime($leave['start_date'])); ?> - 
                                            <?php echo date('d M', strtotime($leave['end_date'])); ?>
                                        </div>
                                        <div class="leave-history-reason">
                                            <?php echo htmlspecialchars(substr($leave['reason'], 0, 30)); ?>...
                                        </div>
                                    </div>
                                    <span class="task-status status-<?php echo $leave['status']; ?>">
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 0; color: var(--text-light);">
                            <i class="fas fa-calendar" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>No leave history</p>
                        </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline mt-3" style="width: 100%;" onclick="openLeaveModal()">
                        <i class="fas fa-plus"></i> Request Leave
                    </button>
                </div>

                <!-- Location Tracking Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Live Location</h3>
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px 0;">
                        <div id="locationStatus" style="margin-bottom: 20px; font-size: 0.9rem; padding: 10px; background: var(--light-bg); border-radius: var(--radius-sm);">
                            <i class="fas fa-location-slash"></i> Location sharing off
                        </div>
                        
                        <div style="display: flex; gap: 12px;">
                            <button class="btn btn-success" onclick="startLocationSharing()" style="flex: 1;">
                                <i class="fas fa-play"></i> Start Sharing
                            </button>
                            <button class="btn btn-danger" onclick="stopLocationSharing()" style="flex: 1;">
                                <i class="fas fa-stop"></i> Stop
                            </button>
                        </div>
                        
                        <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-light);">
                            <i class="fas fa-info-circle"></i> 
                            When enabled, admin can see your real-time location
                        </div>
                        
                        <?php if($location_data): ?>
                        <div style="margin-top: 15px; padding: 10px; background: var(--light-bg); border-radius: var(--radius-sm);">
                            <div style="font-size: 0.9rem;">
                                <i class="fas fa-clock"></i> 
                                Last update: <?php echo date('h:i A', strtotime($location_data['timestamp'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Notifications</h3>
                        </div>
                    </div>
                    
                    <?php if(count($notifications_data) > 0): ?>
                        <div class="notifications-list">
                            <?php foreach($notifications_data as $notification): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <?php 
                                        switch($notification['type']) {
                                            case 'alert': echo '<i class="fas fa-exclamation-circle"></i>'; break;
                                            case 'info': echo '<i class="fas fa-info-circle"></i>'; break;
                                            case 'warning': echo '<i class="fas fa-exclamation-triangle"></i>'; break;
                                            default: echo '<i class="fas fa-bell"></i>';
                                        }
                                        ?>
                                    </div>
                                    <div class="notification-content">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div style="font-size: 0.9rem; color: var(--text-light);">
                                            <?php echo htmlspecialchars(substr($notification['message'], 0, 80)); ?>...
                                        </div>
                                        <div class="notification-time">
                                            <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 0; color: var(--text-light);">
                            <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <p>No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reports Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <h3 class="feature-title">Recent Reports</h3>
                        </div>
                    </div>
                    
                    <?php if(count($reports_data) > 0): ?>
                        <div class="notifications-list">
                            <?php foreach($reports_data as $report): ?>
                                <div class="notification-item">
                                    <div class="notification-icon" style="background: var(--secondary);">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($report['task_title'] ?? 'Report'); ?></div>
                                        <div style="font-size: 0.9rem; color: var(--text-light);">
                                            <?php echo htmlspecialchars(substr($report['report_text'], 0, 80)); ?>...
                                        </div>
                                        <div class="notification-time">
                                            <?php echo date('d M Y, h:i A', strtotime($report['submitted_at'])); ?>
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
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($today_task_data['task_category']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($today_task_data['task_priority'])); ?>" readonly>
                        </div>
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
                    
                    <?php if($today_task_data['latitude'] && $today_task_data['longitude']): ?>
                        <div class="mt-3">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $today_task_data['latitude']; ?>,<?php echo $today_task_data['longitude']; ?>" 
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
                    
                    <div id="reportMap" style="height: 200px; margin: 15px 0; display: none;"></div>
                    <button type="button" class="btn btn-outline mb-3" onclick="addLocationToReport()">
                        <i class="fas fa-location-dot"></i> Add Current Location
                    </button>
                    <input type="hidden" name="report_latitude" id="reportLatitude">
                    <input type="hidden" name="report_longitude" id="reportLongitude">
                    
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

    <!-- Leave Request Modal -->
    <div class="modal" id="leaveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-plus"></i> Leave Request</h2>
                <button class="close-modal" onclick="closeLeaveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="leaveForm">
                    <input type="hidden" name="volunteer_id" value="<?php echo $volunteer_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type" class="form-select" required>
                            <option value="">Select Leave Type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="personal">Personal Leave</option>
                            <option value="vacation">Vacation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" id="startDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" id="endDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Total Days</label>
                        <input type="text" name="total_days" class="form-control" id="totalDays" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reason for Leave</label>
                        <textarea name="reason" class="form-control" rows="4" 
                                  placeholder="Please provide a detailed reason for your leave request..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Number During Leave</label>
                        <input type="tel" name="contact_number" class="form-control" 
                               placeholder="Enter contact number where you can be reached">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" 
                               placeholder="Name and number of emergency contact">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Upload Supporting Document (Optional)</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Upload medical certificate or other supporting documents</small>
                    </div>
                    
                    <div style="background: var(--light-bg); padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                            <span style="font-size: 0.9rem;">Your leave request will be reviewed by the coordinator. You'll be notified of the decision.</span>
                        </div>
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

    <!-- Directions Modal -->
    <div class="modal" id="directionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-directions"></i> Get Directions</h2>
                <button class="close-modal" onclick="closeDirectionsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if($today_task_data && $today_task_data['latitude'] && $today_task_data['longitude']): ?>
                    <div style="text-align: center; padding: 20px;">
                        <p>Open directions to your task location in your preferred maps app:</p>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $today_task_data['latitude']; ?>,<?php echo $today_task_data['longitude']; ?>" 
                               target="_blank" class="btn btn-outline">
                                <i class="fab fa-google"></i> Google Maps
                            </a>
                            
                            <a href="https://maps.apple.com/?daddr=<?php echo $today_task_data['latitude']; ?>,<?php echo $today_task_data['longitude']; ?>" 
                               target="_blank" class="btn btn-outline">
                                <i class="fab fa-apple"></i> Apple Maps
                            </a>
                        </div>
                        
                        <div style="margin-top: 20px; padding: 15px; background: var(--light-bg); border-radius: var(--radius-sm);">
                            <div style="font-weight: 600;">Task Location:</div>
                            <div><?php echo htmlspecialchars($today_task_data['location_name']); ?></div>
                            <div style="font-size: 0.9rem; color: var(--text-light); margin-top: 5px;">
                                Coordinates: <?php echo $today_task_data['latitude']; ?>, <?php echo $today_task_data['longitude']; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <i class="fas fa-map" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <p>No location data available for this task</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        // Modal Functions
        function openTaskModal() {
            document.getElementById('taskModal').classList.add('active');
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').classList.remove('active');
        }
        
        function openReportModal() {
            document.getElementById('reportModal').classList.add('active');
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').classList.remove('active');
        }
        
        function openLeaveModal() {
            document.getElementById('leaveModal').classList.add('active');
            // Reset form
            document.getElementById('leaveForm').reset();
            document.getElementById('totalDays').value = '';
        }
        
        function closeLeaveModal() {
            document.getElementById('leaveModal').classList.remove('active');
        }
        
        function openDirectionsModal() {
            document.getElementById('directionsModal').classList.add('active');
        }
        
        function closeDirectionsModal() {
            document.getElementById('directionsModal').classList.remove('active');
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
        
        // Check In/Out Function
        let checkInOutMap;
        
        function checkInOut(action) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const taskId = <?php echo $today_task_data['id'] ?? 'null'; ?>;
                        const taskLat = <?php echo $today_task_data['latitude'] ?? 'null'; ?>;
                        const taskLng = <?php echo $today_task_data['longitude'] ?? 'null'; ?>;
                        const geofenceRadius = <?php echo $today_task_data['geofence_radius'] ?? 100; ?>;
                        
                        // Calculate distance if task location exists
                        let distance = null;
                        if (taskLat && taskLng) {
                            distance = calculateDistance(
                                position.coords.latitude,
                                position.coords.longitude,
                                taskLat,
                                taskLng
                            );
                            
                            if (distance > geofenceRadius) {
                                alert(`You are ${Math.round(distance)}m away from task location. 
                                    You must be within ${geofenceRadius}m to check ${action === 'check_in' ? 'in' : 'out'}.`);
                                return;
                            }
                        }
                        
                        // Send check-in/out request
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
                                alert('Error: ' + data.message);
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
        
        // Calculate distance between two coordinates (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3; // Earth's radius in meters
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lon2 - lon1) * Math.PI / 180;
            
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                      Math.cos(φ1) * Math.cos(φ2) *
                      Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            
            return R * c; // Distance in meters
        }
        
        // Location Tracking for Admin
        let locationInterval;
        let isLocationSharing = false;
        
        function startLocationSharing() {
            if (!navigator.geolocation) {
                alert('Location sharing not supported by your browser');
                return;
            }
            
            if (isLocationSharing) {
                stopLocationSharing();
                return;
            }
            
            // Ask for permission
            if (confirm('Share your live location with admin for tracking? You can stop anytime.')) {
                isLocationSharing = true;
                document.getElementById('locationStatus').innerHTML = 
                    '<span style="color: var(--secondary);"><i class="fas fa-location-dot"></i> Live Tracking ON</span>';
                
                // Send location every 30 seconds
                locationInterval = setInterval(updateLocation, 30000);
                
                // Send immediately
                updateLocation();
                
                alert('Location sharing started. Admin can now track your location.');
            }
        }
        
        function updateLocation() {
            if (!isLocationSharing) return;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const data = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        volunteer_id: <?php echo $volunteer_id; ?>
                    };
                    
                    fetch('update_location.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Location updated:', data.timestamp);
                        }
                    });
                },
                function(error) {
                    console.error('Error getting location:', error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        function stopLocationSharing() {
            if (locationInterval) {
                clearInterval(locationInterval);
                locationInterval = null;
            }
            isLocationSharing = false;
            document.getElementById('locationStatus').innerHTML = 
                '<span style="color: var(--danger);"><i class="fas fa-location-slash"></i> Tracking OFF</span>';
            alert('Location sharing stopped.');
        }
        
        // Report form with location
        function addLocationToReport() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('reportLatitude').value = position.coords.latitude;
                        document.getElementById('reportLongitude').value = position.coords.longitude;
                        
                        // Show map
                        const mapDiv = document.getElementById('reportMap');
                        mapDiv.style.display = 'block';
                        
                        if (!window.reportMap) {
                            window.reportMap = L.map('reportMap').setView([position.coords.latitude, position.coords.longitude], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(window.reportMap);
                        } else {
                            window.reportMap.setView([position.coords.latitude, position.coords.longitude], 15);
                        }
                        
                        // Clear existing markers
                        window.reportMap.eachLayer(function(layer) {
                            if (layer instanceof L.Marker) {
                                window.reportMap.removeLayer(layer);
                            }
                        });
                        
                        // Add marker
                        L.marker([position.coords.latitude, position.coords.longitude], {
                            icon: L.divIcon({
                                html: '<i class="fas fa-map-marker-alt" style="color: var(--primary); font-size: 24px;"></i>'
                            })
                        }).addTo(window.reportMap)
                          .bindPopup('Report Location')
                          .openPopup();
                    },
                    function(error) {
                        alert('Unable to get your location for the report.');
                    }
                );
            }
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
        
        // Leave Request Form
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Validate dates
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (startDate < today) {
                alert('Start date cannot be in the past.');
                return;
            }
            
            if (endDate < startDate) {
                alert('End date cannot be before start date.');
                return;
            }
            
            fetch('submit_leave_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Leave request submitted successfully!');
                    closeLeaveModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error submitting leave request: ' + error);
            });
        });
        
        // Calculate days between dates
        function calculateDays() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (end < start) {
                    document.getElementById('totalDays').value = 'Invalid dates';
                    return;
                }
                
                // Calculate difference in days
                const timeDiff = end.getTime() - start.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // +1 to include both start and end days
                
                document.getElementById('totalDays').value = daysDiff + ' day(s)';
            }
        }
        
        // Add event listeners for date calculations
        document.getElementById('startDate').addEventListener('change', calculateDays);
        document.getElementById('endDate').addEventListener('change', calculateDays);
        
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
                    fetch('send_emergency_alert.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            volunteer_id: <?php echo $volunteer_id; ?>,
                            volunteer_name: "<?php echo addslashes($name); ?>"
                        })
                    })
                    .then(() => {
                        alert('Emergency alert sent! Your coordinator has been notified.');
                    });
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
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Volunteer dashboard loaded successfully');
            
            // Initialize date pickers
            flatpickr("#startDate", {
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        const endDatePicker = document.querySelector("#endDate")._flatpickr;
                        endDatePicker.set('minDate', dateStr);
                    }
                }
            });
            
            flatpickr("#endDate", {
                minDate: "today",
                dateFormat: "Y-m-d"
            });
            
            // Start location sharing automatically if checked in but not checked out
            const isCheckedIn = <?php echo isset($attendance_data) && !isset($attendance_data['check_out_time']) ? 'true' : 'false'; ?>;
            if (isCheckedIn) {
                setTimeout(() => {
                    if (confirm('Start sharing your location with admin while working?')) {
                        startLocationSharing();
                    }
                }, 2000);
            }
        });
    </script>
</body>
</html>

<?php 
// Close all statements
if(isset($today_stmt)) mysqli_stmt_close($today_stmt);
if(isset($attendance_stmt)) mysqli_stmt_close($attendance_stmt);
if(isset($leave_stmt)) mysqli_stmt_close($leave_stmt);
if(isset($reports_stmt)) mysqli_stmt_close($reports_stmt);
if(isset($stats_stmt)) mysqli_stmt_close($stats_stmt);
if(isset($rank_stmt)) mysqli_stmt_close($rank_stmt);
if(isset($notifications_stmt)) mysqli_stmt_close($notifications_stmt);
if(isset($location_stmt)) mysqli_stmt_close($location_stmt);
if(isset($leave_history_stmt)) mysqli_stmt_close($leave_history_stmt);

// Close connection
mysqli_close($conn); 
?>