<?php
require_once 'config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$conn = getDBConnection();

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'view':
            $request_id = intval($_GET['id']);
            viewVolunteerRequest($request_id);
            exit();
            
        case 'delete':
            $request_id = intval($_GET['id']);
            deleteRequest($conn, $request_id);
            break;
    }
}

function viewVolunteerRequest($request_id) {
    require_once 'config/config.php';
    $conn = getDBConnection();
    
    // IMPORTANT: Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $sql = "SELECT * FROM volunteer_requests WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);
    
    if (!$request) {
        echo json_encode(['error' => 'Request not found']);
        exit();
    }
    
    // DEBUG: Check what's in the database
    error_log("DEBUG - Request ID: " . $request_id);
    error_log("DEBUG - Passport Photo Path: " . $request['passport_photo']);
    error_log("DEBUG - Aadhaar Card Path: " . $request['aadhaar_card']);
    error_log("DEBUG - School Certificate Path: " . $request['school_certificate']);
    
    // Function to check if file exists
    function checkFileExists($path) {
        if (empty($path)) return false;
        
        // Remove base URL if present
        $local_path = str_replace('http://localhost/sarathi_volunteer_system/', '', $path);
        $local_path = str_replace('http://localhost/', '', $local_path);
        
        // Try multiple locations
        $possible_paths = [
            'C:/xampp/htdocs/sarathi_volunteer_system/' . $local_path,
            'C:/xampp/htdocs/' . $local_path,
            $_SERVER['DOCUMENT_ROOT'] . '/' . $local_path,
            $local_path
        ];
        
        foreach ($possible_paths as $test_path) {
            if (file_exists($test_path)) {
                error_log("DEBUG - File found at: " . $test_path);
                return true;
            }
        }
        
        error_log("DEBUG - File NOT found: " . $path);
        return false;
    }
    
    // Function to fix file paths - IMPROVED VERSION
    function fixFilePath($path, $type = '') {
        if (empty($path)) {
            error_log("DEBUG - Empty path for type: " . $type);
            return '';
        }
        
        // Check if already a valid URL
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            error_log("DEBUG - Already a URL: " . $path);
            return $path;
        }
        
        // Base URL
        $base_url = 'http://localhost/sarathi_volunteer_system/';
        
        // Clean the path
        $clean_path = ltrim($path, './');
        
        // Check what type of path we have
        if (strpos($clean_path, 'assets/uploads/') === 0) {
            // Already has correct path structure
            $final_path = $base_url . $clean_path;
            error_log("DEBUG - Fixed path (assets): " . $final_path);
            return $final_path;
        }
        
        // If it's just a filename, determine folder
        if (strpos($clean_path, '/') === false) {
            if (strpos($clean_path, 'passport') !== false) {
                $final_path = $base_url . 'assets/uploads/passport/' . $clean_path;
            } elseif (strpos($clean_path, 'aadhaar') !== false) {
                $final_path = $base_url . 'assets/uploads/aadhaar/' . $clean_path;
            } elseif (strpos($clean_path, 'certificate') !== false) {
                $final_path = $base_url . 'assets/uploads/certificate/' . $clean_path;
            } else {
                $final_path = $base_url . 'assets/uploads/' . $clean_path;
            }
            error_log("DEBUG - Fixed path (filename only): " . $final_path);
            return $final_path;
        }
        
        // Default: prepend base URL
        $final_path = $base_url . $clean_path;
        error_log("DEBUG - Fixed path (default): " . $final_path);
        return $final_path;
    }
    
    // Fix document paths
    $request['passport_photo'] = fixFilePath($request['passport_photo'], 'passport');
    $request['aadhaar_card'] = fixFilePath($request['aadhaar_card'], 'aadhaar');
    $request['school_certificate'] = fixFilePath($request['school_certificate'], 'certificate');
    
    // Add debug information
    $request['debug'] = [
        'original_passport' => $request['passport_photo'],
        'original_aadhaar' => $request['aadhaar_card'],
        'original_certificate' => $request['school_certificate'],
        'passport_exists' => checkFileExists($request['passport_photo']),
        'aadhaar_exists' => checkFileExists($request['aadhaar_card']),
        'certificate_exists' => checkFileExists($request['school_certificate']),
        'base_url' => 'http://localhost/sarathi_volunteer_system/',
        'document_root' => $_SERVER['DOCUMENT_ROOT']
    ];
    
    echo json_encode($request);
    exit();
}

function deleteRequest($conn, $request_id) {
    $sql = "DELETE FROM volunteer_requests WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "✅ Request deleted successfully";
    } else {
        $_SESSION['error'] = "❌ Failed to delete request";
    }
    
    header("Location: admin_requests.php");
    exit();
}

