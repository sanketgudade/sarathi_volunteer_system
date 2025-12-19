<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'view':
            $problem_id = intval($_GET['id']);
            viewProblemDetails($conn, $problem_id);
            exit();
            
        case 'assign':
            $problem_id = intval($_GET['id']);
            assignProblem($conn, $admin_id, $problem_id);
            break;
            
        case 'update_status':
            $problem_id = intval($_GET['id']);
            $status = mysqli_real_escape_string($conn, $_GET['status']);
            updateProblemStatus($conn, $admin_id, $problem_id, $status);
            break;
    }
}

function viewProblemDetails($conn, $problem_id) {
    header('Content-Type: application/json');
    
    $sql = "SELECT sp.*, 
                   v.full_name as volunteer_name,
                   a.username as admin_name
            FROM society_problems sp
            LEFT JOIN volunteers v ON sp.assigned_to_volunteer = v.id
            LEFT JOIN admins a ON sp.assigned_by_admin = a.id
            WHERE sp.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $problem_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $problem = mysqli_fetch_assoc($result);
    
    if ($problem) {
        // Decode images JSON
        $problem['images'] = json_decode($problem['images'] ?? '[]', true);
        echo json_encode($problem);
    } else {
        echo json_encode(['error' => 'Problem not found']);
    }
    exit();
}

function assignProblem($conn, $admin_id, $problem_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $volunteer_id = intval($_POST['volunteer_id']);
        $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes']);
        $est_date = mysqli_real_escape_string($conn, $_POST['estimated_date']);
        
        // Update problem
        $sql = "UPDATE society_problems 
                SET assigned_to_volunteer = ?, 
                    assigned_by_admin = ?,
                    admin_notes = ?,
                    estimated_resolution_date = ?,
                    status = 'assigned',
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iissi", $volunteer_id, $admin_id, $admin_notes, $est_date, $problem_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Problem assigned to volunteer successfully!";
        } else {
            $_SESSION['error'] = "Failed to assign problem: " . mysqli_error($conn);
        }
    }
    header("Location: admin_society_problems.php");
    exit();
}

function updateProblemStatus($conn, $admin_id, $problem_id, $status) {
    $sql = "UPDATE society_problems 
            SET status = ?, 
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $problem_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Problem status updated to " . ucfirst($status);
    } else {
        $_SESSION['error'] = "Failed to update status: " . mysqli_error($conn);
    }
    header("Location: admin_society_problems.php");
    exit();
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM society_problems WHERE status = 'reported') as reported,
    (SELECT COUNT(*) FROM society_problems WHERE status = 'under_review') as under_review,
    (SELECT COUNT(*) FROM society_problems WHERE status = 'assigned') as assigned,
    (SELECT COUNT(*) FROM society_problems WHERE status = 'in_progress') as in_progress,
    (SELECT COUNT(*) FROM society_problems WHERE status = 'completed') as completed";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get problems
$problems_sql = "SELECT sp.*, v.full_name as volunteer_name 
                 FROM society_problems sp
                 LEFT JOIN volunteers v ON sp.assigned_to_volunteer = v.id
                 ORDER BY 
                   CASE urgency_level 
                     WHEN 'emergency' THEN 1
                     WHEN 'high' THEN 2
                     WHEN 'medium' THEN 3
                     WHEN 'low' THEN 4
                   END,
                   sp.created_at DESC";
$problems_result = mysqli_query($conn, $problems_sql);

