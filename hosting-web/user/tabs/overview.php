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
