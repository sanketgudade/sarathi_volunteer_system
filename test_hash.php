<?php
// Test the current hash
$password = 'Pass@1234@';
$current_hash = '$2y$10$0p.TR33cTQlnIkmV3WgT5OxjHx57zSC2Q2yq0PhhWj5.Rt9/0VUQe';

echo "Testing password: 'Pass@1234@'<br>";
echo "Current hash: $current_hash<br><br>";

if (password_verify($password, $current_hash)) {
    echo "✅ CURRENT HASH IS CORRECT!<br>";
} else {
    echo "❌ CURRENT HASH IS WRONG!<br><br>";
    echo "Generating new hash...<br>";
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    echo "New hash: <strong>$new_hash</strong><br><br>";
    
    echo "SQL to update:<br>";
    echo "<pre>DELETE FROM admins WHERE username = 'Admin@1234@';</pre>";
    echo "<pre>INSERT INTO admins (username, password, email, organization_code, organization_name, role) 
VALUES (
    'Admin@1234@',
    '$new_hash',
    'admin@sarathi.com',
    'ORG-001',
    'Sarathi International',
    'super_admin'
);</pre>";
}
?>