// Get volunteers for assignment
$volunteers_sql = "SELECT id, full_name FROM volunteers WHERE status = 'active' ORDER BY full_name";
$volunteers_result = mysqli_query($conn, $volunteers_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Society Problems - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse your existing admin panel styles */
        /* Add new styles for problem management */
        
        .problem-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #f59e0b;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .problem-card.emergency { border-left-color: #ef4444; }
        .problem-card.high { border-left-color: #f59e0b; }
        .problem-card.medium { border-left-color: #3b82f6; }
        .problem-card.low { border-left-color: #06D6A0; }
        
        .urgency-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .urgency-emergency { background: #fee2e2; color: #dc2626; }
        .urgency-high { background: #fef3c7; color: #d97706; }
        .urgency-medium { background: #dbeafe; color: #1e40af; }
        .urgency-low { background: #d1fae5; color: #065f46; }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-reported { background: #fef3c7; color: #92400e; }
        .status-under_review { background: #dbeafe; color: #1e40af; }
        .status-assigned { background: #e0e7ff; color: #4f46e5; }
        .status-in_progress { background: #f0f9ff; color: #0ea5e9; }
        .status-completed { background: #d1fae5; color: #065f46; }
        
        .category-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        
        .image-gallery img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .image-gallery img:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Use your existing admin panel structure -->
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-exclamation-circle"></i> Society Problems Management</h1>
            <a href="admin_panel.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['reported']; ?></div>
                <div>Reported</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['under_review']; ?></div>
                <div>Under Review</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['assigned']; ?></div>
                <div>Assigned</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div>In Progress</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div>Completed</div>
            </div>
        </div>
        
        <!-- Problems List -->
        <div class="table-container">
            <h2>All Reported Problems</h2>
            
            <?php if(mysqli_num_rows($problems_result) > 0): ?>
                <?php while($problem = mysqli_fetch_assoc($problems_result)): ?>
                <div class="problem-card <?php echo $problem['urgency_level']; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($problem['problem_title']); ?></h3>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                <span class="urgency-badge urgency-<?php echo $problem['urgency_level']; ?>">
                                    <?php echo ucfirst($problem['urgency_level']); ?> Priority
                                </span>
                                <span class="status-badge status-<?php echo $problem['status']; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($problem['status'])); ?>
                                </span>
                                <span class="category-badge">
                                    <i class="fas fa-tag"></i> <?php echo ucfirst($problem['problem_category']); ?>
                                </span>
                            </div>
                            <p style="color: #666; margin-bottom: 10px;">
                                <?php echo substr($problem['problem_description'], 0, 150); ?>...
                            </p>
                            <div style="font-size: 0.9rem; color: #777;">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($problem['area_location']); ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($problem['reporter_name']); ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($problem['created_at'])); ?></span>
                                <?php if($problem['volunteer_name']): ?>
                                <span style="margin-left: 15px;"><i class="fas fa-user-check"></i> Assigned to: <?php echo htmlspecialchars($problem['volunteer_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn btn-info btn-sm" onclick="viewProblem(<?php echo $problem['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if($problem['status'] == 'reported' || $problem['status'] == 'under_review'): ?>
                            <button class="btn btn-success btn-sm" onclick="assignProblem(<?php echo $problem['id']; ?>)">
                                <i class="fas fa-user-check"></i> Assign
                            </button>
                            <?php endif; ?>
                            <?php if($problem['status'] != 'completed'): ?>
                            <div class="dropdown">
                                <button class="btn btn-warning btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-edit"></i> Status
                                </button>
                                <div class="dropdown-menu">
                                    <?php 
                                    $statuses = ['under_review', 'assigned', 'in_progress', 'completed', 'rejected'];
                                    foreach($statuses as $status): 
                                        if($status != $problem['status']):
                                    ?>
                                    <a class="dropdown-item" href="?action=update_status&id=<?php echo $problem['id']; ?>&status=<?php echo $status; ?>">
                                        Mark as <?php echo str_replace('_', ' ', ucfirst($status)); ?>
                                    </a>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <h3>No Problems Reported</h3>
                    <p>No society problems have been reported yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Problem Details Modal -->
    <div class="modal" id="problemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Problem Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="problemModalBody" style="padding: 20px;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Assign Problem Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Problem to Volunteer</h2>
                <button class="close-modal" onclick="closeAssignModal()">&times;</button>
            </div>
            <form method="POST" id="assignForm" style="padding: 20px;">
                <input type="hidden" name="problem_id" id="assignProblemId">
                
                <div class="form-group">
                    <label>Select Volunteer</label>
                    <select name="volunteer_id" class="form-control" required>
                        <option value="">Choose a volunteer...</option>
                        <?php while($volunteer = mysqli_fetch_assoc($volunteers_result)): ?>
                        <option value="<?php echo $volunteer['id']; ?>">
                            <?php echo htmlspecialchars($volunteer['full_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estimated Resolution Date</label>
                    <input type="date" name="estimated_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Admin Notes (Optional)</label>
                    <textarea name="admin_notes" class="form-control" rows="3" 
                              placeholder="Add instructions or notes for the volunteer..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Assign
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function viewProblem(id) {
            fetch(`admin_society_problems.php?action=view&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const modalBody = document.getElementById('problemModalBody');
                    
                    if (data.error) {
                        modalBody.innerHTML = `<div style="text-align: center; padding: 40px;">${data.error}</div>`;
                    } else {
                        const urgencyClass = `urgency-${data.urgency_level}`;
                        const statusClass = `status-${data.status}`;
                        
                        let imagesHtml = '';
                        if (data.images && data.images.length > 0) {
                            imagesHtml = `
                                <h4><i class="fas fa-images"></i> Attached Images</h4>
                                <div class="image-gallery">
                                    ${data.images.map(img => `
                                        <img src="${img}" alt="Problem Image" onclick="openImage('${img}')">
                                    `).join('')}
                                </div>
                            `;
                        }
                        
                        modalBody.innerHTML = `
                            <div style="margin-bottom: 20px;">
                                <h3>${data.problem_title}</h3>
                                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                    <span class="urgency-badge ${urgencyClass}">
                                        ${data.urgency_level.toUpperCase()} Priority
                                    </span>
                                    <span class="status-badge ${statusClass}">
                                        ${data.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                    <span class="category-badge">
                                        <i class="fas fa-tag"></i> ${data.problem_category.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <h4><i class="fas fa-user"></i> Reporter Information</h4>
                                    <p><strong>Name:</strong> ${data.reporter_name}</p>
                                    ${data.reporter_email ? `<p><strong>Email:</strong> ${data.reporter_email}</p>` : ''}
                                    ${data.reporter_phone ? `<p><strong>Phone:</strong> ${data.reporter_phone}</p>` : ''}
                                </div>
                                
                                <div>
                                    <h4><i class="fas fa-map-marker-alt"></i> Location Details</h4>
                                    <p><strong>Area:</strong> ${data.area_location}</p>
                                    ${data.exact_location ? `<p><strong>Exact Location:</strong> ${data.exact_location}</p>` : ''}
                                    ${data.google_map_link ? `<p><strong>Map Link:</strong> <a href="${data.google_map_link}" target="_blank">View on Google Maps</a></p>` : ''}
                                    ${data.latitude && data.longitude ? `<p><strong>Coordinates:</strong> ${data.latitude}, ${data.longitude}</p>` : ''}
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h4><i class="fas fa-file-alt"></i> Problem Description</h4>
                                <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                                    ${data.problem_description}
                                </div>
                            </div>
                            
                            ${imagesHtml}
                            
                            ${data.additional_info ? `
                                <div style="margin-bottom: 20px;">
                                    <h4><i class="fas fa-info-circle"></i> Additional Information</h4>
                                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                                        ${data.additional_info}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                                <div>
                                    <h4><i class="fas fa-calendar"></i> Timeline</h4>
                                    <p><strong>Reported:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>
                                    ${data.estimated_resolution_date ? `<p><strong>Estimated Resolution:</strong> ${data.estimated_resolution_date}</p>` : ''}
                                    ${data.updated_at !== data.created_at ? `<p><strong>Last Updated:</strong> ${new Date(data.updated_at).toLocaleDateString()}</p>` : ''}
                                </div>
                                
                                <div>
                                    <h4><i class="fas fa-users"></i> Assignment</h4>
                                    ${data.volunteer_name ? `<p><strong>Assigned to:</strong> ${data.volunteer_name}</p>` : '<p><strong>Not assigned yet</strong></p>'}
                                    ${data.admin_name ? `<p><strong>Assigned by:</strong> ${data.admin_name}</p>` : ''}
                                    ${data.admin_notes ? `<p><strong>Admin Notes:</strong> ${data.admin_notes}</p>` : ''}
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; display: flex; gap: 10px;">
                                <button class="btn btn-primary" onclick="assignProblem(${data.id})">
                                    <i class="fas fa-user-check"></i> Assign to Volunteer
                                </button>
                                <button class="btn btn-secondary" onclick="closeModal()">
                                    Close
                                </button>
                            </div>
                        `;
                    }
                    
                    document.getElementById('problemModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('problemModalBody').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="color: #ef4444; font-size: 3rem;"></i>
                            <h3>Error Loading Problem Details</h3>
                            <p>Please try again.</p>
                        </div>
                    `;
                    document.getElementById('problemModal').style.display = 'flex';
                });
        }
        
        function assignProblem(problemId) {
            document.getElementById('assignProblemId').value = problemId;
            document.getElementById('assignForm').action = `?action=assign&id=${problemId}`;
            document.getElementById('assignModal').style.display = 'flex';
            closeModal();
        }
        
        function closeModal() {
            document.getElementById('problemModal').style.display = 'none';
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }
        
        function openImage(src) {
            window.open(src, '_blank');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal();
                closeAssignModal();
            }
        });
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeAssignModal();
            }
        });
        
        // Set min date for estimated resolution
        document.querySelector('[name="estimated_date"]').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>