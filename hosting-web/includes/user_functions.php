<?php
/**
 * USER_FUNCTIONS.PHP - Các hàm xử lý cho trang người dùng
 */

require_once __DIR__ . '/../api/csdl.php';
require_once __DIR__ . '/functions.php'; // Cần get_all_slots

/**
 * Lấy thống kê thực tế của user
 */
function get_user_statistics($user_id) {
    try {
        $stats = [];
        
        // Lấy tất cả bookings của user
        $bookings = dbGetMany('bookings', ['user_id' => $user_id]);
        $stats['total_bookings'] = count($bookings);
        
        // Đếm confirmed bookings
        $activeBookings = array_filter($bookings, function($b) {
            return $b['status'] === 'confirmed';
        });
        $stats['active_bookings'] = count($activeBookings);
        
        // Tính tổng giờ từ bookings confirmed/completed
        $total_hours = 0;
        foreach ($bookings as $b) {
            if (in_array($b['status'], ['confirmed', 'completed'])) {
                $start = new DateTime($b['start_time']);
                $end = new DateTime($b['end_time']);
                $diff = $end->diff($start);
                $total_hours += $diff->h + ($diff->days * 24);
            }
        }
        $stats['total_hours'] = $total_hours;
        
        // Lấy vehicles của user
        $vehicles = dbGetMany('vehicles', ['user_id' => $user_id]);
        $stats['total_parkings'] = count($vehicles);
        
        // Tổng chi phí từ payments completed
        $payments = dbGetMany('payments', ['user_id' => $user_id, 'status' => 'completed']);
        $total_spent = 0;
        foreach ($payments as $p) {
            $total_spent += floatval($p['amount']);
        }
        $stats['total_spent'] = $total_spent;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get user statistics error: " . $e->getMessage());
        return [
            'total_bookings' => 0,
            'total_parkings' => 0,
            'total_hours' => 0,
            'total_spent' => 0,
            'active_bookings' => 0
        ];
    }
}

/**
 * Lấy trạng thái slots (Wrapper)
 */
function get_slots_display_status() {
    return get_all_slots();
}

/**
 * Lấy xe của user
 */
function get_user_vehicles($user_id) {
    try {
        return dbGetMany('vehicles', ['user_id' => $user_id], '*', 'entry_time.desc');
    } catch (Exception $e) {
        error_log("Get user vehicles error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy slots trống (cho trang booking)
 */
function get_booking_available_slots() {
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM parking_slots WHERE status = 'empty'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
