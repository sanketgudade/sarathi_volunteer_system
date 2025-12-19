<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

$conn = getDBConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix File Paths</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .warning { color: orange; background: #fff3cd; padding: 10px; margin: 5px 0; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        button { background: #2563eb; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h1>🛠️ Fix All File Paths</h1>";

// Check current state
$sql = "SELECT id, full_name, passport_photo, aadhaar_card, school_certificate 
        FROM volunteer_requests";
$result = mysqli_query($conn, $sql);

echo "<h3>Current Database Records:</h3>";
echo "<table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Passport Path</th>
            <th>Aadhaar Path</th>
            <th>Certificate Path</th>
        </tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['full_name']}</td>
            <td>{$row['passport_photo']}</td>
            <td>{$row['aadhaar_card']}</td>
            <td>{$row['school_certificate']}</td>
          </tr>";
}
echo "</table>";

// Fix paths when button is clicked
if (isset($_POST['fix'])) {
    echo "<div class='warning'>Fixing paths...</div>";
    
    $total_fixed = 0;
    
    // Get all records
    $sql = "SELECT id, passport_photo, aadhaar_card, school_certificate 
            FROM volunteer_requests";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $fixed = false;
        
        // Fix passport photo
        if (!empty($row['passport_photo']) && strpos($row['passport_photo'], 'assets/uploads/') === false) {
            $new_path = fixPath($row['passport_photo']);
            $update = "UPDATE volunteer_requests SET passport_photo = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt, "si", $new_path, $id);
            mysqli_stmt_execute($stmt);
            $fixed = true;
        }
        
        // Fix aadhaar card
        if (!empty($row['aadhaar_card']) && strpos($row['aadhaar_card'], 'assets/uploads/') === false) {
            $new_path = fixPath($row['aadhaar_card']);
            $update = "UPDATE volunteer_requests SET aadhaar_card = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt, "si", $new_path, $id);
            mysqli_stmt_execute($stmt);
            $fixed = true;
        }
        
        // Fix certificate
        if (!empty($row['school_certificate']) && strpos($row['school_certificate'], 'assets/uploads/') === false) {
            $new_path = fixPath($row['school_certificate']);
            $update = "UPDATE volunteer_requests SET school_certificate = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt, "si", $new_path, $id);
            mysqli_stmt_execute($stmt);
            $fixed = true;
        }
        
        if ($fixed) {
            $total_fixed++;
        }
    }
    
    echo "<div class='success'>✅ Fixed {$total_fixed} records</div>";
}

function fixPath($path) {
    // Remove any leading slashes or dots
    $path = ltrim($path, './');
    
    // If it already starts with assets/uploads, return as is
    if (strpos($path, 'assets/uploads/') === 0) {
        return $path;
    }
    
    // If it starts with uploads/, add assets/
    if (strpos($path, 'uploads/') === 0) {
        return 'assets/' . $path;
    }
    
    // If it's just a filename, guess the folder
    if (strpos($path, '/') === false) {
        if (strpos($path, 'passport') !== false) {
            return 'assets/uploads/passport/' . $path;
        } elseif (strpos($path, 'aadhaar') !== false) {
            return 'assets/uploads/aadhaar/' . $path;
        } elseif (strpos($path, 'certificate') !== false) {
            return 'assets/uploads/certificate/' . $path;
        } else {
            return 'assets/uploads/documents/' . $path;
        }
    }
    
    // Default: prepend assets/uploads/
    return 'assets/uploads/' . basename($path);
}

echo "<form method='POST' style='margin: 20px 0;'>
        <button type='submit' name='fix' onclick='return confirm(\"Are you sure you want to fix all file paths?\")'>
            🔧 Fix All File Paths
        </button>
      </form>";

echo "<h3>Test File Access:</h3>";

// Test if files exist
$test_sql = "SELECT id, passport_photo FROM volunteer_requests LIMIT 3";
$test_result = mysqli_query($conn, $test_sql);

echo "<table>
        <tr>
            <th>ID</th>
            <th>File Path</th>
            <th>File Exists?</th>
            <th>Preview</th>
        </tr>";

while ($row = mysqli_fetch_assoc($test_result)) {
    $path = $row['passport_photo'];
    $full_path = __DIR__ . '/../' . $path;
    $file_exists = file_exists($full_path) ? '✅ Yes' : '❌ No';
    
    echo "<tr>
            <td>{$row['id']}</td>
            <td>{$path}</td>
            <td>{$file_exists}</td>
            <td>";
    
    if (file_exists($full_path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            echo "<img src='{$path}' style='max-width: 100px; max-height: 80px;'>";
        } else {
            echo "<i class='fas fa-file'></i> {$ext} file";
        }
    }
    
    echo "</td></tr>";
}

echo "</table>";

echo "</body></html>";
?>