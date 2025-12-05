<?php
// includes/auth.php
require_once 'config.php';
require_once __DIR__ . '/../api/csdl.php';

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Login user - Sử dụng Supabase REST API
function login_user($username, $password) {
    try {
        // Lấy user từ Supabase
        $user = supabaseGetOne('users', 'username', $username);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['user_fullname'] = $user['full_name'];
            
            // Log login
            log_activity('login', 'User logged in', $user['id']);
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// Register new user - Sử dụng Supabase REST API
function register_user($username, $password, $email, $full_name, $phone = null) {
    try {
        // Check if username already exists
        $existingUser = supabaseGetOne('users', 'username', $username);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại!'];
        }
        
        // Check if email already exists
        $existingEmail = supabaseGetOne('users', 'email', $email);
        if ($existingEmail) {
            return ['success' => false, 'message' => 'Email đã được sử dụng!'];
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user via Supabase
        $userData = [
            'username' => $username,
            'password' => $password_hash,
            'email' => $email,
            'full_name' => $full_name,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = supabaseInsert('users', $userData);
        
        if ($result && isset($result[0]['id'])) {
            // Log registration
            log_activity('registration', 'New user registered', $result[0]['id']);
            return ['success' => true, 'message' => 'Đăng ký thành công!'];
        }
        
        return ['success' => false, 'message' => 'Lỗi tạo tài khoản!'];
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau!'];
    }
}

// Log out user
function logout_user() {
    if (isset($_SESSION['user_id'])) {
        log_activity('logout', 'User logged out', $_SESSION['user_id']);
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Get user details by ID - Sử dụng Supabase REST API
function get_user($user_id) {
    try {
        $user = supabaseGetOne('users', 'id', $user_id);
        if ($user) {
            // Loại bỏ password khỏi kết quả
            unset($user['password']);
            return $user;
        }
        return false;
    } catch (Exception $e) {
        error_log("Get user error: " . $e->getMessage());
        return false;
    }
}

// Update user profile - Sử dụng Supabase REST API
function update_user_profile($user_id, $email, $full_name, $phone) {
    try {
        $result = supabaseUpdate('users', 'id', $user_id, [
            'email' => $email,
            'full_name' => $full_name,
            'phone' => $phone
        ]);
        
        if ($result) {
            // Update session values
            $_SESSION['user_email'] = $email;
            $_SESSION['user_fullname'] = $full_name;
            
            // Log update
            log_activity('profile_update', 'User updated profile', $user_id);
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Update profile error: " . $e->getMessage());
        return false;
    }
}

// Change user password - Sử dụng Supabase REST API
function change_user_password($user_id, $current_password, $new_password) {
    try {
        // Verify current password
        $user = supabaseGetOne('users', 'id', $user_id);
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng!'];
        }
        
        // Hash new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $result = supabaseUpdate('users', 'id', $user_id, [
            'password' => $password_hash
        ]);
        
        if ($result) {
            // Log password change
            log_activity('password_change', 'User changed password', $user_id);
            return ['success' => true, 'message' => 'Đổi mật khẩu thành công!'];
        }
        
        return ['success' => false, 'message' => 'Lỗi cập nhật mật khẩu!'];
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau!'];
    }
}

// Log system activity - Sử dụng Supabase REST API
function log_activity($event_type, $description, $user_id = null) {
    try {
        // Kiểm tra function supabaseInsert tồn tại
        if (!function_exists('supabaseInsert')) {
            error_log("supabaseInsert function not found");
            return false;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $result = supabaseInsert('system_logs', [
            'event_type' => $event_type,
            'description' => $description,
            'user_id' => $user_id,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $result !== null;
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
        return false;
    }
}

// Require login or redirect
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('error', 'Vui lòng đăng nhập để tiếp tục!');
        redirect(SITE_URL . '/index.php?page=login');
    }
}

// Require admin or redirect
function require_admin() {
    if (!is_admin()) {
        set_flash_message('error', 'Bạn không có quyền truy cập trang này!');
        redirect(SITE_URL . '/dashboard.php');
    }
}
?>