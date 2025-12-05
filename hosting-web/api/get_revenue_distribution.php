<?php
/**
 * GET REVENUE DISTRIBUTION - Tổng doanh thu theo loại giao dịch (từ đầu đến giờ)
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

// Lấy TẤT CẢ payments completed (từ đầu đến giờ)
$payments = supabaseQuery('payments', 'status=eq.completed', 'payment_ref,amount');

// Phân loại doanh thu theo loại giao dịch
$bookingRevenue = 0;    // Đặt chỗ trước
$walkinRevenue = 0;     // Vãng lai
$overstayRevenue = 0;   // Phí quá giờ

foreach ($payments as $p) {
    $amount = (float)($p['amount'] ?? 0);
    $ref = $p['payment_ref'] ?? '';
    
    if (strpos($ref, 'BOOK') !== false) {
        $bookingRevenue += $amount;
    } elseif (strpos($ref, 'OVERSTAY') !== false) {
        $overstayRevenue += $amount;
    } else {
        $walkinRevenue += $amount;
    }
}

// Format output cho doughnut chart
$labels = [' Đặt chỗ trước', ' Vãng lai', ' Phí quá giờ'];
$values = [$bookingRevenue, $walkinRevenue, $overstayRevenue];

ApiResponse::success(['labels' => $labels, 'values' => $values]);