<?php
require_once 'config/config.php';

$email = $_GET['email'] ?? '';

if (!empty($email)) {
    $conn = getDBConnection();
    $sql = "SELECT id FROM volunteer_requests WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo 'exists';
    } else {
        echo 'available';
    }
    
    mysqli_close($conn);
}
?>