<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Empty Emails</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>🛠️ Fix Empty Email Records</h2>";

$conn = getDBConnection();

// Count bad records
$count_sql = "SELECT COUNT(*) as bad_count FROM volunteer_requests WHERE email IS NULL OR email = '' OR email = '0'";
$count_result = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_result);
$bad_count = $count_row['bad_count'];

echo "<p>Found <strong>{$bad_count}</strong> records with empty/null/0 email</p>";

if ($bad_count > 0) {
    echo "<form method='POST'>
            <button type='submit' name='fix' onclick='return confirm(\"Are you sure you want to fix {$bad_count} records?\")'>
                🔧 Fix Empty Emails
            </button>
          </form>";
    
    if (isset($_POST['fix'])) {
        // Fix the empty emails
        $update_sql = "UPDATE volunteer_requests 
                      SET email = CONCAT('fixed_', id, '_', UNIX_TIMESTAMP(), '@sarathi.example.com')
                      WHERE email IS NULL OR email = '' OR email = '0'";
        
        if (mysqli_query($conn, $update_sql)) {
            $affected = mysqli_affected_rows($conn);
            echo "<div class='success'>✅ Fixed {$affected} records successfully!</div>";
            
            // Verify
            $verify_sql = "SELECT COUNT(*) as remaining FROM volunteer_requests WHERE email IS NULL OR email = '' OR email = '0'";
            $verify_result = mysqli_query($conn, $verify_sql);
            $verify_row = mysqli_fetch_assoc($verify_result);
            
            if ($verify_row['remaining'] == 0) {
                echo "<div class='success'>✅ All empty emails have been fixed!</div>";
            } else {
                echo "<div class='error'>❌ Still have {$verify_row['remaining']} bad records</div>";
            }
            
            // Show some fixed records
            $sample_sql = "SELECT id, email FROM volunteer_requests WHERE email LIKE 'fixed_%@sarathi.example.com' LIMIT 5";
            $sample_result = mysqli_query($conn, $sample_sql);
            echo "<h3>Sample Fixed Records:</h3>";
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
                    <tr><th>ID</th><th>New Email</th></tr>";
            while ($row = mysqli_fetch_assoc($sample_result)) {
                echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Failed to fix records: " . mysqli_error($conn) . "</div>";
        }
    }
} else {
    echo "<div class='success'>✅ No empty email records found!</div>";
}

// Show all records for debugging
echo "<h3>Current Records Preview:</h3>";
$preview_sql = "SELECT id, full_name, email FROM volunteer_requests ORDER BY id DESC LIMIT 10";
$preview_result = mysqli_query($conn, $preview_sql);

echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
        </tr>";

while ($row = mysqli_fetch_assoc($preview_result)) {
    $email_status = '';
    if (empty($row['email']) || $row['email'] == '0') {
        $email_status = "<span style='color:red'>❌ BAD</span>";
    } else {
        $email_status = "<span style='color:green'>✅ OK</span>";
    }
    
    echo "<tr>
            <td>{$row['id']}</td>
            <td>" . htmlspecialchars($row['full_name']) . "</td>
            <td>" . htmlspecialchars($row['email']) . "</td>
            <td>{$email_status}</td>
          </tr>";
}
echo "</table>";

mysqli_close($conn);

echo "<hr>
    <h3>Next Steps:</h3>
    <ol>
        <li>Run this fix script first</li>
        <li>Delete this file after fixing</li>
        <li>Test the volunteer form again</li>
    </ol>
    
    <p><a href='volunteer_request_fixed.php'>← Back to Volunteer Form</a></p>
</body>
</html>";
?>