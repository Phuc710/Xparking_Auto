<?php
/**
 * TICKET_FUNCTIONS.PHP - Xử lý vé xe vãng lai + Booking overstay
 */
require_once __DIR__ . '/csdl.php';

// Lấy giá từ settings
function getPricingSettings() {
    $price_amount_s = dbGetOne('settings', 'key', 'price_amount');
    $price_minutes_s = dbGetOne('settings', 'key', 'price_minutes');
    $min_price_s = dbGetOne('settings', 'key', 'min_price');
    
    $price_amount = $price_amount_s ? intval($price_amount_s['value']) : 5000;
    $price_minutes = $price_minutes_s ? intval($price_minutes_s['value']) : 60;
    $min_price = $min_price_s ? intval($min_price_s['value']) : 5000;
    
    // Tính giá theo phút
    $price_per_minute = ($price_minutes > 0) ? $price_amount / $price_minutes : 100;
    
    return [
        'price_per_minute' => $price_per_minute,
        'min_price' => $min_price,
        'price_amount' => $price_amount,
        'price_minutes' => $price_minutes
    ];
}

function generateTicketCode() {
    return 'VE' . strtoupper(bin2hex(random_bytes(4)));
}

// Tạo vé mới
function createTicket($params) {
    $plate = strtoupper(trim($params['license_plate'] ?? ''));
    if (empty($plate)) return ['success' => false, 'error' => 'Thiếu BSX'];
    
    $code = generateTicketCode();
    $qr = (defined('SITE_URL') ? SITE_URL : '') . "/payment.php?ticket=$code";
    
    $r = dbInsert('tickets', [
        'ticket_code' => $code,
        'license_plate' => $plate,
        'time_in' => date('Y-m-d H:i:s'),
        'qr_url' => $qr,
        'status' => 'ACTIVE',
        'payment_method' => 'webhook'
    ]);
    
    if ($r && isset($r[0])) {
        return [
            'success' => true,
            'ticket_code' => $code,
            'qr_url' => $qr,
            'license_plate' => $plate,
            'time_in' => date('Y-m-d H:i:s')
        ];
    }
    
    // Log error
    error_log("Failed to create ticket: " . json_encode($r));
    return ['success' => false, 'error' => 'Lỗi tạo vé (DB Error)', 'debug' => $r];
}

