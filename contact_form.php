<?php
// contact_form.php
session_start();
require_once 'config/config.php';

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate required fields
$required_fields = ['name', 'email', 'message'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Please fill in the $field field"]);
        exit();
    }
}

// Sanitize inputs
$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$organization = isset($_POST['organization']) ? sanitizeInput($_POST['organization']) : '';
$message = sanitizeInput($_POST['message']);
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Check for spam (basic check)
if (strlen($message) < 10) {
    echo json_encode(['success' => false, 'message' => 'Message is too short']);
    exit();
}

// Check if too many submissions from same IP (rate limiting)
$conn = getDBConnection();
$time_limit = date('Y-m-d H:i:s', strtotime('-1 hour'));
$sql = "SELECT COUNT(*) as count FROM contact_messages WHERE ip_address = ? AND created_at > ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $ip_address, $time_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] >= 5) {
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later.']);
    mysqli_close($conn);
    exit();
}

// Insert into database
$sql = "INSERT INTO contact_messages 
        (name, email, organization, message, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)";
        
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $organization, $message, $ip_address, $user_agent);

if (mysqli_stmt_execute($stmt)) {
    $message_id = mysqli_insert_id($conn);
    
    // Send email notification (optional)
    sendEmailNotification($name, $email, $organization, $message, $message_id);
    
    // Log activity if admin is logged in
    if (isset($_SESSION['admin_id'])) {
        $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description) 
                   VALUES (?, 'contact', 'New contact message from {$email}')";
        mysqli_query($conn, $log_sql);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you for your message! We will get back to you soon.',
        'message_id' => $message_id
    ]);
} else {
    error_log("Contact form error: " . mysqli_error($conn));
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to submit message. Please try again later.'
    ]);
}

mysqli_close($conn);

// Function to send email notification
function sendEmailNotification($name, $email, $organization, $message, $message_id) {
    // Email settings - configure these according to your server
    $to = "info@sarathi.com"; // Your email address
    $subject = "New Contact Form Message - Sarathi";
    
    $headers = "From: noreply@sarathi.com\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Email body
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
            .content { background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #1e293b; }
            .value { color: #64748b; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
                <p>Message ID: CM-" . str_pad($message_id, 6, '0', STR_PAD_LEFT) . "</p>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Name:</div>
                    <div class='value'>$name</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div class='value'><a href='mailto:$email'>$email</a></div>
                </div>
                <div class='field'>
                    <div class='label'>Organization:</div>
                    <div class='value'>" . ($organization ?: 'Not provided') . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Message:</div>
                    <div class='value'>$message</div>
                </div>
                <div class='field'>
                    <div class='label'>Submitted:</div>
                    <div class='value'>" . date('F j, Y, g:i a') . "</div>
                </div>
                <div class='field'>
                    <div class='label'>IP Address:</div>
                    <div class='value'>" . $_SERVER['REMOTE_ADDR'] . "</div>
                </div>
                <hr>
                <p>To view and manage this message, visit the admin panel.</p>
                <a href='http://yourdomain.com/admin_contact.php?id=$message_id' style='
                    background: #2563eb; 
                    color: white; 
                    padding: 10px 20px; 
                    text-decoration: none; 
                    border-radius: 5px;
                    display: inline-block;
                    margin-top: 10px;
                '>View in Admin Panel</a>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    // Uncomment the line below when you have email configured
    // mail($to, $subject, $email_body, $headers);
    
    // For testing, you can log instead
    error_log("Contact form email would be sent to: $to");
}
?>