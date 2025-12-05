<?php
/**
 * ADMIN_FUNCTIONS.PHP - Các hàm xử lý cho trang quản trị
 */

require_once __DIR__ . '/../api/csdl.php';

/**
 * Lấy xe đang trong bãi
 */
function get_active_vehicles() {
    try {
        $vehicles = dbGetMany('vehicles', ['status' => 'in_parking'], '*', 'entry_time.desc');
        return $vehicles;
    } catch (Exception $e) {
        error_log("Get active vehicles error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy tất cả users
 */
function get_all_users() {
    try {
        $users = dbGetAll('users', 'id,username,email,full_name,phone,role,created_at', 'created_at.desc');
        return $users;
    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy tất cả bookings với user info
 */
function get_all_bookings() {
    try {
        $bookings = dbGetAll('bookings', '*', 'created_at.desc');
        
        // Lấy user info và payment info
        foreach ($bookings as &$booking) {
            $user = dbGetOne('users', 'id', $booking['user_id']);
            if ($user) {
                $booking['username'] = $user['username'];
                $booking['full_name'] = $user['full_name'];
            }
            
            $payment = dbGetOne('payments', 'booking_id', $booking['id']);
            if ($payment) {
                $booking['payment_status'] = $payment['status'];
                $booking['amount'] = $payment['amount'];
            }
        }
        
        return $bookings;
    } catch (Exception $e) {
        error_log("Get bookings error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy tất cả payments
 */
function get_all_payments() {
    try {
        $payments = dbGetAll('payments', '*', 'created_at.desc', 50);
        
        // Lấy thêm user info và license_plate
        foreach ($payments as &$payment) {
            if ($payment['user_id']) {
                $user = dbGetOne('users', 'id', $payment['user_id']);
                if ($user) {
                    $payment['username'] = $user['username'];
                    $payment['full_name'] = $user['full_name'];
                }
            }
            
            // Lấy license_plate từ booking (đặt chỗ)
            if ($payment['booking_id']) {
                $booking = dbGetOne('bookings', 'id', $payment['booking_id']);
                if ($booking) {
                    $payment['license_plate'] = $booking['license_plate'];
                }
            }
            // Hoặc từ vehicle (vãng lai)
            elseif ($payment['vehicle_id']) {
                $vehicle = dbGetOne('vehicles', 'id', $payment['vehicle_id']);
                if ($vehicle) {
                    $payment['license_plate'] = $vehicle['license_plate'];
                }
            }
            
            // payment_time = paid_at hoặc updated_at khi completed
            if ($payment['status'] === 'completed') {
                $payment['payment_time'] = $payment['paid_at'] ?? $payment['updated_at'] ?? null;
            }
        }
        
        return $payments;
    } catch (Exception $e) {
        error_log("Get payments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy system logs
 */
function get_system_logs() {
    try {
        $logs = dbGetAll('system_logs', '*', 'created_at.desc', 100);
        
        // Lấy username
        foreach ($logs as &$log) {
            if ($log['user_id']) {
                $user = dbGetOne('users', 'id', $log['user_id']);
                if ($user) {
                    $log['username'] = $user['username'];
                }
            }
        }
        
        return $logs;
    } catch (Exception $e) {
        error_log("Get logs error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy thống kê doanh thu
 */
function get_revenue_stats() {
    try {
        $stats = [];
        
        // Lấy tất cả payments completed
        $completedPayments = dbQuery('payments', 'status=eq.completed');
        
        $today = date('Y-m-d');
        $stats['today'] = 0;
        $stats['week'] = 0;
        $stats['month'] = 0;
        $stats['total'] = 0;
        $stats['daily'] = [];
        
        $dailyRevenue = [];
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
        
        foreach ($completedPayments as $p) {
            $amount = floatval($p['amount']);
            $stats['total'] += $amount;
            
            if ($p['payment_time']) {
                $paymentDate = date('Y-m-d', strtotime($p['payment_time']));
                
                // Today
                if ($paymentDate === $today) {
                    $stats['today'] += $amount;
                }
                
                // This week
                if ($paymentDate >= $weekStart) {
                    $stats['week'] += $amount;
                }
                
                // This month
                if ($paymentDate >= $monthStart) {
                    $stats['month'] += $amount;
                }
                
                // Daily for last 7 days
                if ($paymentDate >= $sevenDaysAgo) {
                    if (!isset($dailyRevenue[$paymentDate])) {
                        $dailyRevenue[$paymentDate] = 0;
                    }
                    $dailyRevenue[$paymentDate] += $amount;
                }
            }
        }
        
        // Format daily array
        ksort($dailyRevenue);
        foreach ($dailyRevenue as $date => $revenue) {
            $stats['daily'][] = ['date' => $date, 'revenue' => $revenue];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Get revenue stats error: " . $e->getMessage());
        return [
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'total' => 0,
            'daily' => []
        ];
    }
}

/**
 * Lấy tất cả lịch sử gửi xe (từ tickets table)
 * Thời gian vào lấy từ vehicles.entry_time (thời gian thực tế xe vào)
 */
function get_all_vehicles_history() {
    try {
        $tickets = dbGetAll('tickets', '*', 'created_at.desc', 100);
        
        foreach ($tickets as &$t) {
            // Lấy thông tin vehicle để có entry_time thực tế
            $vehicle = dbGetOne('vehicles', 'ticket_code', $t['ticket_code']);
            $actual_entry_time = $vehicle ? $vehicle['entry_time'] : null;
            $actual_exit_time = $vehicle ? $vehicle['exit_time'] : null;
            
            // Xác định loại vé
            if ($t['booking_id']) {
                $t['ticket_type'] = 'booking';
                $t['type_label'] = 'Đặt trước';
                // Lấy thông tin booking để tính phí thêm
                $booking = dbGetOne('bookings', 'id', $t['booking_id']);
                if ($booking) {
                    $t['slot_id'] = $booking['slot_id'];
                    $t['booking_end'] = $booking['end_time'];
                    $t['checked_in'] = $booking['checked_in'] ?? false;
                    
                    // Tính phí quá giờ nếu có
                    $time_out = $actual_exit_time ?? $t['time_out'];
                    if ($time_out && $booking['end_time']) {
                        $end = strtotime($booking['end_time']);
                        $out = strtotime($time_out);
                        if ($out > $end) {
                            $overstay_mins = ceil(($out - $end) / 60);
                            $t['overstay_minutes'] = $overstay_mins;
                            $t['overstay_fee'] = ceil($overstay_mins / 60) * 5000;
                        }
                    }
                }
            } else {
                $t['ticket_type'] = 'walkin';
                $t['type_label'] = 'Vãng lai';
            }
            
            // Format thời gian - ƯU TIÊN entry_time từ vehicles (thời gian thực tế)
            $display_time_in = $actual_entry_time ?? $t['time_in'] ?? $t['created_at'];
            $display_time_out = $actual_exit_time ?? $t['time_out'];
            
            $t['time_in_formatted'] = $display_time_in ? date('d/m/Y H:i:s', strtotime($display_time_in)) : 'N/A';
            $t['time_out_formatted'] = $display_time_out ? date('d/m/Y H:i:s', strtotime($display_time_out)) : null;
            
            // Trạng thái
            switch ($t['status']) {
                case 'ACTIVE':
                    $t['status_label'] = 'Trong bãi';
                    $t['status_class'] = 'success';
                    break;
                case 'PAID':
                    $t['status_label'] = 'Đã thanh toán';
                    $t['status_class'] = 'info';
                    break;
                case 'USED':
                    $t['status_label'] = 'Đã ra';
                    $t['status_class'] = 'secondary';
                    break;
                default:
                    $t['status_label'] = $t['status'];
                    $t['status_class'] = 'warning';
            }
        }
        
        return $tickets;
    } catch (Exception $e) {
        error_log("Get all vehicles error: " . $e->getMessage());
        return [];
    }
}
