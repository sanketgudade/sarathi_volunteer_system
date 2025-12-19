<?php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$org_name = $_SESSION['org_name'] ?? 'Sarathi Admin';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'approve_volunteer':
            $id = intval($_GET['id']);
            approveVolunteer($conn, $admin_id, $id);
            break;
            
        case 'reject_volunteer':
            $id = intval($_GET['id']);
            rejectVolunteer($conn, $admin_id, $id);
            break;
            
        case 'approve_leave':
            $id = intval($_GET['id']);
            approveLeave($conn, $admin_id, $id);
            break;
            
        case 'reject_leave':
            $id = intval($_GET['id']);
            rejectLeave($conn, $admin_id, $id);
            break;
            
        case 'create_task':
            createTask($conn, $admin_id);
            break;
            
        case 'update_task_status':
            updateTaskStatus($conn, $admin_id);
            break;
            
        case 'assign_task':
            assignTask($conn, $admin_id);
            break;
            
        case 'send_notification':
            sendNotification($conn, $admin_id);
            break;
            
        case 'update_alert_status':
            updateAlertStatus($conn, $admin_id);
            break;
            
        case 'view_volunteer':
            $id = intval($_GET['id']);
            viewVolunteerDetails($conn, $id);
            exit();
            
        case 'view_task':
            $id = intval($_GET['id']);
            viewTaskDetails($conn, $id);
            exit();
    }
}

