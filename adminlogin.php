<?php
// Enable for debugging, disable in production
error_reporting(0); // Change to E_ALL for debugging
ini_set('display_errors', 0); // Change to 1 for debugging

require_once 'config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (!$conn) {
        $error = "Database connection failed! Please contact administrator.";
    } else {
        $username   = sanitizeInput($_POST['username']);
        $password   = $_POST['password']; // Don't sanitize password
        $secret_key = sanitizeInput($_POST['secret_key']);
        $org_code   = sanitizeInput($_POST['org_code']);
        
        // Debug log
        error_log("Login attempt: User=$username, Org=$org_code");
        
        // Check NGO Secret Key
        if ($secret_key !== NGO_SECRET_KEY) {
            $error = "Invalid NGO Secret Key!";
            error_log("Invalid secret key entered: $secret_key");
        } else {
            // Query database
            $sql = "SELECT * FROM admins WHERE username = ? AND organization_code = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $username, $org_code);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    // Verify password
                    if (password_verify($password, $row['password'])) {
                        // SUCCESS - Set session variables
                        $_SESSION['admin_id']    = $row['id'];
                        $_SESSION['admin_name']  = $row['username'];
                        $_SESSION['admin_email'] = $row['email'];
                        $_SESSION['org_name']    = $row['organization_name'];
                        $_SESSION['org_code']    = $row['organization_code'];
                        $_SESSION['admin_role']  = isset($row['role']) ? $row['role'] : 'admin';
                        
                        // Prevent session fixation
                        session_regenerate_id(true);
                        
                        // Update last login
                        $update_sql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "i", $row['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                        
                        // Log activity (if table exists)
                        try {
                            $log_sql = "INSERT INTO activity_logs (admin_id, action_type, description, ip_address, user_agent) 
                                        VALUES (?, 'login', 'Admin logged in', ?, ?)";
                            $log_stmt = mysqli_prepare($conn, $log_sql);
                            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
                            mysqli_stmt_bind_param($log_stmt, "iss", $row['id'], $ip_address, $user_agent);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        } catch (Exception $e) {
                            // Ignore if activity_logs table doesn't exist
                        }
                        
                        mysqli_stmt_close($stmt);
                        mysqli_close($conn);
                        
                        // Redirect to admin panel
                        header("Location: admin_panel.php");
                        exit();
                    } else {
                        $error = "Invalid password!";
                        error_log("Password verification failed for user: $username");
                    }
                } else {
                    $error = "Invalid username or organization code!";
                }
                
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database error!";
                error_log("Prepare statement failed: " . mysqli_error($conn));
            }
        }
        mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sarathi NGO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a56db;
            --primary-dark: #1e429f;
            --secondary: #7e22ce;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f9fafb;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .credential-note {
            background: #eff6ff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--primary);
        }
        
        .credential-note h4 {
            color: #1e40af;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .credential-note ul {
            list-style: none;
        }
        
        .credential-note li {
            padding: 8px 0;
            border-bottom: 1px solid #dbeafe;
            display: flex;
            justify-content: space-between;
        }
        
        .credential-note li:last-child {
            border-bottom: none;
        }
        
        .credential-note .label {
            color: #4b5563;
            font-weight: 500;
        }
        
        .credential-note .value {
            color: #111827;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .links a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1><i class="fas fa-user-shield"></i> Sarathi</h1>
                <p>Administrator Login Portal</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Admin Username</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Enter username" 
                               value="Admin@1234@" 
                               required 
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" 
                               id="password" 
                               placeholder="Enter password" 
                               value="Pass@1234@" 
                               required 
                               autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Organization Code</label>
                    <div class="input-group">
                        <i class="fas fa-building input-icon"></i>
                        <input type="text" name="org_code" class="form-control" 
                               placeholder="Enter organization code" 
                               value="ORG-001" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">NGO Secret Key</label>
                    <div class="input-group">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" name="secret_key" class="form-control" 
                               id="secretKey" 
                               placeholder="Enter secret key" 
                               value="NGO@1234@" 
                               required>
                        <button type="button" class="password-toggle" id="toggleSecretKey">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
                </button>
            </form>
            
            <div class="credential-note">
                <h4><i class="fas fa-check-circle"></i> Verified Login Credentials</h4>
                <ul>
                    <li>
                        <span class="label">Username:</span>
                        <span class="value">Admin@1234@</span>
                    </li>
                    <li>
                        <span class="label">Password:</span>
                        <span class="value">Pass@1234@ <span class="status-badge">✓ Fixed</span></span>
                    </li>
                    <li>
                        <span class="label">Organization Code:</span>
                        <span class="value">ORG-001</span>
                    </li>
                    <li>
                        <span class="label">Secret Key:</span>
                        <span class="value">NGO@1234@</span>
                    </li>
                </ul>
            </div>
            
            <div class="links">
                <p>
                    <a href="index.html">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </a>
                </p>
                <p style="font-size: 0.9rem; color: #6b7280; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Contact support if you encounter issues
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        // Toggle secret key visibility
        document.getElementById('toggleSecretKey').addEventListener('click', function() {
            const secretInput = document.getElementById('secretKey');
            const icon = this.querySelector('i');
            
            if (secretInput.type === 'password') {
                secretInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                secretInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="username"]').focus();
        });
        
        // Form submission feedback
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            submitBtn.disabled = true;
            
            // Re-enable button after 5 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });
    </script>
</body>
</html>