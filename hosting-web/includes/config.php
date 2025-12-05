<?php
/**
 * CONFIG.PHP - Cấu hình trung tâm XPARKING
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

// === DATABASE (MySQL) ===

define('DB_HOST', 'localhost');
define('DB_NAME', 'xparking');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO Connection - XParking Database
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true,  // Connection pooling
                    PDO::ATTR_EMULATE_PREPARES => false,  // Native prepared statements
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,  // Buffered queries
                    PDO::ATTR_TIMEOUT => 3,  // Connection timeout
                    PDO::MYSQL_ATTR_LOCAL_INFILE => false,  // Security
                    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false  // Security
                ]
            );
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// Vietnam time
function now_vn() {
    return date('Y-m-d H:i:s');
}

// === SEPAY ===
define('SEPAY_API_URL', 'https://my.sepay.vn/userapi/transactions/list');
define('SEPAY_TOKEN', 'TRK794OUY0TJVVIPCASYOULQGNKI6DME2CXC8H0P4ZGDLRAK6FS1HWFV5DHGYNGJ');
define('SEPAY_QR_API', 'https://qr.sepay.vn/img');

// === VIETQR ===
define('BANK_ID', 'MBBank');
define('BANK_ACCOUNT', '09696969690');
define('BANK_NAME', 'NGUYEN THANH PHUC');
define('VIETQR_BANK_ID', 'MBBank');
define('VIETQR_ACCOUNT_NO', '09696969690');
define('VIETQR_ACCOUNT_NAME', 'NGUYEN THANH PHUC');
define('VIETQR_TEMPLATE', 'compact');

// === PARKING ===
define('PRICE_PER_MINUTE', 1000);
define('MIN_PRICE', 5000);
define('HOURLY_RATE', 5000);
define('QR_EXPIRE_MINUTES', 10);

// === SITE ===
define('SITE_URL', 'https://xparking.elementfx.com');
define('ADMIN_EMAIL', 'admin@xparking.com');

// === HELPERS ===
function redirect($url) { 
    header("Location: $url"); 
    exit; 
}

function flash($type, $msg) { 
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg]; 
}

function getFlash() { 
    $f = $_SESSION['flash'] ?? null; 
    unset($_SESSION['flash']); 
    return $f; 
}

function set_flash_message($type, $message) {
    flash($type, $message);
}

function get_flash_message() {
    return getFlash();
}
?>