// Functions for different actions (keep all your existing functions)
function approveVolunteer($conn, $admin_id, $volunteer_id) {
    $invite_code = 'SAR' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $sql = "UPDATE volunteers SET status = 'active', invite_code = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $invite_code, $volunteer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'volunteer_approve', "Approved volunteer ID: $volunteer_id");
        $_SESSION['success'] = "Volunteer approved successfully! Invite Code: {$invite_code}";
    } else {
        $_SESSION['error'] = "Failed to approve volunteer: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function rejectVolunteer($conn, $admin_id, $volunteer_id) {
    $sql = "UPDATE volunteers SET status = 'inactive' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'volunteer_reject', "Rejected volunteer ID: $volunteer_id");
        $_SESSION['success'] = "Volunteer request rejected";
    } else {
        $_SESSION['error'] = "Failed to reject volunteer: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function approveLeave($conn, $admin_id, $leave_id) {
    $sql = "UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $admin_id, $leave_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'leave_approve', "Approved leave request ID: $leave_id");
        $_SESSION['success'] = "Leave request approved";
    } else {
        $_SESSION['error'] = "Failed to approve leave: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function rejectLeave($conn, $admin_id, $leave_id) {
    $sql = "UPDATE leave_requests SET status = 'rejected', rejected_by = ?, rejected_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $admin_id, $leave_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'leave_reject', "Rejected leave request ID: $leave_id");
        $_SESSION['success'] = "Leave request rejected";
    } else {
        $_SESSION['error'] = "Failed to reject leave: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function createTask($conn, $admin_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Invalid request method";
        header("Location: admin_panel.php");
        exit();
    }
    
    $title = mysqli_real_escape_string($conn, $_POST['task_title']);
    $description = mysqli_real_escape_string($conn, $_POST['task_description']);
    $location = mysqli_real_escape_string($conn, $_POST['task_location']);
    $category = mysqli_real_escape_string($conn, $_POST['task_category']);
    $urgency = mysqli_real_escape_string($conn, $_POST['task_urgency']);
    $priority = mysqli_real_escape_string($conn, $_POST['task_priority']);
    $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : NULL;
    $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
    
    $status = $assigned_to ? 'assigned' : 'pending';
    
    $sql = "INSERT INTO tasks (title, description, location_name, assigned_to, deadline, status, admin_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssisssi", 
        $title, $description, $location, $assigned_to, $deadline, $status, $admin_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $task_id = mysqli_insert_id($conn);
        adminLogActivity($conn, $admin_id, 'task_create', "Created task ID: $task_id - $title");
        $_SESSION['success'] = "Task created successfully" . ($assigned_to ? " and assigned" : "");
    } else {
        $_SESSION['error'] = "Failed to create task: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function updateTaskStatus($conn, $admin_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Invalid request method";
        header("Location: admin_panel.php");
        exit();
    }
    
    $task_id = intval($_POST['task_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
    
    $sql = "UPDATE tasks SET status = ?, admin_notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssii", $status, $notes, $admin_id, $task_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'task_status_update', "Updated task ID: $task_id status to $status");
        $_SESSION['success'] = "Task status updated";
    } else {
        $_SESSION['error'] = "Failed to update task: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function assignTask($conn, $admin_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Invalid request method";
        header("Location: admin_panel.php");
        exit();
    }
    
    $task_id = intval($_POST['task_id']);
    $assigned_to = intval($_POST['assigned_to']);
    
    $sql = "UPDATE tasks SET assigned_to = ?, assigned_by = ?, assigned_at = NOW(), status = 'assigned' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $assigned_to, $admin_id, $task_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'task_assign', "Assigned task ID: $task_id to volunteer ID: $assigned_to");
        $_SESSION['success'] = "Task assigned successfully";
    } else {
        $_SESSION['error'] = "Failed to assign task: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function sendNotification($conn, $admin_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Invalid request method";
        header("Location: admin_panel.php");
        exit();
    }
    
    $title = mysqli_real_escape_string($conn, $_POST['notification_title']);
    $message = mysqli_real_escape_string($conn, $_POST['notification_message']);
    $type = mysqli_real_escape_string($conn, $_POST['notification_type']);
    $target = mysqli_real_escape_string($conn, $_POST['notification_target']);
    
    $sql = "INSERT INTO notifications (title, message, type, target, created_by, status) 
            VALUES (?, ?, ?, ?, ?, 'sent')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $title, $message, $type, $target, $admin_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'notification_send', "Sent notification: $title");
        $_SESSION['success'] = "Notification sent successfully";
    } else {
        $_SESSION['error'] = "Failed to send notification: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function updateAlertStatus($conn, $admin_id) {
    $alert_id = intval($_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    $sql = "UPDATE alerts SET status = ?, handled_by = ?, handled_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $status, $admin_id, $alert_id);
    
    if (mysqli_stmt_execute($stmt)) {
        adminLogActivity($conn, $admin_id, 'alert_resolve', "Resolved alert ID: $alert_id");
        $_SESSION['success'] = "Alert status updated";
    } else {
        $_SESSION['error'] = "Failed to update alert: " . mysqli_error($conn);
    }
    header("Location: admin_panel.php");
    exit();
}

function viewVolunteerDetails($conn, $volunteer_id) {
    header('Content-Type: application/json');
    
    $sql = "SELECT v.*, 
                   COUNT(DISTINCT t.id) as total_tasks,
                   COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
                   COUNT(DISTINCT lr.id) as total_leaves
            FROM volunteers v
            LEFT JOIN tasks t ON v.id = t.assigned_to
            LEFT JOIN leave_requests lr ON v.id = lr.volunteer_id
            WHERE v.id = ?
            GROUP BY v.id";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $volunteer = mysqli_fetch_assoc($result);
    
    if ($volunteer) {
        $tasks_sql = "SELECT * FROM tasks WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 5";
        $tasks_stmt = mysqli_prepare($conn, $tasks_sql);
        mysqli_stmt_bind_param($tasks_stmt, "i", $volunteer_id);
        mysqli_stmt_execute($tasks_stmt);
        $volunteer['recent_tasks'] = mysqli_fetch_all(mysqli_stmt_get_result($tasks_stmt), MYSQLI_ASSOC);
        
        $checkins_sql = "SELECT * FROM volunteer_checkins WHERE volunteer_id = ? ORDER BY checkin_time DESC LIMIT 5";
        $checkins_stmt = mysqli_prepare($conn, $checkins_sql);
        mysqli_stmt_bind_param($checkins_stmt, "i", $volunteer_id);
        mysqli_stmt_execute($checkins_stmt);
        $volunteer['recent_checkins'] = mysqli_fetch_all(mysqli_stmt_get_result($checkins_stmt), MYSQLI_ASSOC);
    }
    
    echo json_encode($volunteer);
    exit();
}

function viewTaskDetails($conn, $task_id) {
    header('Content-Type: application/json');
    
    $sql = "SELECT t.*, v.full_name as volunteer_name, v.email as volunteer_email,
                   a.username as created_by_name
            FROM tasks t
            LEFT JOIN volunteers v ON t.assigned_to = v.id
            LEFT JOIN admins a ON t.created_by = a.id
            WHERE t.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $task = mysqli_fetch_assoc($result);
    
    if ($task) {
        $reports_sql = "SELECT * FROM task_reports WHERE task_id = ?";
        $reports_stmt = mysqli_prepare($conn, $reports_sql);
        mysqli_stmt_bind_param($reports_stmt, "i", $task_id);
        mysqli_stmt_execute($reports_stmt);
        $task['reports'] = mysqli_fetch_all(mysqli_stmt_get_result($reports_stmt), MYSQLI_ASSOC);
    }
    
    echo json_encode($task);
    exit();
}

// Renamed function to avoid conflict with config.php
if (!function_exists('adminLogActivity')) {
    function adminLogActivity($conn, $admin_id, $action, $description) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $sql = "INSERT INTO activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
                VALUES ('admin', ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issss", $admin_id, $action, $description, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
    }
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM volunteers WHERE status = 'pending') as pending_volunteers,
    (SELECT COUNT(*) FROM volunteers WHERE status = 'active') as active_volunteers,
    (SELECT COUNT(*) FROM volunteers WHERE status = 'active') as on_duty_volunteers,
    (SELECT COUNT(*) FROM tasks WHERE status = 'pending') as pending_tasks,
    (SELECT COUNT(*) FROM tasks WHERE status IN ('assigned', 'in_progress')) as active_tasks,
    (SELECT COUNT(*) FROM tasks WHERE status = 'completed') as completed_tasks,
    (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') as pending_leaves,
    (SELECT COUNT(*) FROM alerts WHERE status = 'active') as active_alerts,
    (SELECT COUNT(*) FROM alerts WHERE status = 'critical') as critical_alerts,
    (SELECT COUNT(*) FROM volunteer_checkins WHERE DATE(checkin_time) = CURDATE()) as today_checkins";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

if (!isset($stats['avg_rating'])) {
    $stats['avg_rating'] = 'N/A';
}

// Get pending volunteers
$pending_volunteers_sql = "SELECT * FROM volunteers WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5";
$pending_volunteers_result = mysqli_query($conn, $pending_volunteers_sql);

// Get active tasks
$active_tasks_sql = "SELECT t.*, v.full_name as volunteer_name 
                    FROM tasks t 
                    LEFT JOIN volunteers v ON t.assigned_to = v.id 
                    WHERE t.status = 'in_progress' 
                    ORDER BY t.created_at DESC 
                    LIMIT 10";
$active_tasks_result = mysqli_query($conn, $active_tasks_sql);

// Get pending leaves
$pending_leaves_sql = "SELECT lr.*, v.full_name as volunteer_name, v.email as volunteer_email 
                      FROM leave_requests lr 
                      JOIN volunteers v ON lr.volunteer_id = v.id 
                      WHERE lr.status = 'pending' 
                      ORDER BY lr.created_at DESC 
                      LIMIT 10";
$pending_leaves_result = mysqli_query($conn, $pending_leaves_sql);

// Get active alerts
$alerts_sql = "SELECT a.*, v.full_name as volunteer_name 
               FROM alerts a 
               LEFT JOIN volunteers v ON a.volunteer_id = v.id 
               WHERE a.status IN ('active', 'critical') 
               ORDER BY 
                   CASE a.severity 
                       WHEN 'critical' THEN 1 
                       WHEN 'high' THEN 2 
                       WHEN 'medium' THEN 3 
                       WHEN 'low' THEN 4 
                       ELSE 5 
                   END,
                   a.created_at DESC 
               LIMIT 10";
$alerts_result = mysqli_query($conn, $alerts_sql);

// Get recent checkins
$checkins_sql = "SELECT c.*, v.full_name, v.email 
                FROM volunteer_checkins c 
                JOIN volunteers v ON c.volunteer_id = v.id 
                WHERE DATE(c.checkin_time) = CURDATE() 
                ORDER BY c.checkin_time DESC 
                LIMIT 10";
$checkins_result = mysqli_query($conn, $checkins_sql);

// Get volunteers for assignment
$volunteers_sql = "SELECT id, full_name, email FROM volunteers WHERE status = 'active'";
$volunteers_result = mysqli_query($conn, $volunteers_sql);
$volunteers = [];
while($row = mysqli_fetch_assoc($volunteers_result)) {
    $volunteers[] = $row;
}

// Get leaderboard data
$leaderboard_sql = "SELECT v.id, v.full_name, v.email, 
                   COUNT(DISTINCT t.id) as total_tasks,
                   COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks
                   FROM volunteers v 
                   LEFT JOIN tasks t ON v.id = t.assigned_to 
                   WHERE v.status = 'active' 
                   GROUP BY v.id 
                   ORDER BY completed_tasks DESC 
                   LIMIT 10";
$leaderboard_result = mysqli_query($conn, $leaderboard_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sarathi Field Management</title>
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

        /* ===== ADMIN HEADER ===== */
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

        .admin-user-info {
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

        .btn-info {
            background: var(--accent);
            color: white;
            border: 2px solid var(--accent);
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

        /* ===== MAIN LAYOUT ===== */
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 80px);
        }

        /* ===== SIDEBAR ===== */
        .admin-sidebar {
            background: white;
            border-right: 1px solid var(--border);
            padding: 30px 0;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 4px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar-nav a:hover {
            background: var(--light-bg);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .sidebar-nav a.active {
            background: var(--light-bg);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .sidebar-nav i {
            width: 20px;
            text-align: center;
        }

        /* ===== MAIN CONTENT ===== */
        .admin-content {
            padding: 30px;
            overflow-y: auto;
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d1fae5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
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

        .stat-trend {
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .stat-trend.up {
            color: var(--secondary);
        }

        .stat-trend.down {
            color: var(--danger);
        }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--border);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }

        .action-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }

        .action-card h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .action-card p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* ===== PANELS ===== */
        .panel {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== DATA TABLES ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            color: var(--text-light);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }

        .data-table tr:hover {
            background: var(--light-bg);
        }

        /* ===== STATUS BADGES ===== */
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
        .status-active { background: #dbeafe; color: var(--primary); }
        .status-completed { background: #dcfce7; color: var(--secondary); }
        .status-critical { background: #fee2e2; color: var(--danger); }
        .status-assigned { background: #f3e8ff; color: var(--accent); }
        .status-inactive { background: #f1f5f9; color: var(--text-light); }

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
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ===== TASK CARDS ===== */
        .task-list {
            display: grid;
            gap: 16px;
        }

        .task-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .task-card.critical {
            border-left: 4px solid var(--danger);
        }

        .task-card.high {
            border-left: 4px solid var(--warning);
        }

        .task-card.medium {
            border-left: 4px solid var(--primary);
        }

        .task-card.low {
            border-left: 4px solid var(--secondary);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .task-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .task-description {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        .task-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .task-meta i {
            width: 16px;
        }

        .task-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        /* ===== VOLUNTEER CARDS ===== */
        .volunteer-list {
            display: grid;
            gap: 16px;
        }

        .volunteer-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .volunteer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .volunteer-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .volunteer-email {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .volunteer-info {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .volunteer-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        /* ===== ALERT ITEMS ===== */
        .alert-list {
            display: grid;
            gap: 12px;
        }

        .alert-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .alert-item:hover {
            transform: translateX(4px);
        }

        .alert-item.critical {
            border-left: 4px solid var(--danger);
        }

        .alert-item.high {
            border-left: 4px solid var(--warning);
        }

        .alert-item.medium {
            border-left: 4px solid var(--primary);
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            background: var(--danger);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .alert-item.high .alert-icon {
            background: var(--warning);
        }

        .alert-item.medium .alert-icon {
            background: var(--primary);
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .alert-description {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* ===== LEADERBOARD ===== */
        .leaderboard {
            display: grid;
            gap: 12px;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover {
            transform: translateX(4px);
        }

        .rank-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .leaderboard-info {
            flex: 1;
        }

        .leaderboard-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .leaderboard-email {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 4px;
        }

        .leaderboard-stats {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
        }

        .stat-completed {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 992px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .admin-content {
                padding: 20px;
            }
            
            .admin-user-info {
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }
        }

        @media (max-width: 576px) {
            .panel {
                padding: 20px;
            }
            
            .task-meta {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .task-actions {
                flex-wrap: wrap;
            }
            
            .volunteer-actions {
                flex-wrap: wrap;
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
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container header-content">
            <a href="admin_panel.php" class="admin-logo">
                <div class="admin-logo-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="admin-logo-text">
                    Sarathi <span>Admin</span>
                </div>
            </a>

            <div class="admin-user-info">
                <div class="user-badge">
                    <i class="fas fa-user"></i>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($admin_name); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-light);"><?php echo htmlspecialchars($org_name); ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="admin-container">
        <!-- Sidebar -->
       <nav class="admin-sidebar">
    <ul class="sidebar-nav">
        <li><a href="admin_panel.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a></li>
        <li><a href="admin_tasks.php">
            <i class="fas fa-tasks"></i>
            Task Management
        </a></li>
        <li><a href="admin_created_tasks.php">
            <i class="fas fa-list-check"></i>
            Created Tasks
        </a></li>
        <li><a href="admin_volunteers.php">
            <i class="fas fa-users"></i>
            Volunteers
        </a></li>
        <li><a href="admin_attendance.php">
            <i class="fas fa-clipboard-check"></i>
            Attendance
        </a></li>
        <li><a href="admin_track.php">
            <i class="fas fa-location-dot"></i>
            Track Volunteers
        </a></li>
        <li><a href="admin_leaves.php">
            <i class="fas fa-calendar-alt"></i>
            Leave Management
        </a></li>
        <li><a href="admin_society_problems.php">
            <i class="fas fa-exclamation-circle"></i>
            Society Problems
        </a></li>
        <li><a href="admin_reports.php">
            <i class="fas fa-chart-bar"></i>
            Reports
        </a></li>
        <li><a href="admin_rankings.php">
            <i class="fas fa-trophy"></i>
            Rankings
        </a></li>
        <li><a href="admin_notifications.php">
            <i class="fas fa-bullhorn"></i>
            Notifications
        </a></li>
        <li><a href="admin_alerts.php">
            <i class="fas fa-exclamation-triangle"></i>
            Alerts
        </a></li>
    </ul>
</nav>
        <!-- Main Content -->
        <div class="admin-content">
            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions mb-4">
                <div class="action-card" onclick="openCreateTaskModal()">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3>Create Task</h3>
                    <p>Assign new task to volunteer</p>
                </div>
                
                <div class="action-card" onclick="openSendNotificationModal()">
                    <div class="action-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Send Alert</h3>
                    <p>Broadcast notification</p>
                </div>
                
                <div class="action-card" onclick="window.location.href='admin_volunteers.php'">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add Volunteer</h3>
                    <p>Register new volunteer</p>
                </div>
                
                <div class="action-card" onclick="window.location.href='admin_society_problems.php'">
                    <div class="action-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3>View Problems</h3>
                    <p>Check society issues</p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['active_volunteers'] ?: 0; ?></div>
                    <div class="stat-label">Active Volunteers</div>
                    <div class="stat-trend">
                        <?php echo $stats['pending_volunteers'] ?: 0; ?> pending approval
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['active_tasks'] ?: 0; ?></div>
                    <div class="stat-label">Active Tasks</div>
                    <div class="stat-trend">
                        <?php echo $stats['completed_tasks'] ?: 0; ?> completed
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['critical_alerts'] ?: 0; ?></div>
                    <div class="stat-label">Critical Alerts</div>
                    <div class="stat-trend">
                        <?php echo $stats['active_alerts'] ?: 0; ?> total active
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['today_checkins'] ?: 0; ?></div>
                    <div class="stat-label">Today's Check-ins</div>
                    <div class="stat-trend">
                        <?php echo $stats['pending_leaves'] ?: 0; ?> pending leaves
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
                <!-- Active Tasks -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-running"></i>
                            Active Tasks
                        </div>
                        <span class="status-badge status-active"><?php echo $stats['active_tasks'] ?: 0; ?> Active</span>
                    </div>
                    
                    <?php if(mysqli_num_rows($active_tasks_result) > 0): ?>
                        <div class="task-list">
                            <?php while($task = mysqli_fetch_assoc($active_tasks_result)): ?>
                                <div class="task-card <?php echo strtolower($task['priority']); ?>">
                                    <div class="task-header">
                                        <div>
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="status-badge status-<?php echo $task['status']; ?>">
                                                <?php echo $task['status']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="task-description">
                                        <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?>...
                                    </div>
                                    <div class="task-meta">
                                        <?php if($task['volunteer_name']): ?>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['volunteer_name']); ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($task['location_name']); ?></span>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm btn-outline" onclick="viewTaskDetails(<?php echo $task['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if(!$task['assigned_to']): ?>
                                            <button class="btn btn-sm btn-primary" onclick="openAssignTaskModal(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-user-edit"></i> Assign
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px;"></i>
                            <p>No active tasks</p>
                            <button class="btn btn-primary mt-3" onclick="openCreateTaskModal()">
                                <i class="fas fa-plus"></i> Create First Task
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pending Volunteers -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-user-clock"></i>
                            Pending Volunteer Approvals
                        </div>
                        <span class="status-badge status-pending"><?php echo $stats['pending_volunteers'] ?: 0; ?> Pending</span>
                    </div>
                    
                    <?php if(mysqli_num_rows($pending_volunteers_result) > 0): ?>
                        <div class="volunteer-list">
                            <?php while($volunteer = mysqli_fetch_assoc($pending_volunteers_result)): ?>
                                <div class="volunteer-card">
                                    <div class="volunteer-header">
                                        <div>
                                            <div class="volunteer-name"><?php echo htmlspecialchars($volunteer['full_name']); ?></div>
                                            <div class="volunteer-email"><?php echo htmlspecialchars($volunteer['email']); ?></div>
                                        </div>
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                    
                                    <div class="volunteer-info">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer['mobile_number']); ?>
                                    </div>
                                    
                                    <?php if($volunteer['education']): ?>
                                        <div class="volunteer-info">
                                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($volunteer['education']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($volunteer['ngo_name']): ?>
                                        <div class="volunteer-info">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($volunteer['ngo_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="volunteer-actions">
                                        <button class="btn btn-sm btn-success" onclick="approveVolunteer(<?php echo $volunteer['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectVolunteer(<?php echo $volunteer['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <button class="btn btn-sm btn-outline" onclick="viewVolunteerDetails(<?php echo $volunteer['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 16px;"></i>
                            <p>No pending volunteer approvals</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Leaves -->
            <?php if(mysqli_num_rows($pending_leaves_result) > 0): ?>
            <div class="panel mb-4">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-calendar-times"></i>
                        Pending Leave Requests
                    </div>
                    <span class="status-badge status-pending"><?php echo $stats['pending_leaves'] ?: 0; ?> Pending</span>
                </div>
                
                <div class="volunteer-list">
                    <?php while($leave = mysqli_fetch_assoc($pending_leaves_result)): ?>
                        <div class="volunteer-card">
                            <div class="volunteer-header">
                                <div>
                                    <div class="volunteer-name"><?php echo htmlspecialchars($leave['volunteer_name']); ?></div>
                                    <div class="volunteer-email"><?php echo htmlspecialchars($leave['volunteer_email']); ?></div>
                                </div>
                                <span class="status-badge status-pending">Pending</span>
                            </div>
                            
                            <div style="background: #fef3c7; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-size: 0.9rem; color: #d97706; font-weight: 600;">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('d M Y', strtotime($leave['start_date'])); ?> 
                                            - 
                                            <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #92400e; margin-top: 4px;">
                                            Type: <?php echo htmlspecialchars($leave['leave_type']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 0.9rem; color: var(--text-dark); font-weight: 600;">
                                            <?php 
                                            $start = new DateTime($leave['start_date']);
                                            $end = new DateTime($leave['end_date']);
                                            $interval = $start->diff($end);
                                            echo ($interval->days + 1) . ' days';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="volunteer-info">
                                <?php echo htmlspecialchars(substr($leave['reason'], 0, 100)); ?>...
                            </div>
                            
                            <div class="volunteer-actions mt-3">
                                <button class="btn btn-sm btn-success" onclick="approveLeave(<?php echo $leave['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectLeave(<?php echo $leave['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Alerts Panel -->
            <?php if(mysqli_num_rows($alerts_result) > 0): ?>
            <div class="panel mb-4">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-exclamation-circle"></i>
                        Critical Alerts
                    </div>
                    <span class="status-badge status-critical">
                        <?php echo $stats['critical_alerts'] ?: 0; ?> Critical
                    </span>
                </div>
                
                <div class="alert-list">
                    <?php while($alert = mysqli_fetch_assoc($alerts_result)): ?>
                        <div class="alert-item <?php echo strtolower($alert['severity']); ?>">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                                <div class="alert-description">
                                    <?php echo htmlspecialchars($alert['description']); ?>
                                    <?php if($alert['volunteer_name']): ?>
                                        • Volunteer: <?php echo htmlspecialchars($alert['volunteer_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-primary" onclick="handleAlert(<?php echo $alert['id']; ?>, 'resolved')">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Leaderboard -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-trophy"></i>
                        Volunteer Leaderboard
                    </div>
                    <span class="status-badge status-active">Top Performers</span>
                </div>
                
                <?php if(mysqli_num_rows($leaderboard_result) > 0): ?>
                    <div class="leaderboard">
                        <?php $rank = 1; ?>
                        <?php while($volunteer = mysqli_fetch_assoc($leaderboard_result)): ?>
                            <div class="leaderboard-item">
                                <div class="rank-number">#<?php echo $rank; ?></div>
                                <div class="leaderboard-info">
                                    <div class="leaderboard-name"><?php echo htmlspecialchars($volunteer['full_name']); ?></div>
                                    <div class="leaderboard-email"><?php echo htmlspecialchars($volunteer['email']); ?></div>
                                    <div class="leaderboard-stats">
                                        <div class="stat-completed"><?php echo $volunteer['completed_tasks'] ?: 0; ?> tasks</div>
                                        <div style="font-size: 0.85rem; color: var(--text-light);">
                                            <?php echo $volunteer['total_tasks'] ?: 0; ?> total assigned
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $rank++; ?>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <i class="fas fa-trophy" style="font-size: 3rem; margin-bottom: 16px;"></i>
                        <p>No leaderboard data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal" id="createTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Create New Task</h2>
                <button class="close-modal" onclick="closeCreateTaskModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createTaskForm" method="POST" action="admin_panel.php?action=create_task">
                    <div class="form-group">
                        <label class="form-label">Task Title</label>
                        <input type="text" name="task_title" class="form-control" required placeholder="Enter task title">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="task_description" class="form-control" rows="3" required placeholder="Describe the task"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" name="task_location" class="form-control" required placeholder="Enter location or address">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="task_category" class="form-control" required>
                                <option value="maintenance">Maintenance</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="inspection">Inspection</option>
                                <option value="repair">Repair</option>
                                <option value="delivery">Delivery</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Urgency</label>
                            <select name="task_urgency" class="form-control" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="task_priority" class="form-control" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high" selected>High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deadline</label>
                            <input type="datetime-local" name="deadline" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assign to Volunteer (Optional)</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Not Assigned --</option>
                            <?php foreach($volunteers as $volunteer): ?>
                                <option value="<?php echo $volunteer['id']; ?>">
                                    <?php echo htmlspecialchars($volunteer['full_name']); ?> 
                                    (<?php echo htmlspecialchars($volunteer['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-plus-circle"></i> Create Task
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeCreateTaskModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Notification Modal -->
    <div class="modal" id="sendNotificationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-bullhorn"></i> Send Notification</h2>
                <button class="close-modal" onclick="closeSendNotificationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="notificationForm" method="POST" action="admin_panel.php?action=send_notification">
                    <div class="form-group">
                        <label class="form-label">Notification Title</label>
                        <input type="text" name="notification_title" class="form-control" required placeholder="Enter notification title">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="notification_message" class="form-control" rows="4" required placeholder="Enter notification message"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select name="notification_type" class="form-control" required>
                                <option value="alert">Alert</option>
                                <option value="info">Information</option>
                                <option value="warning">Warning</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Target</label>
                            <select name="notification_target" class="form-control" required>
                                <option value="all">All Volunteers</option>
                                <option value="active">Active Volunteers Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-paper-plane"></i> Send Notification
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeSendNotificationModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Task Modal -->
    <div class="modal" id="assignTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Assign Task</h2>
                <button class="close-modal" onclick="closeAssignTaskModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignTaskForm" method="POST" action="admin_panel.php?action=assign_task">
                    <input type="hidden" name="task_id" id="assignTaskId">
                    
                    <div class="form-group">
                        <label class="form-label">Select Volunteer</label>
                        <select name="assigned_to" class="form-control" required>
                            <option value="">-- Select Volunteer --</option>
                            <?php foreach($volunteers as $volunteer): ?>
                                <option value="<?php echo $volunteer['id']; ?>">
                                    <?php echo htmlspecialchars($volunteer['full_name']); ?> 
                                    (<?php echo htmlspecialchars($volunteer['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-user-check"></i> Assign Task
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeAssignTaskModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openCreateTaskModal() {
            document.getElementById('createTaskModal').classList.add('active');
            // Set default deadline to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const deadlineInput = document.querySelector('input[name="deadline"]');
            if (deadlineInput) {
                deadlineInput.value = tomorrow.toISOString().slice(0, 16);
            }
        }
        
        function closeCreateTaskModal() {
            document.getElementById('createTaskModal').classList.remove('active');
        }
        
        function openSendNotificationModal() {
            document.getElementById('sendNotificationModal').classList.add('active');
        }
        
        function closeSendNotificationModal() {
            document.getElementById('sendNotificationModal').classList.remove('active');
        }
        
        function openAssignTaskModal(taskId) {
            document.getElementById('assignTaskId').value = taskId;
            document.getElementById('assignTaskModal').classList.add('active');
        }
        
        function closeAssignTaskModal() {
            document.getElementById('assignTaskModal').classList.remove('active');
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
        
        // Action Functions
        function approveVolunteer(volunteerId) {
            if (confirm('Are you sure you want to approve this volunteer?')) {
                window.location.href = `admin_panel.php?action=approve_volunteer&id=${volunteerId}`;
            }
        }
        
        function rejectVolunteer(volunteerId) {
            if (confirm('Are you sure you want to reject this volunteer?')) {
                window.location.href = `admin_panel.php?action=reject_volunteer&id=${volunteerId}`;
            }
        }
        
        function approveLeave(leaveId) {
            if (confirm('Are you sure you want to approve this leave request?')) {
                window.location.href = `admin_panel.php?action=approve_leave&id=${leaveId}`;
            }
        }
        
        function rejectLeave(leaveId) {
            if (confirm('Are you sure you want to reject this leave request?')) {
                window.location.href = `admin_panel.php?action=reject_leave&id=${leaveId}`;
            }
        }
        
        function viewVolunteerDetails(volunteerId) {
            fetch(`admin_panel.php?action=view_volunteer&id=${volunteerId}`)
                .then(response => response.json())
                .then(data => {
                    let message = `Volunteer Details:\nName: ${data.full_name}\nEmail: ${data.email}\nMobile: ${data.mobile_number}\nStatus: ${data.status}`;
                    if (data.ngo_name) message += `\nNGO: ${data.ngo_name}`;
                    if (data.education) message += `\nEducation: ${data.education}`;
                    if (data.total_tasks) message += `\nTotal Tasks: ${data.total_tasks}`;
                    if (data.completed_tasks) message += `\nCompleted Tasks: ${data.completed_tasks}`;
                    alert(message);
                });
        }
        
        function viewTaskDetails(taskId) {
            fetch(`admin_panel.php?action=view_task&id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    let message = `Task Details:\nTitle: ${data.title}\nDescription: ${data.description}\nLocation: ${data.location}\nCategory: ${data.category}\nUrgency: ${data.urgency}\nPriority: ${data.priority}\nStatus: ${data.status}`;
                    if (data.volunteer_name) {
                        message += `\nAssigned to: ${data.volunteer_name} (${data.volunteer_email})`;
                    }
                    if (data.deadline) {
                        message += `\nDeadline: ${new Date(data.deadline).toLocaleString()}`;
                    }
                    alert(message);
                });
        }
        
        function handleAlert(alertId, action) {
            if (confirm(`Are you sure you want to mark this alert as ${action}?`)) {
                window.location.href = `admin_panel.php?action=update_alert_status&id=${alertId}&status=${action}`;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin panel loaded successfully');
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>