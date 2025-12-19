<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Uploads Directory</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        .directory { font-family: monospace; background: #f8f9fa; padding: 5px 10px; border-radius: 3px; }
        ul { list-style: none; padding-left: 0; }
        li { padding: 5px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📁 Create Uploads Directory Structure</h1>
        <hr>";

// Define directory structure
$base_dir = __DIR__;
$directories = [
    'assets/uploads/passport',
    'assets/uploads/aadhaar',
    'assets/uploads/certificate',
    'assets/uploads/documents',
    'assets/uploads/temp'
];

$created = [];
$existing = [];
$failed = [];

foreach ($directories as $dir) {
    $full_path = $base_dir . '/' . $dir;
    
    if (!is_dir($full_path)) {
        if (mkdir($full_path, 0777, true)) {
            $created[] = $dir;
            
            // Create .htaccess for security
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($full_path . '/.htaccess', $htaccess_content);
            
            // Create index.html to prevent directory listing
            file_put_contents($full_path . '/index.html', '<html><body><h1>403 - Access Forbidden</h1></body></html>');
        } else {
            $failed[] = $dir;
        }
    } else {
        $existing[] = $dir;
    }
}

// Display results
if (!empty($created)) {
    echo "<div class='success'><strong>✅ Created directories:</strong>";
    echo "<ul>";
    foreach ($created as $dir) {
        echo "<li><span class='directory'>/$dir</span></li>";
    }
    echo "</ul></div>";
}

if (!empty($existing)) {
    echo "<div class='info'><strong>ℹ️ Already exists:</strong>";
    echo "<ul>";
    foreach ($existing as $dir) {
        echo "<li><span class='directory'>/$dir</span></li>";
    }
    echo "</ul></div>";
}

if (!empty($failed)) {
    echo "<div class='error'><strong>❌ Failed to create:</strong>";
    echo "<ul>";
    foreach ($failed as $dir) {
        echo "<li><span class='directory'>/$dir</span></li>";
    }
    echo "</ul></div>";
}

// Test permissions
echo "<h2>🔧 Permission Test</h2>";

$test_dir = $base_dir . '/assets/uploads/';
$test_file = $test_dir . 'test_permission.txt';

if (is_dir($test_dir)) {
    if (file_put_contents($test_file, 'test')) {
        echo "<div class='success'>✅ Write permission: OK</div>";
        unlink($test_file);
    } else {
        echo "<div class='error'>❌ Write permission: FAILED</div>";
        echo "<p>Please set folder permissions to 755 or 777:</p>";
        echo "<pre>chmod 777 " . realpath($test_dir) . "</pre>";
    }
}

// Check PHP settings
echo "<h2>⚙️ PHP Configuration</h2>";
echo "<ul>";
echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>post_max_size: " . ini_get('post_max_size') . "</li>";
echo "<li>max_file_uploads: " . ini_get('max_file_uploads') . "</li>";
echo "<li>PHP Version: " . PHP_VERSION . "</li>";
echo "</ul>";

// Check required PHP extensions
echo "<h2>📦 Required Extensions</h2>";
$extensions = ['gd', 'mysqli', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>✅ $ext: Enabled</div>";
    } else {
        echo "<div class='error'>❌ $ext: Not enabled</div>";
    }
}

// Directory structure visualization
echo "<h2>🌳 Project Structure</h2>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
function showTree($dir, $prefix = '') {
    $files = scandir($dir);
    $output = '';
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..' || $file == '.htaccess') continue;
        
        $path = $dir . '/' . $file;
        $is_dir = is_dir($path);
        
        $output .= $prefix . ($is_dir ? '📁 ' : '📄 ') . $file . "\n";
        
        if ($is_dir && ($file == 'assets' || $file == 'config' || $file == 'uploads')) {
            $output .= showTree($path, $prefix . '    ');
        }
    }
    
    return $output;
}

echo showTree($base_dir);
echo "</pre>";

// Test database connection
echo "<h2>🗄️ Database Test</h2>";
try {
    require_once 'config/config.php';
    $conn = getDBConnection();
    if ($conn) {
        echo "<div class='success'>✅ Database connection: SUCCESS</div>";
        
        // Check tables
        $tables = ['admins', 'volunteer_requests', 'volunteers', 'activity_logs'];
        foreach ($tables as $table) {
            $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if (mysqli_num_rows($result) > 0) {
                echo "<div class='success'>✅ Table '$table': EXISTS</div>";
            } else {
                echo "<div class='error'>❌ Table '$table': MISSING</div>";
            }
        }
        
        mysqli_close($conn);
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database connection: FAILED - " . $e->getMessage() . "</div>";
}

echo "<hr>
      <div class='info'>
          <strong>Next Steps:</strong>
          <ol>
              <li>Run this script once to create directories</li>
              <li>Test file upload with the form below</li>
              <li>Delete this file after setup (security)</li>
          </ol>
      </div>

      <h2>🚀 Quick Test Upload Form</h2>
      <form action='volunteer_request_fixed.php' method='POST' enctype='multipart/form-data' style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>
          <div style='margin-bottom: 15px;'>
              <label>Test Name:</label><br>
              <input type='text' name='full_name' value='Test User' required style='width: 100%; padding: 8px; margin-top: 5px;'>
          </div>
          <div style='margin-bottom: 15px;'>
              <label>Test File (Max 5MB):</label><br>
              <input type='file' name='passport_photo' required style='margin-top: 5px;'>
          </div>
          <button type='submit' style='background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>Test Upload</button>
      </form>

      <div style='margin-top: 20px; text-align: center;'>
          <a href='index.html' style='color: #007bff; text-decoration: none;'>← Back to Home</a> | 
          <a href='adminlogin.php' style='color: #007bff; text-decoration: none;'>Admin Login</a>
      </div>
    </div>
</body>
</html>";
?>