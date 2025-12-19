<?php
// Test the NEW hash
$password = 'Pass@1234@';
$new_hash = '$2y$10$m019gOICwXfEgKQDOlP29.05.v2E.wpjvol4mahm3IQlBk5ufDAVO';

echo "<h2>Testing New Hash</h2>";
echo "Password: $password<br>";
echo "Hash: $new_hash<br><br>";

if (password_verify($password, $new_hash)) {
    echo "<div style='background:green;color:white;padding:20px;'>✅ HASH IS NOW CORRECT! Login should work!</div>";
} else {
    echo "<div style='background:red;color:white;padding:20px;'>❌ Still wrong - something else is wrong</div>";
}
?>