<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Structure Check</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>🗄️ Database Structure Check</h2>";

$conn = getDBConnection();

// Check volunteer_requests table
echo "<h3>1. Checking volunteer_requests table:</h3>";

$result = mysqli_query($conn, "SHOW TABLES LIKE 'volunteer_requests'");
if (mysqli_num_rows($result) > 0) {
    echo "<div class='success'>✅ Table 'volunteer_requests' exists</div>";
    
    // Check table structure
    $desc_result = mysqli_query($conn, "DESCRIBE volunteer_requests");
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
    
    while ($row = mysqli_fetch_assoc($desc_result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
        
        // Check email field
        if ($row['Field'] == 'email') {
            if ($row['Key'] == 'UNI') {
                echo "<tr><td colspan='6' class='success'>✅ Email field has UNIQUE constraint (good)</td></tr>";
            } else {
                echo "<tr><td colspan='6' class='warning'>⚠️ Email field should have UNIQUE constraint</td></tr>";
            }
        }
    }
    echo "</table>";
    
    // Test insertion
    echo "<h3>2. Test Data Insertion:</h3>";
    
    $test_email = "test_" . time() . "@example.com";
    $test_sql = "INSERT INTO volunteer_requests 
                (full_name, age, gender, mobile_number, email, education, skills, 
                 passport_photo, aadhaar_card, school_certificate, ngo_name, role_position, request_message) 
                VALUES ('Test User', 25, 'Male', '1234567890', ?, 'Bachelor', 'Testing', 
                'test.jpg', 'test.jpg', 'test.pdf', 'Test NGO', 'Tester', 'Test message')";
    
    $stmt = mysqli_prepare($conn, $test_sql);
    mysqli_stmt_bind_param($stmt, "s", $test_email);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<div class='success'>✅ Test insertion successful</div>";
        
        // Try duplicate email
        try {
            mysqli_stmt_execute($stmt); // Try same email again
            echo "<div class='error'>❌ UNIQUE constraint NOT working (should fail)</div>";
        } catch (Exception $e) {
            echo "<div class='success'>✅ UNIQUE constraint working correctly (failed as expected)</div>";
        }
        
        // Clean up
        mysqli_query($conn, "DELETE FROM volunteer_requests WHERE email LIKE 'test_%@example.com'");
    } else {
        echo "<div class='error'>❌ Test insertion failed: " . mysqli_error($conn) . "</div>";
    }
    
} else {
    echo "<div class='error'>❌ Table 'volunteer_requests' does not exist</div>";
    
    // Create table SQL
    echo "<h3>Create Table SQL:</h3>";
    echo "<pre>
CREATE TABLE volunteer_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    education VARCHAR(255) NOT NULL,
    skills TEXT,
    passport_photo VARCHAR(500) NOT NULL,
    aadhaar_card VARCHAR(500) NOT NULL,
    school_certificate VARCHAR(500) NOT NULL,
    ngo_name VARCHAR(255) NOT NULL,
    role_position VARCHAR(255) NOT NULL,
    request_message TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
</pre>";
}

// Check for existing duplicate emails
echo "<h3>3. Checking for existing duplicate emails:</h3>";
$dup_sql = "SELECT email, COUNT(*) as count 
            FROM volunteer_requests 
            WHERE email IS NOT NULL AND email != '' 
            GROUP BY email 
            HAVING COUNT(*) > 1";
$dup_result = mysqli_query($conn, $dup_sql);

if (mysqli_num_rows($dup_result) > 0) {
    echo "<div class='warning'>⚠️ Found duplicate emails:</div>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
            <tr><th>Email</th><th>Count</th></tr>";
    while ($row = mysqli_fetch_assoc($dup_result)) {
        echo "<tr><td>" . htmlspecialchars($row['email']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='success'>✅ No duplicate emails found</div>";
}

// Check for empty emails
echo "<h3>4. Checking for empty/null emails:</h3>";
$empty_sql = "SELECT COUNT(*) as empty_count FROM volunteer_requests WHERE email IS NULL OR email = '' OR email = '0'";
$empty_result = mysqli_query($conn, $empty_sql);
$empty_row = mysqli_fetch_assoc($empty_result);

if ($empty_row['empty_count'] > 0) {
    echo "<div class='error'>❌ Found " . $empty_row['empty_count'] . " records with empty/0 email</div>";
    
    // Show sample
    $sample_sql = "SELECT id, email FROM volunteer_requests WHERE email IS NULL OR email = '' OR email = '0' LIMIT 5";
    $sample_result = mysqli_query($conn, $sample_sql);
    echo "<div class='warning'>Sample records:</div>";
    echo "<pre>";
    while ($sample = mysqli_fetch_assoc($sample_result)) {
        echo "ID: " . $sample['id'] . " - Email: '" . $sample['email'] . "'\n";
    }
    echo "</pre>";
    
    // Fix SQL
    echo "<h3>Fix empty emails:</h3>";
    echo "<pre>
-- First, backup your data
CREATE TABLE volunteer_requests_backup AS SELECT * FROM volunteer_requests;

-- Update empty emails to unique values
UPDATE volunteer_requests 
SET email = CONCAT('fixed_', id, '_', UNIX_TIMESTAMP(), '@example.com')
WHERE email IS NULL OR email = '' OR email = '0';

-- Verify
SELECT COUNT(*) FROM volunteer_requests WHERE email IS NULL OR email = '' OR email = '0';
</pre>";
} else {
    echo "<div class='success'>✅ No empty/null emails found</div>";
}

mysqli_close($conn);

echo "</body></html>";
?>