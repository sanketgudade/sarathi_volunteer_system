<?php
session_start();
require_once 'config/config.php';

// HARDCODED TEST
$email = 'test@example.com';
$password = 'password123';

$conn = getDBConnection();
$sql = "SELECT * FROM volunteer_requests WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

echo "<h3>Debug Info:</h3>";
if ($row = mysqli_fetch_assoc($result)) {
    echo "User found!<br>";
    echo "ID: " . $row['id'] . "<br>";
    echo "Email: " . $row['email'] . "<br>";
    echo "Status: " . $row['status'] . "<br>";
    echo "Password hash: " . $row['password'] . "<br>";
    
    // Try password
    if (password_verify($password, $row['password'])) {
        echo "✅ PASSWORD MATCHES! Login should work!";
        $_SESSION['volunteer_id'] = $row['id'];
        header("Location: volunteer_dashboard.php");
        exit();
    } else {
        echo "❌ Password doesn't match. Trying direct match...<br>";
        if ($password == $row['password']) {
            echo "✅ Direct password match works!";
        }
    }
} else {
    echo "❌ User not found!";
}
?>