<?php
/**
 * SLOTS STATUS API - Clean & Simple
 * GET: Lấy trạng thái bãi đỗ xe
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

try {
    $db = db();
    
    // Lấy tất cả slots (quan trọng: phải lấy hết để hiển thị map và tính toán)
    $stmt = $db->prepare("SELECT * FROM parking_slots ORDER BY id ASC");
    $stmt->execute();
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($slots === false) {
        ApiResponse::error('Database connection failed', 500);
    }
    
    // Đếm trạng thái slots từ array kết quả (nhanh & an toàn hơn query lại)
    $total = count($slots);
    $empty = 0;
    $occupied = 0;
    $maintenance = 0;
    
    foreach ($slots as $slot) {
        switch ($slot['status']) {
            case 'empty':
                $empty++;
                break;
            case 'occupied':
                $occupied++;
                break;
            case 'maintenance':
                $maintenance++;
                break;
        }
    }
    
    // Đếm bookings active (sử dụng index idx_bookings_status)
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'confirmed')");
    $stmt->execute();
    $reserved = (int)$stmt->fetchColumn();
    
    // Chỗ trống thực tế = empty slots - reserved
    $available = max(0, $empty - $reserved);
    
    // Response
    ApiResponse::success([
        'data' => [
            'total' => $total,
            'empty' => $empty,
            'occupied' => $occupied,
            'maintenance' => $maintenance,
            'reserved' => $reserved,
            'available' => $available
        ],
        'slots' => $slots,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Slots status error: " . $e->getMessage());
    ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
