<?php
/**
 * CONTACT FORM EMAIL HANDLER - Gửi email từ form liên hệ
 * Sử dụng SMTP: support@xparking.elementfx.com
 */

// Headers và CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Cấu hình email SMTP
$smtp_config = [
    'host' => 'mail.xparking.elementfx.com',
    'port' => 587,
    'username' => '',
    'password' => '',
    'from_email' => 'support@xparking.elementfx.com',
    'from_name' => 'XParking Support'
];

// Lấy dữ liệu form
$from_name = trim($_POST['from_name'] ?? '');
$from_email = trim($_POST['from_email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? 'Liên hệ từ website');
$message = trim($_POST['message'] ?? '');

// Validate dữ liệu
if (empty($from_name) || empty($from_email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc!']);
    exit;
}

if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ!']);
    exit;
}

// Tạo nội dung email
$email_subject = '[XParking] ' . $subject;
$email_body = "
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #2563eb; }
        .footer { text-align: center; padding: 15px; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <div class='header'>
        <h2>🅿️ Tin nhắn mới từ XParking</h2>
    </div>
    
    <div class='content'>
        <div class='info-box'>
            <h3>👤 Thông tin người gửi:</h3>
            <p><strong>Họ tên:</strong> {$from_name}</p>
            <p><strong>Email:</strong> {$from_email}</p>
            <p><strong>Điện thoại:</strong> " . ($phone ?: 'Không cung cấp') . "</p>
            <p><strong>Chủ đề:</strong> {$subject}</p>
            <p><strong>Thời gian:</strong> " . date('d/m/Y H:i:s') . "</p>
        </div>
        
        <div class='info-box'>
            <h3>💬 Nội dung tin nhắn:</h3>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
    </div>
    
    <div class='footer'>
        <p>Email được gửi tự động từ hệ thống XParking</p>
        <p>🌐 <a href='https://xparking.elementfx.com'>xparking.elementfx.com</a></p>
    </div>
</body>
</html>
";

// Tạo headers email
$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=utf-8',
    'From: ' . $smtp_config['from_name'] . ' <' . $smtp_config['from_email'] . '>',
    'Reply-To: ' . $from_email,
    'X-Mailer: PHP/' . phpversion()
];

try {
    // Gửi email bằng mail() function
    $mail_sent = mail(
        $smtp_config['from_email'],
        $email_subject,
        $email_body,
        implode("\r\n", $headers)
    );
    
    if ($mail_sent) {
        echo json_encode([
            'success' => true,
            'message' => "Cảm ơn {$from_name}! Tin nhắn đã được gửi thành công."
        ]);
    } else {
        throw new Exception('Mail function failed');
    }
    
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi gửi email. Vui lòng liên hệ trực tiếp qua support@xparking.elementfx.com'
    ]);
}
?>
