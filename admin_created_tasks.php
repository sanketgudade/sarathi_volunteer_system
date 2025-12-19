<?php
require_once 'config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];

// Get all tasks created by this admin
$sql = "SELECT t.*, v.full_name as volunteer_name, 
               tp.status as proof_status,
               COUNT(tp.id) as proof_count
        FROM tasks t
        LEFT JOIN volunteers v ON t.assigned_to = v.id
        LEFT JOIN task_proofs tp ON t.id = tp.task_id
        WHERE t.admin_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$tasks = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Created Tasks - Sarathi Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Use the same styles from admin_panel.php */
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
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .task-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .task-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .task-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .task-location {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .task-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .task-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: var(--primary); }
        .status-in_progress { background: #f3e8ff; color: var(--accent); }
        .status-under_review { background: #fce7f3; color: #be185d; }
        .status-completed { background: #dcfce7; color: var(--secondary); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-dark);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .map-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .map-link:hover {
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin: 0; color: var(--text-dark);">
                    <i class="fas fa-list-check"></i> Created Tasks
                </h1>
                <p style="margin: 5px 0 0 0; color: var(--text-light);">
                    View all tasks created by you
                </p>
            </div>
            <a href="admin_panel.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if(mysqli_num_rows($tasks) > 0): ?>
            <div class="task-grid">
                <?php while($task = mysqli_fetch_assoc($tasks)): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                <span class="task-status status-<?php echo str_replace(' ', '_', $task['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </div>
                            <?php if($task['points_awarded']): ?>
                                <div style="font-weight: 700; color: var(--warning);">
                                    <?php echo $task['points_awarded']; ?> pts
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="task-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($task['location_name']); ?>
                            <?php if($task['location_lat']): ?>
                                <a href="https://www.openstreetmap.org/?mlat=<?php echo $task['location_lat']; ?>&mlon=<?php echo $task['location_lng']; ?>&zoom=15" 
                                   target="_blank" class="map-link">
                                    (View on Map)
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="task-meta">
                            <div>
                                <i class="fas fa-user"></i>
                                <?php if($task['volunteer_name']): ?>
                                    <?php echo htmlspecialchars($task['volunteer_name']); ?>
                                <?php else: ?>
                                    Not assigned
                                <?php endif; ?>
                            </div>
                            <div>
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($task['deadline'])); ?>
                            </div>
                        </div>
                        
                        <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 15px;">
                            <?php echo substr(htmlspecialchars($task['description']), 0, 150); ?>...
                        </p>
                        
                        <div class="task-actions">
                            <?php if($task['proof_status'] === 'pending'): ?>
                                <button class="btn btn-success" onclick="reviewProof(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-check-circle"></i> Review Proof
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline" onclick="viewTaskDetails(<?php echo $task['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            
                            <?php if($task['location_lat']): ?>
                                <a href="admin_track.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-location-dot"></i> Track
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <h3>No Tasks Created Yet</h3>
                <p>You haven't created any tasks yet. Create your first task to get started.</p>
                <a href="admin_panel.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Create First Task
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function reviewProof(taskId) {
            window.location.href = 'admin_review_proof.php?task_id=' + taskId;
        }
        
        function viewTaskDetails(taskId) {
            window.location.href = 'admin_task_details.php?id=' + taskId;
        }
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>