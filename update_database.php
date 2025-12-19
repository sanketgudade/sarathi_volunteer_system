<?php
require_once 'config/config.php';

$conn = getDBConnection();

// SQL queries to update database
$sql_updates = [
    // Update tasks table with location coordinates
    "ALTER TABLE tasks 
     ADD COLUMN IF NOT EXISTS location_lat DECIMAL(10,8),
     ADD COLUMN IF NOT EXISTS location_lng DECIMAL(11,8),
     ADD COLUMN IF NOT EXISTS radius_meters INT DEFAULT 100,
     ADD COLUMN IF NOT EXISTS points_awarded INT DEFAULT 10;",
    
    // Update volunteers table with tracking info
    "ALTER TABLE volunteers
     ADD COLUMN IF NOT EXISTS total_points INT DEFAULT 0,
     ADD COLUMN IF NOT EXISTS tasks_completed INT DEFAULT 0,
     ADD COLUMN IF NOT EXISTS current_rank INT DEFAULT 0,
     ADD COLUMN IF NOT EXISTS last_location_lat DECIMAL(10,8),
     ADD COLUMN IF NOT EXISTS last_location_lng DECIMAL(11,8),
     ADD COLUMN IF NOT EXISTS location_updated_at DATETIME;",
    
    // Create new attendance table (replacing checkins)
    "CREATE TABLE IF NOT EXISTS volunteer_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        volunteer_id INT NOT NULL,
        task_id INT NULL,
        check_in_time DATETIME NOT NULL,
        check_out_time DATETIME NULL,
        check_in_lat DECIMAL(10,8),
        check_in_lng DECIMAL(11,8),
        check_out_lat DECIMAL(10,8),
        check_out_lng DECIMAL(11,8),
        location_name VARCHAR(255),
        status ENUM('present', 'half_day', 'absent') DEFAULT 'present',
        device_info TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (volunteer_id) REFERENCES volunteers(id),
        FOREIGN KEY (task_id) REFERENCES tasks(id)
    );",
    
    // Create task proofs/reports table
    "CREATE TABLE IF NOT EXISTS task_proofs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        volunteer_id INT NOT NULL,
        report_text TEXT,
        images JSON,
        documents JSON,
        videos JSON,
        submitted_at DATETIME NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        points_awarded INT DEFAULT 0,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        FOREIGN KEY (task_id) REFERENCES tasks(id),
        FOREIGN KEY (volunteer_id) REFERENCES volunteers(id),
        FOREIGN KEY (reviewed_by) REFERENCES admins(id)
    );",
    
    // Create society problems table
    "CREATE TABLE IF NOT EXISTS society_problems (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        location_name VARCHAR(255),
        location_lat DECIMAL(10,8),
        location_lng DECIMAL(11,8),
        reported_by INT NULL,
        status ENUM('pending', 'assigned', 'in_progress', 'resolved') DEFAULT 'pending',
        assigned_to INT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        FOREIGN KEY (reported_by) REFERENCES volunteers(id),
        FOREIGN KEY (assigned_to) REFERENCES volunteers(id)
    );"
];

echo "<h2>Updating Database...</h2>";

foreach ($sql_updates as $sql) {
    echo "<p>Executing: " . substr($sql, 0, 100) . "...</p>";
    
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color: green;'>✓ Success</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . mysqli_error($conn) . "</p>";
    }
}

echo "<h3 style='color: green;'>Database update completed!</h3>";
echo "<a href='admin_panel.php'>Go to Admin Panel</a>";

mysqli_close($conn);
?>