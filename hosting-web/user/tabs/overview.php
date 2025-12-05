<div class="card">
    <h2 class="card-title"><i class="fas fa-tachometer-alt"></i> Tổng quan</h2>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?php echo $user_stats['active_bookings']; ?></div>
            <div class="stat-label">Đặt chỗ hiện tại</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-value"><?php echo $user_stats['total_parkings']; ?></div>
            <div class="stat-label">Lần đỗ xe</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $user_stats['total_hours']; ?></div>
            <div class="stat-label">Tổng giờ đã book</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value"><?php echo number_format($user_stats['total_spent'], 0, ',', '.'); ?>₫
            </div>
            <div class="stat-label">Tổng chi phí</div>
        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><i class="fas fa-parking"></i> Tình trạng bãi đỗ xe</h2>

    <div class="slot-grid">
        <?php 
                foreach ($slots as $slot): 
                    $statusClass = '';
                    $statusText = '';
                    $statusColor = '';
                    $details = '';
                    
                    // Sử dụng actual_status từ query đã tối ưu
                    switch ($slot['actual_status']) {
                        case 'empty':
                            $statusClass = 'success';
                            $statusText = 'Trống';
                            $statusColor = '#10b981';
                            break;
                            
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
            <?php if ($details): ?>
            <div class="slot-details"><?php echo htmlspecialchars($details); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
