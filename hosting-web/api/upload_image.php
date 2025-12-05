<?php
/**
 * UPLOAD IMAGE API - Upload ảnh xe vào/ra/vé
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tối ưu headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type, User-Agent, Accept');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Health check cho test_connection()
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/csdl.php';

// Config tối ưu cho hiệu suất
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_WIDTH', 640);      // Giảm từ 800 xuống 640 cho tốc độ
define('JPEG_QUALITY', 70);    // Tăng từ 75 lên 70 cho balance tốt hơn
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB limit

// Create folders if not exist
$folders = ['entry', 'exit', 'ticket'];
foreach ($folders as $folder) {
    $path = UPLOAD_DIR . $folder;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Đo thời gian xử lý
$start_time = microtime(true);

// Parse input với error handling
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    echo json_encode(['success' => false, 'error' => 'No input data received']);
    exit;
}

$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON format']);
    exit;
}

// Lấy và validate parameters
$type = trim($input['type'] ?? '');
$ticket_code = strtoupper(trim($input['ticket_code'] ?? ''));
$imageData = $input['image'] ?? '';

// Validate type
if (!in_array($type, ['entry', 'exit', 'ticket'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid type. Must be: entry, exit, or ticket']);
    exit;
}

// Validate ticket code format (VE + 8 hex chars)
if (!preg_match('/^VE[A-F0-9]{8}$/i', $ticket_code)) {
    echo json_encode(['success' => false, 'error' => 'Invalid ticket_code format. Must be VE + 8 hex chars']);
    exit;
}

// Validate image data
if (empty($imageData)) {
    echo json_encode(['success' => false, 'error' => 'No image data provided']);
    exit;
}

// Kiểm tra size base64 (estimate)
$estimated_size = (strlen($imageData) * 3) / 4;
if ($estimated_size > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'error' => 'Image too large. Max 2MB allowed']);
    exit;
}

// Decode base64
if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
    $imageData = substr($imageData, strpos($imageData, ',') + 1);
}
$imageData = base64_decode($imageData);

if ($imageData === false || strlen($imageData) < 100) {
    echo json_encode(['success' => false, 'error' => 'Invalid or corrupted base64 image data']);
    exit;
}

// Tạo image từ string với memory limit check
$image = @imagecreatefromstring($imageData);
if (!$image) {
    echo json_encode(['success' => false, 'error' => 'Cannot process image. Invalid format or corrupted']);
    exit;
}

// Lấy thông tin ảnh
$original_width = imagesx($image);
$original_height = imagesy($image);
$original_size = strlen($imageData);

// Tối ưu resize với thuật toán tốt hơn
$needs_resize = $original_width > MAX_WIDTH;
if ($needs_resize) {
    $ratio = MAX_WIDTH / $original_width;
    $new_width = MAX_WIDTH;
    $new_height = intval($original_height * $ratio);
    
    // Tạo ảnh mới với chất lượng cao
    $resized = imagecreatetruecolor($new_width, $new_height);
    
    // Tối ưu chất lượng resize
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    
    // Sử dụng resampling chất lượng cao
    if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height)) {
        imagedestroy($image);
        imagedestroy($resized);
        echo json_encode(['success' => false, 'error' => 'Image resize failed']);
        exit;
    }
    
    imagedestroy($image);
    $image = $resized;
}

// Generate filename với milliseconds cho unique
$timestamp = date('Ymd_His') . '_' . substr(microtime(), 2, 3);
$filename = "{$ticket_code}_{$type}_{$timestamp}.jpg";
$filepath = UPLOAD_DIR . $type . '/' . $filename;
$webpath = "/uploads/{$type}/{$filename}";

// Kiểm tra thư mục có writable không
if (!is_writable(dirname($filepath))) {
    imagedestroy($image);
    echo json_encode(['success' => false, 'error' => 'Upload directory not writable']);
    exit;
}

// Save với tối ưu JPEG
if (!imagejpeg($image, $filepath, JPEG_QUALITY)) {
    imagedestroy($image);
    echo json_encode(['success' => false, 'error' => 'Failed to save optimized image']);
    exit;
}

imagedestroy($image);

// Verify file được tạo thành công
if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => 'Image save verification failed']);
    exit;
}

$final_size = filesize($filepath);

// Xác định column database
$column_mapping = [
    'entry' => 'entry_image',
    'exit' => 'exit_image', 
    'ticket' => 'ticket_image'
];
$column = $column_mapping[$type];

// Update database với error handling
try {
    $updateResult = supabaseUpdate('tickets', 'ticket_code', $ticket_code, [
        $column => $webpath,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$updateResult) {
        // Nếu DB update thất bại, xóa file đã upload
        @unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
        exit;
    }
    
} catch (Exception $e) {
    // Cleanup file nếu có lỗi
    @unlink($filepath);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Tính toán metrics
$processing_time = round((microtime(true) - $start_time) * 1000, 1); // ms
$compression_ratio = $original_size > 0 ? round(($original_size - $final_size) / $original_size * 100, 1) : 0;
$size_reduction = $original_size - $final_size;

// Response tối ưu với đầy đủ thông tin
echo json_encode([
    'success' => true,
    'message' => 'Image uploaded and optimized successfully',
    'data' => [
        'type' => $type,
        'ticket_code' => $ticket_code,
        'path' => $webpath,
        'filename' => $filename,
        'size' => $final_size,
        'size_kb' => round($final_size / 1024, 1),
        'original_size' => $original_size,
        'original_size_kb' => round($original_size / 1024, 1),
        'compression_ratio' => $compression_ratio,
        'size_reduction_kb' => round($size_reduction / 1024, 1),
        'dimensions' => $needs_resize ? "{$new_width}x{$new_height}" : "{$original_width}x{$original_height}",
        'was_resized' => $needs_resize,
        'processing_time_ms' => $processing_time,
        'upload_timestamp' => date('Y-m-d H:i:s')
    ]
], JSON_UNESCAPED_SLASHES);
