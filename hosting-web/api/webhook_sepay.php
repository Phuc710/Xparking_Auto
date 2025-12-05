<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/csdl.php';

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function webhook_log($msg, $data = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    
    $log = "$logDir/webhook_sepay.log";
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $entry .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($log, $entry . "\n---\n", FILE_APPEND);
}

function respond($success, $msg, $code = 200) {
    http_response_code($code);
    exit(json_encode(['success' => $success, 'message' => $msg]));
}

// === VALIDATE REQUEST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed', 405);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
webhook_log('Received', $data);

if (!$data || !isset($data['transferAmount'], $data['content'])) respond(false, 'Invalid data', 400);
if (($data['transferType'] ?? '') !== 'in') respond(true, 'Skipped: not incoming');

// === PARSE DATA ===
$transId = $data['id'] ?? null;
$amount = (int)$data['transferAmount'];
$content = strtoupper($data['content']);

// Kiểm tra trùng lặp giao dịch
if ($transId) {
    $exists = dbQuery('webhook_payments', "sepay_transaction_id=eq.$transId");
    if ($exists && count($exists) > 0) {
        webhook_log('Duplicate: ' . $transId);
        respond(true, 'Duplicate');
    }
}

// === TÌM MÃ TRONG NỘI DUNG ===
$ticket_code = null;
$payment_code = null;
$overstay_code = null;

// 1. Vé vãng lai: VE + 8 hex (VE1A2B3C4D)
if (preg_match('/VE([A-F0-9]{8})/i', $content, $m)) {
    $ticket_code = 'VE' . strtoupper($m[1]);
}

if (preg_match('/BOOKS(\d{10})(\d+)/i', $content, $m)) {
    $payment_code = 'BOOKS' . $m[1] . $m[2];
}

// 3. Phí quá giờ: OVERSTAY1234567890123 (không space, không dấu -)
if (preg_match('/OVERSTAY(\d{10})(\d+)/i', $content, $m)) {
    $overstay_code = 'OVERSTAY' . $m[1] . $m[2];
}

if (!$ticket_code && !$payment_code && !$overstay_code) {
    webhook_log('No code found: ' . $content);
    respond(true, 'No code found');
}

webhook_log("Codes: ticket=$ticket_code, payment=$payment_code, overstay=$overstay_code");

// === XỬ LÝ VÉ VÃNG LAI ===
if ($ticket_code) {
    $ticket = dbGetOne('tickets', 'ticket_code', $ticket_code);
    
    if (!$ticket || !in_array($ticket['status'], ['ACTIVE', 'PENDING'])) {
        webhook_log("Ticket invalid: $ticket_code");
        respond(true, 'Ticket invalid');
    }
    
    // Cập nhật ticket
    dbUpdate('tickets', 'ticket_code', $ticket_code, [
        'status' => 'PAID',
        'amount' => $amount,
        'paid_at' => date('Y-m-d H:i:s'),
        'transaction_id' => $transId
    ]);
    
    // Log webhook
    dbInsert('webhook_payments', [
        'sepay_transaction_id' => $transId,
        'reference' => $content,
        'ticket_code' => $ticket_code,
        'amount' => $amount,
        'status' => 'completed',
        'payload' => $raw
    ]);
    
    webhook_log("Ticket PAID: $ticket_code, amount=$amount");
    respond(true, 'Ticket paid');
}

