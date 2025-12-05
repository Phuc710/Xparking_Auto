<?php
/**
 * GET NOTIFICATIONS API - Lấy thông báo mới nhất
 * Params: ?since=YYYY-MM-DD HH:MM:SS (optional)
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

$since = ApiResponse::param('since');

try {
    // Lấy thông báo mới nhất (public notification - target_user_id = null)
    $query = 'target_user_id=is.null&order=created_at.desc&limit=1';
    $notifications = dbQuery('notifications', $query);
    
    if (!$notifications || empty($notifications)) {
        ApiResponse::success([
            'notification' => null,
            'hasNew' => false,
            'message' => 'Không có thông báo nào'
        ]);
    }
    
    $latest_notification = $notifications[0];
    $hasNew = false;
    
    // Kiểm tra xem có thông báo mới không (nếu có tham số since)
    if ($since) {
        $notificationTime = strtotime($latest_notification['created_at']);
        $sinceTime = strtotime($since);
        $hasNew = $notificationTime > $sinceTime;
    }
    
    ApiResponse::success([
        'notification' => $latest_notification,
        'hasNew' => $hasNew,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications API error: " . $e->getMessage());
    ApiResponse::error('Lỗi hệ thống khi lấy thông báo');
}
