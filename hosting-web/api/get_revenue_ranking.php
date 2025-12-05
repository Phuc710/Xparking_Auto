<?php
/**
 * GET REVENUE RANKING - Tổng doanh thu theo phương thức thanh toán (từ đầu đến giờ)
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

// Lấy TẤT CẢ payments completed (từ đầu đến giờ)
$payments = supabaseQuery('payments', 'status=eq.completed', 'payment_method,amount');

// Phân loại theo phương thức thanh toán
$mobilePayment = 0;    // Thanh toán di động (VietQR, MoMo, etc.)
$cashPayment = 0;      // Tiền mặt
$cardPayment = 0;      // Thẻ ngân hàng

foreach ($payments as $p) {
    $amount = (float)($p['amount'] ?? 0);
    $method = strtolower($p['payment_method'] ?? 'mobile');
    
    // Phân loại dựa trên payment_method
    if (in_array($method, ['cash', 'tien_mat'])) {
        $cashPayment += $amount;
    } elseif (in_array($method, ['card', 'atm', 'visa', 'mastercard'])) {
        $cardPayment += $amount;
    } else {
        // Mặc định là mobile payment (VietQR, MoMo, ZaloPay, etc.)
        $mobilePayment += $amount;
    }
}

// Nếu không có dữ liệu payment_method, phân bổ theo tỷ lệ ước tính
$totalPayments = count($payments);
if ($mobilePayment == 0 && $cashPayment == 0 && $cardPayment == 0 && $totalPayments > 0) {
    $totalAmount = array_sum(array_column($payments, 'amount'));
    $mobilePayment = $totalAmount * 0.70;  // 70% mobile
    $cashPayment = $totalAmount * 0.25;    // 25% cash
    $cardPayment = $totalAmount * 0.05;    // 5% card
}

// Format output
$labels = ['📱 Thanh toán di động', '💵 Tiền mặt', '💳 Thẻ ngân hàng'];
$values = [$mobilePayment, $cashPayment, $cardPayment];

ApiResponse::success(['labels' => $labels, 'values' => $values]);