// Get all requests with their status
$sql = "SELECT * FROM volunteer_requests ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'pending') as pending,
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'approved') as approved,
    (SELECT COUNT(*) FROM volunteer_requests WHERE status = 'rejected') as rejected,
    (SELECT COUNT(*) FROM volunteer_requests) as total";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Requests - Admin Panel</title>
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
            --sidebar-width: 250px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #1e40af);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .header {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 4px solid var(--primary);
        }
        
        .stat-card.pending { border-top-color: var(--warning); }
        .stat-card.approved { border-top-color: var(--secondary); }
        .stat-card.rejected { border-top-color: var(--danger); }
        .stat-card.total { border-top-color: var(--info); }
        
        .stat-number {
            font-size: 2.5rem;
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
            padding: 15px;
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
        }
        
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        .btn-success { background: var(--secondary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-info { background: var(--info); color: white; }
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
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .document-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        
        .document-card img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            background: #f8fafc;
            padding: 10px;
        }
        
        .document-info {
            padding: 15px;
            border-top: 1px solid var(--border);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .file-path {
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            word-break: break-all;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-hands-helping"></i> Sarathi</h2>
            <p style="opacity: 0.8; font-size: 0.9rem;">Admin Panel</p>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="admin_panel.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_volunteers.php"><i class="fas fa-users"></i> Volunteers</a></li>
            <li><a href="admin_requests.php" class="active"><i class="fas fa-user-clock"></i> Requests</a></li>
            <li><a href="admin_contact.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Volunteer Requests Management</h1>
                <p style="color: var(--text-light);">Manage all volunteer applications</p>
            </div>
            <div>
                <button class="btn btn-info" onclick="window.location.href='admin_panel.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-inbox" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Total</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">All requests</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-clock" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Pending</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Awaiting review</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Approved</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Requests approved</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-times-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <h3>Rejected</h3>
                        <p style="color: var(--text-light); font-size: 0.9rem;">Requests rejected</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <button class="filter-btn active" onclick="filterRequests('all')">All Requests</button>
            <button class="filter-btn" onclick="filterRequests('pending')">Pending</button>
            <button class="filter-btn" onclick="filterRequests('approved')">Approved</button>
            <button class="filter-btn" onclick="filterRequests('rejected')">Rejected</button>
        </div>
        
        <!-- Requests Table -->
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-user-clock"></i> All Volunteer Requests</h2>
                <span class="status-badge status-pending"><?php echo $stats['total']; ?> Requests</span>
            </div>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
                <table id="requestsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Applicant Details</th>
                            <th>Contact Info</th>
                            <th>NGO & Role</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            // Ensure all fields have values
                            $row['education'] = $row['education'] ?? '';
                            $row['skills'] = $row['skills'] ?? '';
                            $row['ngo_name'] = $row['ngo_name'] ?? 'Sarathi';
                            $row['role_position'] = $row['role_position'] ?? 'field worker';
                            $row['status'] = $row['status'] ?? 'pending';
                        ?>
                            <tr class="request-row" data-status="<?php echo $row['status']; ?>">
                                <td>VR-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                    <small style="color: var(--text-light);">Age: <?php echo $row['age']; ?>, <?php echo $row['gender']; ?></small><br>
                                    <small>Education: <?php echo htmlspecialchars($row['education']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['email']); ?><br>
                                    <small style="color: var(--text-light);"><?php echo htmlspecialchars($row['mobile_number']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['ngo_name']); ?><br>
                                    <small style="color: var(--text-light);"><?php echo htmlspecialchars($row['role_position']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                    <small style="color: var(--text-light);">
                                        <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewRequest(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if($row['status'] == 'pending'): ?>
                                            <a href="admin_panel.php?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="admin_panel.php?action=reject&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-warning btn-sm" onclick="deleteRequest(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3>No Volunteer Requests Found</h3>
                    <p>There are no volunteer requests in the system yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for Viewing Request Details -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Volunteer Application Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody" style="padding: 20px;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <script>
        // View Request Details
        function viewRequest(id) {
            fetch(`admin_requests.php?action=view&id=${id}`)
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        return response.text().then(text => {
                            console.error("Received non-JSON response:", text.substring(0, 200));
                            throw new Error("Server error. Please check PHP error logs.");
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    const modalBody = document.getElementById('modalBody');
                    
                    if (data.error) {
                        modalBody.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
                                <h3>${data.error}</h3>
                            </div>
                        `;
                    } else {
                        modalBody.innerHTML = `
                            <div style="margin-bottom: 20px;">
                                <h3>Personal Information</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <p><strong>Full Name:</strong> ${data.full_name || 'Not provided'}</p>
                                        <p><strong>Age:</strong> ${data.age || 'Not provided'}</p>
                                        <p><strong>Gender:</strong> ${data.gender || 'Not provided'}</p>
                                    </div>
                                    <div>
                                        <p><strong>Email:</strong> ${data.email || 'Not provided'}</p>
                                        <p><strong>Mobile:</strong> ${data.mobile_number || 'Not provided'}</p>
                                        <p><strong>Education:</strong> ${data.education || 'Not provided'}</p>
                                    </div>
                                </div>
                                <p><strong>Skills:</strong> ${data.skills || 'Not specified'}</p>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h3>NGO Information</h3>
                                <p><strong>NGO Name:</strong> ${data.ngo_name || 'Not specified'}</p>
                                <p><strong>Role/Position:</strong> ${data.role_position || 'Not specified'}</p>
                                <p><strong>Request Message:</strong> ${data.request_message || 'No message provided'}</p>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h3>Application Status</h3>
                                <p><strong>Status:</strong> <span class="status-badge status-${data.status}">${data.status.toUpperCase()}</span></p>
                                <p><strong>Submitted On:</strong> ${new Date(data.created_at).toLocaleDateString('en-IN', { 
                                    weekday: 'long', 
                                    year: 'numeric', 
                                    month: 'long', 
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}</p>
                                ${data.invite_code ? `<p><strong>Invite Code:</strong> <code>${data.invite_code}</code></p>` : ''}
                            </div>
                            
                            <div>
                                <h3>Uploaded Documents</h3>
                                ${data.passport_photo || data.aadhaar_card || data.school_certificate ? `
                                    <div class="documents-grid">
                                        ${data.passport_photo ? `
                                            <div class="document-card">
                                                <h4>Passport Photo</h4>
                                                <img src="${data.passport_photo}" 
                                                     alt="Passport Photo" 
                                                     onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                                <div class="document-info">
                                                    <a href="${data.passport_photo}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View Full
                                                    </a>
                                                    <div class="file-path">${data.passport_photo}</div>
                                                </div>
                                            </div>
                                        ` : `
                                            <div class="document-card">
                                                <h4>Passport Photo</h4>
                                                <div style="text-align: center; padding: 20px; color: var(--text-light);">
                                                    <i class="fas fa-file-exclamation"></i>
                                                    <p>No passport photo uploaded</p>
                                                </div>
                                            </div>
                                        `}
                                        
                                        ${data.aadhaar_card ? `
                                            <div class="document-card">
                                                <h4>Aadhaar Card</h4>
                                                <img src="${data.aadhaar_card}" 
                                                     alt="Aadhaar Card" 
                                                     onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                                <div class="document-info">
                                                    <a href="${data.aadhaar_card}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View Full
                                                    </a>
                                                    <div class="file-path">${data.aadhaar_card}</div>
                                                </div>
                                            </div>
                                        ` : `
                                            <div class="document-card">
                                                <h4>Aadhaar Card</h4>
                                                <div style="text-align: center; padding: 20px; color: var(--text-light);">
                                                    <i class="fas fa-file-exclamation"></i>
                                                    <p>No Aadhaar card uploaded</p>
                                                </div>
                                            </div>
                                        `}
                                        
                                        ${data.school_certificate ? `
                                            <div class="document-card">
                                                <h4>School Certificate</h4>
                                                <img src="${data.school_certificate}" 
                                                     alt="Certificate" 
                                                     onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200?text=Image+Not+Found';">
                                                <div class="document-info">
                                                    <a href="${data.school_certificate}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View Full
                                                    </a>
                                                    <div class="file-path">${data.school_certificate}</div>
                                                </div>
                                            </div>
                                        ` : `
                                            <div class="document-card">
                                                <h4>School Certificate</h4>
                                                <div style="text-align: center; padding: 20px; color: var(--text-light);">
                                                    <i class="fas fa-file-exclamation"></i>
                                                    <p>No school certificate uploaded</p>
                                                </div>
                                            </div>
                                        `}
                                    </div>
                                ` : `
                                    <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 10px;">
                                        <i class="fas fa-file-exclamation" style="font-size: 2rem; color: var(--warning);"></i>
                                        <p>No documents were uploaded with this application</p>
                                    </div>
                                `}
                            </div>
                            
                            <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                                ${data.status === 'pending' ? `
                                    <a href="admin_panel.php?action=approve&id=${data.id}" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve Application
                                    </a>
                                    <a href="admin_panel.php?action=reject&id=${data.id}" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject Application
                                    </a>
                                ` : ''}
                                <button class="btn btn-info" onclick="window.location.href='admin_volunteers.php'">
                                    <i class="fas fa-users"></i> View All Volunteers
                                </button>
                                <button class="btn btn-warning" onclick="closeModal()">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        `;
                    }
                    
                    document.getElementById('detailsModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error:', error);
                    const modalBody = document.getElementById('modalBody');
                    modalBody.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
                            <h3>Error Loading Request Details</h3>
                            <p>Failed to load volunteer request details. Please try again.</p>
                            <p style="color: var(--text-light); font-size: 0.9rem;">${error.message}</p>
                            <div style="margin-top: 20px;">
                                <button class="btn btn-warning" onclick="closeModal()">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </div>
                    `;
                    document.getElementById('detailsModal').style.display = 'flex';
                });
        }
        
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Delete Request
        function deleteRequest(id) {
            if (confirm('⚠️ Are you sure you want to delete this volunteer request?\n\nThis action cannot be undone!')) {
                window.location.href = `admin_requests.php?action=delete&id=${id}`;
            }
        }
        
        // Filter requests by status
        function filterRequests(status) {
            const rows = document.querySelectorAll('.request-row');
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            filterButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(status)) {
                    btn.classList.add('active');
                }
            });
            
            // Filter rows
            rows.forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal();
            }
        });
        
        // Initialize filter to show all
        document.addEventListener('DOMContentLoaded', function() {
            filterRequests('all');
        });
    </script>
</body>
</html>
