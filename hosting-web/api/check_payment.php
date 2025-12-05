<?php
/**
 * CHECK PAYMENT - Kiểm tra trạng thái thanh toán
 * Params: ?ticket=VE... hoặc ?ref=BOOK-...
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';
require_once __DIR__ . '/ticket_functions.php';

ApiResponse::init();

$ticket = ApiResponse::param('ticket');
$ref = ApiResponse::param('ref');

// === VÉ VÃNG LAI ===
if ($ticket) {
    $result = getTicketInfo($ticket);
    if (!$result['success']) ApiResponse::error($result['error'] ?? 'Không tìm thấy vé');
    
    $t = $result['ticket'];
    $isPaid = in_array($t['status'], ['PAID', 'USED']);
    
    ApiResponse::success([
        'status' => $isPaid ? 'completed' : 'pending',
        'message' => $isPaid ? 'Thanh toán thành công!' : 'Đang chờ thanh toán...',
        'amount' => floatval($t['amount']),
        'ticket_code' => $t['ticket_code']
    ]);
}

// === VÉ ĐẶT TRƯỚC ===
if ($ref) {
    $db = db();
    
    // Sử dụng index idx_payments_ref cho tốc độ tối ưu
    $stmt = $db->prepare("SELECT * FROM payments WHERE payment_ref = ?");
    $stmt->execute([strtoupper($ref)]);
    $payment = $stmt->fetch();
    
    if (!$payment) ApiResponse::error('Payment not found');
    
    switch ($payment['status']) {
        case 'completed':
            $ticket = null;
            if ($payment['booking_id']) {
                // Sử dụng index idx_tickets_booking
                $stmt = $db->prepare("SELECT ticket_code FROM tickets WHERE booking_id = ?");
                $stmt->execute([$payment['booking_id']]);
                $ticket = $stmt->fetch();
            }
            ApiResponse::success([
                'status' => 'completed',
                'message' => 'Thanh toán thành công!',
                'amount' => floatval($payment['amount']),
                'ticket_code' => $ticket['ticket_code'] ?? null
            ]);
            break;
            
        case 'expired':
        case 'cancelled':
        case 'failed':
            ApiResponse::success([
                'status' => 'expired',
                'message' => 'Thanh toán đã hết hạn hoặc bị hủy'
            ]);
            break;
            
        default:
            ApiResponse::success([
                'status' => 'pending',
                'message' => 'Đang chờ thanh toán...',
                'amount' => floatval($payment['amount'])
            ]);
    }
}

ApiResponse::error('Missing ticket or ref parameter');