// Lấy thông tin vé
function getTicketInfo($code) {
    if (empty($code)) return ['success' => false, 'error' => 'Thiếu mã vé'];
    
    $t = dbGetOne('tickets', 'ticket_code', strtoupper($code));
    if (!$t) return ['success' => false, 'error' => 'Không tìm thấy vé'];
    
    $pricing = getPricingSettings();
    $mins = 0;
    $amt = (int)($t['amount'] ?? 0);
    
    // Thông tin booking overstay
    $has_overstay = false;
    $overstay_minutes = 0;
    $overstay_amount = 0;
    $overstay_payment_ref = null;
    $booking_id = $t['booking_id'] ?? null;
    $is_expired = false; // Booking hết hạn mà xe chưa vào
    
    if ($t['status'] === 'ACTIVE' && $t['time_in']) {
        // Xe vãng lai chưa thanh toán
        $mins = max(1, (int)round((time() - strtotime($t['time_in'])) / 60));
        $amt = max($pricing['min_price'], (int)round($mins * $pricing['price_per_minute']));
    } elseif ($t['status'] === 'PAID' && $booking_id) {
        // Booking đã thanh toán - kiểm tra quá giờ
        $booking = dbGetOne('bookings', 'id', $booking_id);
        if ($booking && $booking['end_time']) {
            $now = time();
            $end_time = strtotime($booking['end_time']);
            
            if ($now > $end_time) {
                // Đã quá giờ booking
                $has_overstay = true;
                $overstay_minutes = (int)ceil(($now - $end_time) / 60);
                $overstay_amount = max($pricing['min_price'], (int)round($overstay_minutes * $pricing['price_per_minute']));
                
                // Tìm vehicle để check/create overstay payment
                $vehicle = dbGetOne('vehicles', 'ticket_code', strtoupper($code));
                if ($vehicle) {
                    $vehicle_id = $vehicle['id'];
                    
                    // Kiểm tra đã thanh toán phí overstay chưa
                    $db = db();
                    $stmt = $db->prepare("SELECT * FROM payments WHERE vehicle_id = ? AND status = 'completed' AND payment_ref LIKE 'OVERSTAY%'");
                    $stmt->execute([$vehicle_id]);
                    $overstay_paid = $stmt->fetchAll();

                    if (!empty($overstay_paid)) {
                        // Đã thanh toán phí overstay
                        $has_overstay = false;
                        $overstay_amount = 0;
                    } else {
                        $stmt = $db->prepare("SELECT * FROM payments WHERE vehicle_id = ? AND status = 'pending' AND payment_ref LIKE 'OVERSTAY%' LIMIT 1");
                        $stmt->execute([$vehicle_id]);
                        $pending_overstay = $stmt->fetchAll();

                        if (!empty($pending_overstay)) {
                            $overstay_payment_ref = $pending_overstay[0]['payment_ref'];
                            $overstay_amount = (int)$pending_overstay[0]['amount'];
                        }
                    }
                } else {
                    // Vehicle không tìm thấy = Xe chưa vào bãi
                    $has_overstay = false;
                    $overstay_amount = 0;
                    $is_expired = true;
                }
            }
        }
        // Lấy thời gian từ time_in đến now
        if ($t['time_in']) {
            $mins = max(1, (int)round((time() - strtotime($t['time_in'])) / 60));
        }
    } elseif ($t['time_out'] && $t['time_in']) {
        // Vé đã sử dụng
        $mins = max(1, (int)round((strtotime($t['time_out']) - strtotime($t['time_in'])) / 60));
    }
    
    return [
        'success' => true,
        'ticket' => [
            'ticket_code' => $t['ticket_code'],
            'license_plate' => $t['license_plate'],
            'status' => $t['status'],
            'time_in' => $t['time_in'],
            'time_out' => $t['time_out'],
            'minutes' => $mins,
            'amount' => $amt,
            'qr_url' => $t['qr_url'],
            'booking_id' => $booking_id,
            'has_overstay' => $has_overstay,
            'overstay_minutes' => $overstay_minutes,
            'overstay_amount' => $overstay_amount,
            'overstay_payment_ref' => $overstay_payment_ref,
            'is_expired' => $is_expired
        ]
    ];
}