// === XỬ LÝ VÉ ĐẶT TRƯỚC ===
if ($payment_code) {
    $payment = dbGetOne('payments', 'payment_ref', $payment_code);
    
    if (!$payment) {
        webhook_log("Payment not found: $payment_code");
        respond(true, 'Payment not found');
    }
    
    if ($payment['status'] === 'completed') {
        webhook_log("Already completed: $payment_code");
        respond(true, 'Already completed');
    }
    
    // Kiểm tra số tiền
    if ($amount < (int)$payment['amount']) {
        webhook_log("Amount mismatch: need {$payment['amount']}, got $amount");
        respond(true, 'Amount mismatch');
    }
    
    // 1. Cập nhật payment
    dbUpdate('payments', 'id', $payment['id'], [
        'status' => 'completed',
        'paid_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // 2. Cập nhật booking
    if ($payment['booking_id']) {
        dbUpdate('bookings', 'id', $payment['booking_id'], [
            'status' => 'confirmed',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // 3. Tạo ticket cho booking (nếu chưa có)
        $existing_ticket = dbGetOne('tickets', 'booking_id', $payment['booking_id']);
        if (!$existing_ticket) {
            $booking = dbGetOne('bookings', 'id', $payment['booking_id']);
            if ($booking) {
                // Gen ticket code: VE + 8 hex (uppercase, không dấu gạch ngang)
                $new_ticket = 'VE' . strtoupper(bin2hex(random_bytes(4)));
                $qr_url = (defined('SITE_URL') ? SITE_URL : '') . '/payment.php?ticket=' . $new_ticket;
                
                dbInsert('tickets', [
                    'ticket_code' => $new_ticket,
                    'booking_id' => $booking['id'],
                    'license_plate' => $booking['license_plate'],
                    'time_in' => $booking['start_time'],
                    'qr_url' => $qr_url,
                    'status' => 'PAID',
                    'amount' => $amount,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'transaction_id' => $transId
                ]);
                
                // 4. Thông báo
                dbInsert('notifications', [
                    'user_id' => $booking['user_id'],
                    'title' => 'Thanh toán thành công',
                    'message' => "Đặt chỗ {$booking['slot_id']} đã xác nhận. Mã vé: $new_ticket",
                    'type' => 'success',
                    'is_read' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                webhook_log("Booking ticket created: $new_ticket");
            }
        }
    }
    
    // 5. Log webhook
    dbInsert('webhook_payments', [
        'sepay_transaction_id' => $transId,
        'reference' => $content,
        'payment_id' => $payment['id'],
        'amount' => $amount,
        'status' => 'completed',
        'payload' => $raw
    ]);
    
    webhook_log("Booking PAID: $payment_code");
    respond(true, 'Payment processed');
}

// === XỬ LÝ PHÍ QUÁ GIỜ ===
if ($overstay_code) {
    // Parse payment_ref để tìm (hỗ trợ cả format cũ và mới)
    $payment = dbGetOne('payments', 'payment_ref', $overstay_code);
    
    // Nếu không tìm thấy, thử format cũ
    if (!$payment && strpos($overstay_code, 'OVERSTAY') === 0) {
        $old_format = str_replace('OVERSTAY', 'OVERSTAY-', $overstay_code);
        $old_format = preg_replace('/(\d{10})(\d+)/', '$1-$2', substr($old_format, 9));
        $old_format = 'OVERSTAY-' . $old_format;
        $payment = dbGetOne('payments', 'payment_ref', $old_format);
    }
    
    if (!$payment) {
        webhook_log("Overstay payment not found: $overstay_code");
        respond(true, 'Overstay payment not found');
    }
    
    if ($payment['status'] === 'completed') {
        webhook_log("Overstay already completed: $overstay_code");
        respond(true, 'Already completed');
    }
    
    // Kiểm tra số tiền
    if ($amount < (int)$payment['amount']) {
        webhook_log("Overstay amount mismatch: need {$payment['amount']}, got $amount");
        respond(true, 'Amount mismatch');
    }
    
    // 1. Cập nhật payment
    dbUpdate('payments', 'id', $payment['id'], [
        'status' => 'completed',
        'payment_time' => date('Y-m-d H:i:s'),
        'paid_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // 2. Log webhook
    dbInsert('webhook_payments', [
        'sepay_transaction_id' => $transId,
        'reference' => $content,
        'payment_id' => $payment['id'],
        'amount' => $amount,
        'status' => 'completed',
        'payload' => $raw
    ]);
    
    webhook_log("Overstay PAID: $overstay_code, vehicle_id={$payment['vehicle_id']}");
    respond(true, 'Overstay payment processed');
}

respond(true, 'No action taken');
