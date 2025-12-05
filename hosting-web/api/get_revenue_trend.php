<?php
/**
 * GET REVENUE TREND - Tổng xu hướng doanh thu theo tháng (từ đầu đến giờ)
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';

ApiResponse::init();

// Lấy TẤT CẢ payments completed (từ đầu đến giờ)
$payments = supabaseQuery('payments', 'status=eq.completed', 'payment_time,created_at,amount');

// Group by month-year
$monthlyTotals = [];
foreach ($payments as $p) {
    $paymentDate = $p['payment_time'] ?? $p['created_at'] ?? null;
    if (!empty($paymentDate)) {
        $monthYear = date('Y-m', strtotime($paymentDate));
        if (!isset($monthlyTotals[$monthYear])) $monthlyTotals[$monthYear] = 0;
        $monthlyTotals[$monthYear] += (float)($p['amount'] ?? 0);
    }
}

// Sort theo thời gian
ksort($monthlyTotals);

// Lấy 12 tháng gần nhất (hoặc tất cả nếu < 12 tháng)
$recentMonths = array_slice($monthlyTotals, -12, 12, true);

// Format output
$labels = [];
$values = [];
foreach ($recentMonths as $monthYear => $total) {
    $labels[] = date('m/Y', strtotime($monthYear . '-01'));
    $values[] = $total;
}

// Nếu không có dữ liệu, tạo dữ liệu mẫu cho 12 tháng gần nhất
if (empty($labels)) {
    for ($i = 11; $i >= 0; $i--) {
        $labels[] = date('m/Y', strtotime("-$i months"));
        $values[] = 0;
    }
}

ApiResponse::success(['labels' => $labels, 'values' => $values]);