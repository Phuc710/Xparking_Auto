<?php
/**
 * FUNCTIONS.PHP - Các hàm xử lý nghiệp vụ
 * Version 2.0 - Sử dụng Supabase REST API
 */
require_once 'config.php';
require_once __DIR__ . '/../api/csdl.php';

/**
 * Lấy tất cả slots với trạng thái thực tế
 * Chỉ hiện: empty (trống), occupied (có xe), maintenance (bảo trì)
 * KHÔNG hiện booking trên slot - booking chỉ đảm bảo capacity
 */
function get_all_slots() {
    try {
        $db = db();
        
        // Lấy tất cả parking slots (sử dụng index idx_parking_status)
        $stmt = $db->prepare("SELECT * FROM parking_slots ORDER BY id");
        $stmt->execute();
        $slots = $stmt->fetchAll();
        
        // Lấy vehicles đang trong bãi (sử dụng index idx_vehicles_status)
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE status = 'in_parking'");
        $stmt->execute();
        $vehicles = $stmt->fetchAll();
        
        // Map vehicles theo slot_id
        $vehicleMap = [];
        foreach ($vehicles as $v) {
            if ($v['slot_id']) {
                $vehicleMap[$v['slot_id']] = $v;
            }
        }
        
        // Xác định trạng thái thực tế cho mỗi slot
        $result = [];
        foreach ($slots as $slot) {
            $slotId = $slot['id'];
            
            // Chỉ 3 trạng thái: occupied, maintenance, empty
            if (isset($vehicleMap[$slotId])) {
                $slot['actual_status'] = 'occupied';
                // Thông tin cho Admin (không hiện cho User)
                $slot['vehicle_license'] = $vehicleMap[$slotId]['license_plate'];
                $slot['ticket_code'] = $vehicleMap[$slotId]['ticket_code'] ?? null;
                $slot['entry_time'] = $vehicleMap[$slotId]['entry_time'] ?? null;
            } elseif ($slot['status'] === 'maintenance') {
                $slot['actual_status'] = 'maintenance';
            } else {
                $slot['actual_status'] = 'empty';
            }
            
            $result[] = $slot;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Get slots display error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy slots trống (available)
 */
function get_available_slots() {
    try {
        $allSlots = get_all_slots();
        
        return array_filter($allSlots, function($slot) {
            return $slot['actual_status'] === 'empty';
        });
    } catch (Exception $e) {
        error_log("Get available slots error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy slot theo ID
 */
function get_slot($slot_id) {
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM parking_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        error_log("Get slot error: " . $e->getMessage());
        return false;
    }
}

/**
 * Cập nhật trạng thái slot
 */
function update_slot_status($slot_id, $status) {
    try {
        $db = db();
        $stmt = $db->prepare("UPDATE parking_slots SET status = ?, updated_at = ? WHERE id = ?");
        $result = $stmt->execute([$status, date('Y-m-d H:i:s'), $slot_id]);
        return $result;
    } catch (Exception $e) {
        error_log("Update slot error: " . $e->getMessage());
        return false;
    }
}

/**
 * Tạo booking mới
 * Booking KHÔNG gắn với slot cụ thể - chỉ đảm bảo còn chỗ trống trong khoảng thời gian
 * Slot được gán khi xe THỰC SỰ vào bãi
 */
function create_booking($user_id, $license_plate, $start_time, $end_time) {
    try {
        // Validation input
        if (empty($user_id) || empty($license_plate) || empty($start_time) || empty($end_time)) {
            return ['success' => false, 'message' => 'Thiếu thông tin cần thiết!'];
        }
        
        // Validate thời gian
        $start_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $start_time);
        $end_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $end_time);
        
        if (!$start_datetime || !$end_datetime) {
            return ['success' => false, 'message' => 'Định dạng thời gian không hợp lệ!'];
        }
        
        if ($end_datetime <= $start_datetime) {
            return ['success' => false, 'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu!'];
        }
        
        // === KIỂM TRA CHỖ TRỐNG - MySQL ===
        $db = db();
        
        // Đếm slot khả dụng (sử dụng index idx_parking_status)
        $stmt = $db->prepare("SELECT COUNT(*) FROM parking_slots WHERE status != 'maintenance'");
        $stmt->execute();
        $available_slots = $stmt->fetchColumn();
        
        if ($available_slots == 0) {
            return ['success' => false, 'message' => 'Tất cả chỗ đỗ đang bảo trì!'];
        }
        
        // Đếm bookings đang active (sử dụng index idx_bookings_status)
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'confirmed')");
        $stmt->execute();
        $reserved_count = $stmt->fetchColumn();
        
        // Kiểm tra còn chỗ không
        if ($reserved_count >= $available_slots) {
            return ['success' => false, 'message' => 'Hết chỗ trống! Vui lòng thử lại sau.'];
        }
        
        // === TÍNH PHÍ ===
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'price_amount'");
        $stmt->execute();
        $price_amount_s = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'price_minutes'");
        $stmt->execute();
        $price_minutes_s = $stmt->fetchColumn();
        $price_amount = $price_amount_s ? intval($price_amount_s) : 5000;
        $price_minutes = $price_minutes_s ? intval($price_minutes_s) : 60;
        
        // Giá theo giờ
        $hourly_rate = ($price_minutes > 0) ? round($price_amount * 60 / $price_minutes) : 5000;
        
        // Tính số giờ
        $diff = $end_datetime->diff($start_datetime);
        $hours = $diff->h + ($diff->days * 24);
        if ($diff->i > 0) $hours += 1; // Làm tròn lên
        if ($hours < 1) $hours = 1; // Tối thiểu 1 giờ
        
        $amount = $hours * $hourly_rate;
        
        // === TẠO BOOKING ===
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO bookings (user_id, license_plate, start_time, end_time, amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $user_id,
                $license_plate,
                $start_datetime->format('Y-m-d H:i:s'),
                $end_datetime->format('Y-m-d H:i:s'),
                $amount,
                date('Y-m-d H:i:s')
            ]);
            
            $booking_id = $db->lastInsertId();
            if (!$booking_id) {
                throw new Exception('Failed to create booking');
            }
        
            // Tạo payment
            $payment_ref = 'BOOKS' . time() . $booking_id;
            $stmt = $db->prepare("
                INSERT INTO payments (user_id, booking_id, amount, payment_ref, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $user_id,
                $booking_id,
                $amount,
                $payment_ref,
                date('Y-m-d H:i:s')
            ]);
            
            $payment_id = $db->lastInsertId();
            if (!$payment_id) {
                throw new Exception('Failed to create payment');
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'payment_id' => $payment_id,
                'payment_ref' => $payment_ref,
                'amount' => $amount
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Create booking error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi tạo booking: ' . $e->getMessage()];
        }
        
    } catch (Exception $e) {
        error_log("Create booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

/**
 * Lấy danh sách booking của user
 */
function get_user_bookings($user_id) {
    try {
        // Lấy bookings của user
        $bookings = dbGetMany('bookings', ['user_id' => $user_id], '*', 'created_at.desc');
        
        // Lấy payments liên quan
        $bookingIds = array_column($bookings, 'id');
        
        if (empty($bookingIds)) {
            return [];
        }
        
        // Lấy payments cho các booking
        $payments = dbQuery('payments', 
            'booking_id=in.(' . implode(',', $bookingIds) . ')'
        );
        
        // Map payments theo booking_id
        $paymentMap = [];
        foreach ($payments as $p) {
            $paymentMap[$p['booking_id']] = $p;
        }
        
        // Merge data
        foreach ($bookings as &$booking) {
            if (isset($paymentMap[$booking['id']])) {
                $booking['payment_status'] = $paymentMap[$booking['id']]['status'];
                $booking['amount'] = $paymentMap[$booking['id']]['amount'];
                $booking['payment_ref'] = $paymentMap[$booking['id']]['payment_ref'];
            }
        }
        
        return $bookings;
    } catch (Exception $e) {
        error_log("Get bookings error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy booking theo ID
 */
function get_booking($booking_id) {
    try {
        $booking = dbGetOne('bookings', 'id', $booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Lấy user info
        $user = dbGetOne('users', 'id', $booking['user_id']);
        if ($user) {
            $booking['username'] = $user['username'];
            $booking['email'] = $user['email'];
            $booking['full_name'] = $user['full_name'];
        }
        
        // Lấy payment info
        $payment = dbGetOne('payments', 'booking_id', $booking_id);
        if ($payment) {
            $booking['payment_status'] = $payment['status'];
            $booking['amount'] = $payment['amount'];
            $booking['payment_ref'] = $payment['payment_ref'];
        }
        
        return $booking;
    } catch (Exception $e) {
        error_log("Get booking error: " . $e->getMessage());
        return false;
    }
}

/**
 * Hủy booking
 */
function cancel_booking($booking_id, $user_id) {
    try {
        // Kiểm tra booking thuộc về user
        $booking = dbQuery('bookings', "id=eq.$booking_id&user_id=eq.$user_id");
        
        if (empty($booking)) {
            return ['success' => false, 'message' => 'Booking không tồn tại hoặc không thuộc về bạn!'];
        }
        
        $booking = $booking[0];
        
        if ($booking['status'] === 'completed') {
            return ['success' => false, 'message' => 'Không thể hủy booking đã hoàn thành!'];
        }
        
        // Cập nhật booking status
        dbUpdate('bookings', 'id', $booking_id, ['status' => 'cancelled']);
        
        // Hủy payment liên quan
        dbUpdate('payments', 'booking_id', $booking_id, ['status' => 'cancelled']);
        
        return ['success' => true, 'message' => 'Hủy booking thành công!'];
        
    } catch (Exception $e) {
        error_log("Cancel booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau!'];
    }
}
/**
 * Tạo QR thanh toán
 */
function generate_payment_qr($payment_id) {
    try {
        // Lấy payment pending
        $payment = dbQuery('payments', "id=eq.$payment_id&status=eq.pending");
        
        if (empty($payment)) {
            return ['success' => false, 'message' => 'Thanh toán không tồn tại hoặc đã hoàn thành!'];
        }
        
        $payment = $payment[0];
        $reference = $payment['payment_ref'];
        $amount = intval($payment['amount']);
        
        // Tạo QR URL với SePay
        $qr_url = sprintf(
            "%s?acc=%s&bank=%s&amount=%d&des=%s&template=%s",
            SEPAY_QR_API,
            VIETQR_ACCOUNT_NO,
            VIETQR_BANK_ID,
            $amount,
            urlencode($reference),
            VIETQR_TEMPLATE
        );
        
        // Cập nhật QR URL vào database
        dbUpdate('payments', 'id', $payment_id, ['qr_code' => $qr_url]);

        return [
            'success' => true,
            'qr_code' => $qr_url,
            'reference' => $reference,
            'amount' => $payment['amount'],
            'bank_info' => [
                'bank' => VIETQR_BANK_ID,
                'account' => VIETQR_ACCOUNT_NO,
                'name' => VIETQR_ACCOUNT_NAME
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Generate QR error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

/**
 * Kiểm tra status payment
 */
function check_payment_status($payment_ref) {
    try {
        if (empty($payment_ref)) {
            return 'unknown';
        }
        
        $payment = dbGetOne('payments', 'payment_ref', $payment_ref);
        
        if (!$payment) {
            return 'not_found';
        }
        
        // Nếu đã completed/failed/expired thì return
        if (in_array($payment['status'], ['completed', 'failed', 'expired', 'cancelled'])) {
            return $payment['status'];
        }
        
        // Kiểm tra hết hạn (10 phút)
        $created_time = new DateTime($payment['created_at']);
        $current_time = new DateTime();
        
        $interval = $current_time->diff($created_time);
        $minutes_passed = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
        
        if ($minutes_passed >= QR_EXPIRE_MINUTES) {
            // Cập nhật thành expired
            dbUpdate('payments', 'payment_ref', $payment_ref, ['status' => 'expired']);
            return 'expired';
        }
        
        return $payment['status'];
        
    } catch (Exception $e) {
        error_log("Check payment status error: " . $e->getMessage());
        return 'error';
    }
}

/**
 * Verify payment qua SePay API
 */
function verify_payment_via_api($payment_ref, $amount) {
    try {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => SEPAY_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SEPAY_TOKEN,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            error_log("SePay CURL Error: " . $error);
            return ['success' => false, 'error' => 'CURL Error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            error_log("SePay HTTP Error: " . $httpCode);
            return ['success' => false, 'error' => 'HTTP Error: ' . $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || $data['status'] !== 200 || !isset($data['transactions'])) {
            error_log("SePay invalid response");
            return ['success' => false, 'error' => 'Invalid API response'];
        }
        
        $expected_amount = intval($amount);
        $check_time = new DateTime();
        $check_time->modify('-15 minutes');
        
        foreach ($data['transactions'] as $transaction) {
            $transaction_time = new DateTime($transaction['transaction_date']);
            $amount_match = intval($transaction['amount_in']) === $expected_amount;
            
            $content_patterns = [
                $payment_ref,
                str_replace(['BOOK-', 'EXIT-'], '', $payment_ref),
            ];
            
            $content_match = false;
            foreach ($content_patterns as $pattern) {
                if (stripos($transaction['transaction_content'], $pattern) !== false) {
                    $content_match = true;
                    break;
                }
            }
            
            $time_match = $transaction_time >= $check_time;
            
            if ($amount_match && $content_match && $time_match) {
                error_log("Payment found: $payment_ref - Transaction: " . $transaction['id']);
                return [
                    'success' => true,
                    'transaction_id' => $transaction['id']
                ];
            }
        }
        
        return ['success' => false];
        
    } catch (Exception $e) {
        error_log("Verify payment API error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Xử lý hoàn thành thanh toán
 */
function process_payment_completion($payment_ref, $transaction_id) {
    try {
        // Lấy payment hiện tại
        $payment = dbGetOne('payments', 'payment_ref', $payment_ref);
        
        if (!$payment || $payment['status'] !== 'pending') {
            return false;
        }
        
        // Update payment
        $updateResult = dbUpdate('payments', 'payment_ref', $payment_ref, [
            'status' => 'completed',
            'payment_time' => date('Y-m-d H:i:s'),
            'sepay_ref' => $transaction_id
        ]);
        
        if (!$updateResult) {
            return false;
        }
        
        // Update booking if exists
        if ($payment['booking_id']) {
            dbUpdate('bookings', 'id', $payment['booking_id'], ['status' => 'confirmed']);
            
            // Tạo ticket cho booking (nếu chưa có)
            $existing_ticket = dbGetOne('tickets', 'booking_id', $payment['booking_id']);
            if (!$existing_ticket) {
                $booking = dbGetOne('bookings', 'id', $payment['booking_id']);
                if ($booking) {
                    $new_ticket = 'VE' . strtoupper(substr(md5(uniqid()), 0, 8));
                    $qr_url = SITE_URL . '/payment.php?ticket=' . $new_ticket;
                    
                    dbInsert('tickets', [
                        'ticket_code' => $new_ticket,
                        'booking_id' => $booking['id'],
                        'license_plate' => $booking['license_plate'],
                        'time_in' => $booking['start_time'],
                        'qr_url' => $qr_url,
                        'status' => 'PAID',
                        'amount' => $payment['amount'],
                        'paid_at' => date('Y-m-d H:i:s'),
                        'transaction_id' => $transaction_id
                    ]);
                }
            }
        }
        
        // Handle vehicle exit payment
        if ($payment['vehicle_id']) {
            $vehicle = dbGetOne('vehicles', 'id', $payment['vehicle_id']);
            
            if ($vehicle) {
                dbUpdate('vehicles', 'id', $payment['vehicle_id'], ['status' => 'exited']);
                
                if ($vehicle['slot_id']) {
                    dbUpdate('parking_slots', 'id', $vehicle['slot_id'], ['status' => 'empty']);
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Process payment completion error: " . $e->getMessage());
        return false;
    }
}

/**
 * Hủy payment
 */
function cancel_payment($payment_ref, $user_id = null) {
    try {
        // Tìm payment
        $query = "payment_ref=eq.$payment_ref";
        if ($user_id) {
            $query .= "&user_id=eq.$user_id";
        }
        
        $payments = dbQuery('payments', $query);
        
        if (empty($payments)) {
            return ['success' => false, 'message' => 'Không tìm thấy thanh toán này hoặc không thuộc về bạn!'];
        }
        
        $payment = $payments[0];
        
        if ($payment['status'] === 'completed') {
            return ['success' => false, 'message' => 'Không thể hủy thanh toán đã hoàn thành!'];
        }
        
        if ($payment['status'] === 'cancelled') {
            return ['success' => true, 'message' => 'Thanh toán đã được hủy trước đó!'];
        }
        
        // Kiểm tra hết hạn
        $created_time = new DateTime($payment['created_at']);
        $current_time = new DateTime();
        $interval = $current_time->diff($created_time);
        $minutes_passed = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
        
        $new_status = ($minutes_passed >= QR_EXPIRE_MINUTES) ? 'expired' : 'cancelled';
        
        // Cập nhật payment
        dbUpdate('payments', 'id', $payment['id'], ['status' => $new_status]);
        
        // Hủy booking liên quan nếu có
        if ($payment['booking_id']) {
            dbUpdate('bookings', 'id', $payment['booking_id'], ['status' => 'cancelled']);
        }
        
        $message = ($new_status === 'expired') 
            ? 'Thanh toán đã hết hạn và được hủy thành công!' 
            : 'Đã hủy thanh toán thành công!';
            
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        error_log("Cancel payment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

/**
 * Lấy payment theo ID
 */
function get_payment_by_id($payment_id) {
    try {
        return dbGetOne('payments', 'id', $payment_id);
    } catch (Exception $e) {
        error_log("Get payment error: " . $e->getMessage());
        return null;
    }
}

/**
 * Lấy payment theo ref
 */
function get_payment_by_ref($payment_ref) {
    try {
        return dbGetOne('payments', 'payment_ref', $payment_ref);
    } catch (Exception $e) {
        error_log("Get payment error: " . $e->getMessage());
        return null;
    }
}