// Xác thực vé khi ra
function verifyTicket($code, $plate = '') {
    if (empty($code)) return ['success' => false, 'allow_exit' => false, 'is_paid' => false, 'plate_match' => false, 'error' => 'Thiếu mã vé'];
    
    $t = dbGetOne('tickets', 'ticket_code', strtoupper($code));
    if (!$t) return ['success' => false, 'allow_exit' => false, 'is_paid' => false, 'plate_match' => false, 'error' => 'Vé không tồn tại'];
    
    // Kiểm tra BSX khớp
    $plate_match = true;
    if ($plate && strtoupper($plate) !== strtoupper($t['license_plate'])) {
        $plate_match = false;
        return [
            'success' => false, 
            'allow_exit' => false, 
            'is_paid' => ($t['status'] === 'PAID'),
            'plate_match' => false,
            'error' => 'BSX không khớp',
            'expected_plate' => $t['license_plate'],
            'scanned_plate' => strtoupper($plate)
        ];
    }
    
    if ($t['status'] === 'USED') {
        return ['success' => false, 'allow_exit' => false, 'is_paid' => true, 'plate_match' => $plate_match, 'error' => 'Vé đã sử dụng'];
    }
    
    // === XỬ LÝ BOOKING OVERSTAY ===
    $booking_id = $t['booking_id'] ?? null;
    $overstay_fee = 0;
    $overstay_minutes = 0;
    
    // Tìm vehicle theo ticket_code
    $vehicle = dbGetOne('vehicles', 'ticket_code', strtoupper($code));
    $vehicle_id = $vehicle['id'] ?? null;
    
    if ($booking_id && $t['status'] === 'PAID') {
        $booking = dbGetOne('bookings', 'id', $booking_id);
        if ($booking && $booking['end_time']) {
            $now = time();
            $end_time = strtotime($booking['end_time']);
            
            if ($now > $end_time) {
                // Xe đã quá giờ booking
                $overstay_minutes = (int)ceil(($now - $end_time) / 60);
                $pricing = getPricingSettings();
                $overstay_fee = max($pricing['min_price'], (int)round($overstay_minutes * $pricing['price_per_minute']));
                
                // Kiểm tra đã thanh toán phí phát sinh chưa
                $overstay_paid = false;
                if ($vehicle_id) {
                    $overstay_payment = dbQuery('payments', 
                        "vehicle_id=eq.$vehicle_id&status=eq.completed&payment_ref=ilike.OVERSTAY*"
                    );
                    if (!empty($overstay_payment)) {
                        $overstay_paid = true;
                    }
                }
                
                if (!$overstay_paid) {
                    // Tạo payment phí phát sinh nếu chưa có
                    if ($vehicle_id) {
                        $existing_overstay = dbQuery('payments', 
                            "vehicle_id=eq.$vehicle_id&status=eq.pending&payment_ref=ilike.OVERSTAY*"
                        );
                        
                        if (empty($existing_overstay)) {
                            // Format: OVERSTAY{timestamp}{vehicle_id} - không dấu -, không space
                            $payment_ref = 'OVERSTAY' . time() . $vehicle_id;
                            dbInsert('payments', [
                                'vehicle_id' => $vehicle_id,
                                'amount' => $overstay_fee,
                                'payment_ref' => $payment_ref,
                                'status' => 'pending',
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    
                    return [
                        'success' => false,
                        'allow_exit' => false,
                        'is_paid' => true,
                        'plate_match' => $plate_match,
                        'has_overstay' => true,
                        'overstay_minutes' => $overstay_minutes,
                        'overstay_fee' => $overstay_fee,
                        'error' => "Quá giờ {$overstay_minutes} phút. Phí phát sinh: " . number_format($overstay_fee) . "đ",
                        'amount_due' => $overstay_fee,
                        'qr_url' => (defined('SITE_URL') ? SITE_URL : '') . "/payment.php?ticket=" . $code
                    ];
                }
            }
        }
    }
    
    // === XE VÃNG LAI CHƯA THANH TOÁN ===
    if ($t['status'] !== 'PAID') {
        $info = getTicketInfo($code);
        return [
            'success' => false,
            'allow_exit' => false,
            'is_paid' => false,
            'plate_match' => $plate_match,
            'error' => 'Chưa thanh toán: ' . number_format($info['ticket']['amount'] ?? 0) . 'đ',
            'amount_due' => $info['ticket']['amount'] ?? 0,
            'qr_url' => $t['qr_url']
        ];
    }
    
    return [
        'success' => true,
        'allow_exit' => true,
        'is_paid' => true,
        'plate_match' => $plate_match,
        'message' => 'Mở barrier!',
        'ticket_code' => $t['ticket_code'],
        'license_plate' => $t['license_plate'],
        'paid_amount' => (int)$t['amount'],
        'overstay_paid' => $overstay_fee
    ];
}

// Đánh dấu đã dùng
function useTicket($code) {
    $v = verifyTicket($code);
    if (!$v['allow_exit']) return $v;
    
    dbUpdate('tickets', 'ticket_code', strtoupper($code), [
        'status' => 'USED',
        'time_out' => date('Y-m-d H:i:s')
    ]);
    
    return ['success' => true, 'message' => 'Xe ra thành công!'];
}


