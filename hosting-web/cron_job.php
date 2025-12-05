<?php
/**
 * CRON JOB - Xử lý tự động các trạng thái hết hạn
 * Chạy mỗi phút để đồng bộ hệ thống
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/api/csdl.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

function update_parking_statuses() {
    $now = new DateTime();
    $expireTime = (clone $now)->modify('-' . QR_EXPIRE_MINUTES . ' minutes');
    
    $updated = 0;
    $errors = [];
    
    try {
        // 1. Tìm và xử lý payments pending đã quá hạn (10 phút)
        $pendingPayments = supabaseQuery('payments', 'status=eq.pending');
        
        foreach ($pendingPayments as $payment) {
            $createdAt = new DateTime($payment['created_at']);
            
            // Nếu đã quá QR_EXPIRE_MINUTES phút
            if ($createdAt < $expireTime) {
                // Cập nhật payment thành expired
                supabaseUpdate('payments', 'id', $payment['id'], ['status' => 'expired']);
                
                // Hủy booking liên quan
                if ($payment['booking_id']) {
                    supabaseUpdate('bookings', 'id', $payment['booking_id'], ['status' => 'cancelled']);
                }
                
                $updated++;
                error_log("Expired payment ID: {$payment['id']}, booking_id: {$payment['booking_id']}");
            }
        }
        
        // 2. Tìm bookings đã hết thời gian (end_time < now)
        $activeBookings = supabaseQuery('bookings', 'status=in.(pending,confirmed)');
        
        foreach ($activeBookings as $booking) {
            $endTime = new DateTime($booking['end_time']);
            
            // Nếu đã quá end_time
            if ($endTime < $now) {
                // Kiểm tra xem xe đã check-in chưa
                $vehicle = supabaseQuery('vehicles', "license_plate=eq.{$booking['license_plate']}&status=eq.in_parking");
                
                if (empty($vehicle)) {
                    // Booking hết hạn CHƯA check-in → Cancel booking và giải phóng slot
                    supabaseUpdate('bookings', 'id', $booking['id'], ['status' => 'expired']);
                    
                    // Giải phóng slot nếu có
                    if ($booking['slot_id']) {
                        supabaseUpdate('parking_slots', 'id', $booking['slot_id'], ['status' => 'empty']);
                        error_log("Released slot {$booking['slot_id']} from expired booking ID: {$booking['id']}");
                    }
                    
                    $updated++;
                    error_log("Expired booking ID: {$booking['id']} - License: {$booking['license_plate']}");
                } else {
                    // Xe đã vào nhưng quá giờ → chuyển sang completed (sẽ tính phí overstay khi ra)
                    supabaseUpdate('bookings', 'id', $booking['id'], ['status' => 'completed']);
                    error_log("Completed booking ID: {$booking['id']} - Vehicle still in parking");
                }
            }
        }
        
        // 3. Cập nhật lại trạng thái của các slot
        // Lấy danh sách các slot cần cập nhật trạng thái
        $slots = supabaseQuery('parking_slots', 'status=not.eq.maintenance');
        
        foreach ($slots as $slot) {
            $occupied = false;
            $reserved = false;
            
            // Kiểm tra xe đỗ
            $vehicle = supabaseGetOne('vehicles', 'slot_id', $slot['id']);
            
            if ($vehicle && $vehicle['status'] === 'in_parking') {
                $occupied = true;
            }
            
            // Kiểm tra booking
            $booking = supabaseGetOne('bookings', 'slot_id', $slot['id']);
            
            if ($booking && $booking['status'] === 'confirmed' && $now >= new DateTime($booking['start_time']) && $now <= new DateTime($booking['end_time'])) {
                $reserved = true;
            }
            
            // Cập nhật trạng thái slot
            if ($occupied) {
                supabaseUpdate('parking_slots', 'id', $slot['id'], ['status' => 'occupied']);
            } elseif ($reserved) {
                supabaseUpdate('parking_slots', 'id', $slot['id'], ['status' => 'reserved']);
            } else {
                supabaseUpdate('parking_slots', 'id', $slot['id'], ['status' => 'empty']);
            }
        }
        
        echo json_encode([
            'success' => true,
            'updated' => $updated,
            'timestamp' => $now->format('Y-m-d H:i:s')
        ]);
        
        error_log("Cron job completed: {$updated} records updated.");
        
    } catch (Exception $e) {
        error_log("Cron job error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Chạy hàm
update_parking_statuses();
?>