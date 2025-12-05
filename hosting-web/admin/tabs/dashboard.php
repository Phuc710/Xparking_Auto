<div class="card">
    <h2 class="card-title"><i class="fas fa-tachometer-alt"></i> Tổng quan</h2>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-value"><?php echo count($active_vehicles); ?></div>
            <div class="stat-label">Xe đang đỗ</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-parking"></i>
            </div>
            <?php
                    $available_count = 0;
                    foreach ($slots as $slot) {
                        if ($slot['actual_status'] === 'empty') {
                            $available_count++;
                        }
                    }
                    ?>
            <div class="stat-value"><?php echo $available_count; ?>/<?php echo count($slots); ?></div>
            <div class="stat-label">Slot trống</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <?php
                    $confirmedBookings = dbQuery('bookings', 'status=eq.confirmed');
                    $booking_count = count($confirmedBookings);
                    ?>
            <div class="stat-value"><?php echo $booking_count; ?></div>
            <div class="stat-label">Đặt chỗ hiện tại</div>

        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><i class="fas fa-parking"></i> Tình trạng bãi đỗ xe</h2>

    <div class="slot-grid">
        <?php foreach ($slots as $slot): 
                    $statusClass = '';
                    $statusText = '';
                    $statusColor = '';
                    
                    switch ($slot['actual_status']) {
                        case 'occupied':
                            $statusClass = 'danger';
                            $statusText = 'Có xe';
                            $statusColor = '#ef4444';
                            break;
                        case 'maintenance':
                            $statusClass = 'secondary';
                            $statusText = 'Bảo trì';
                            $statusColor = '#6b7280';
                            break;
                        default:
                            $statusClass = 'success';
                            $statusText = 'Trống';
                            $statusColor = '#10b981';
                    }
                ?>
        <div class="slot-card">
            <div class="slot-icon">
                <i class="fas fa-car" style="color: <?php echo $statusColor; ?>"></i>
            </div>
            <div class="slot-id"><?php echo htmlspecialchars($slot['id']); ?></div>
            <div class="slot-status">
                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Total Revenue Chart on Dashboard -->
<div class="card">
    <h2 class="card-title"><i class="fas fa-chart-pie"></i> Tổng Doanh Thu Hệ Thống</h2>
    <?php
    // Lấy tổng doanh thu toàn hệ thống
    $completedPayments = dbQuery('payments', 'status=eq.completed');
    $totalSystemRevenue = 0;
    $bookingRevenue = 0;
    $walkinRevenue = 0;
    $overstayRevenue = 0;
    
    foreach ($completedPayments as $p) {
        $amount = floatval($p['amount']);
        $totalSystemRevenue += $amount;
        
        // Phân loại doanh thu (demo logic - có thể cải thiện)
        if (strpos($p['payment_ref'], 'BOOK') !== false) {
            $bookingRevenue += $amount;
        } elseif (strpos($p['payment_ref'], 'OVERSTAY') !== false) {
            $overstayRevenue += $amount;
        } else {
            $walkinRevenue += $amount;
        }
    }
    
    $dashboard_revenue_data = [
        'booking' => $bookingRevenue,
        'walkin' => $walkinRevenue,
        'overstay' => $overstayRevenue,
        'total' => $totalSystemRevenue
    ];
    ?>
    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding: 1rem; background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border-radius: 12px; border: 1px solid #bbf7d0;">
        <span><strong>💰 Tổng doanh thu toàn hệ thống:</strong></span>
        <span style="color: #059669; font-weight: bold; font-size: 1.3rem;"><?php echo number_format($totalSystemRevenue, 0, ',', '.'); ?>₫</span>
    </div>
    <div style="height: 300px;">
        <canvas id="dashboardRevenueChart"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dashboardRevenueChart');
    if (ctx) {
        const revenueData = <?php echo json_encode($dashboard_revenue_data); ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['💎 Đặt chỗ trước', '🚶 Vãng lai', '⏰ Phí quá giờ'],
                datasets: [{
                    data: [
                        revenueData.booking || (revenueData.total * 0.65),
                        revenueData.walkin || (revenueData.total * 0.30),
                        revenueData.overstay || (revenueData.total * 0.05)
                    ],
                    backgroundColor: [
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderWidth: 4,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 25,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 14,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const percentage = ((context.parsed / revenueData.total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toLocaleString('vi-VN') + '₫ (' + percentage + '%)';
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });
    }
});
</script>
