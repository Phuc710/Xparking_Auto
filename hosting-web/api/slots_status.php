<?php
/**
 * SLOTS STATUS API - SIMPLIFIED (Global Count)
 * GET: Lấy trạng thái bãi đỗ xe từ settings table
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

try {
    $db = db();
    
    // Lấy total_slots từ settings
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'total_slots'");
    $stmt->execute();
    $total = (int)($stmt->fetchColumn() ?: 50);
    
    // Lấy occupied_slots từ settings
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'occupied_slots'");
    $stmt->execute();
    $occupied = (int)($stmt->fetchColumn() ?: 0);
    
    // Tính available
    $available = max(0, $total - $occupied);
    
    // Đếm bookings pending (chưa vào bãi)
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'confirmed')");
    $stmt->execute();
    $reserved = (int)$stmt->fetchColumn();
    
    // Response
    ApiResponse::success([
        'data' => [
            'total' => $total,
            'occupied' => $occupied,
            'available' => $available,
            'reserved' => $reserved
        ],
        'display' => "$occupied/$total",
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Slots status error: " . $e->getMessage());
    ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
