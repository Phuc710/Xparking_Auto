<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get and validate input
    $name = trim($_POST['from_name'] ?? '');
    $email = trim($_POST['from_email'] ?? '');
    $phone = trim($_POST['phone'] ?? 'Không cung cấp');
    $subject_type = trim($_POST['subject'] ?? 'Liên hệ từ website');
    $message_content = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($message_content)) {
        throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email không hợp lệ');
    }
    
    // Email configuration
    $to = 'support@xparking.x10.mx';
    $subject = 'Liên hệ từ website: ' . $subject_type;
    
    // Create message
    $message = "LIÊN HỆ TỪ WEBSITE XPARKING\n";
    $message .= str_repeat("=", 40) . "\n\n";
    $message .= "Họ tên: {$name}\n";
    $message .= "Email: {$email}\n";  
    $message .= "Điện thoại: {$phone}\n";
    $message .= "Chủ đề: {$subject_type}\n\n";
    $message .= "Nội dung:\n{$message_content}\n\n";
    $message .= str_repeat("-", 30) . "\n";
    $message .= "Thời gian: " . date('d/m/Y H:i:s') . "\n";
    $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
    
    // Email headers
    $headers = [
        'From' => "XParking Website <noreply@xparking.x10.mx>",
        'Reply-To' => $email,
        'Return-Path' => 'support@xparking.x10.mx',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= $key . ': ' . $value . "\r\n";
    }
    
    // Send email
    if (!function_exists('mail')) {
        throw new Exception('Chức năng mail không khả dụng trên server');
    }
    
    $mail_sent = mail($to, $subject, $message, $header_string);
    
    if ($mail_sent) {
        echo json_encode([
            'success' => true, 
            'message' => 'Email đã được gửi thành công!'
        ]);
    } else {
        throw new Exception('Không thể gửi email. Vui lòng thử lại sau.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>