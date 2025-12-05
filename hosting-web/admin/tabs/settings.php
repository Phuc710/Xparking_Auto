<?php
// Lấy giá hiện tại từ settings
$price_amount_setting = dbGetOne('settings', 'key', 'price_amount');
$price_minutes_setting = dbGetOne('settings', 'key', 'price_minutes');
$min_price_setting = dbGetOne('settings', 'key', 'min_price');

$current_price_amount = $price_amount_setting ? intval($price_amount_setting['value']) : 5000;
$current_price_minutes = $price_minutes_setting ? intval($price_minutes_setting['value']) : 60;
$current_min_price = $min_price_setting ? intval($min_price_setting['value']) : 5000;

// Tính giá/giờ để hiển thị
$hourly_display = ($current_price_minutes > 0) ? round($current_price_amount * 60 / $current_price_minutes) : $current_price_amount;
?>
<div class="card">
    <h2 class="card-title"><i class="fas fa-cogs"></i> Cài đặt hệ thống</h2>

    <div style="background: #f8fafc; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div>
                <strong>Thời gian hệ thống:</strong>
                <p id="clock" style="color: var(--primary); font-family: monospace; font-size: 1.1rem;">Đang tải...</p>
            </div>
            <div>
                <strong>Phát triển bởi:</strong>
                <p>PHUCX</p>
            </div>
        </div>
    </div>

    <!-- Cài đặt giá vé -->
    <div style="background: #fff; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; border: 2px solid #10b981;">
        <h3 style="margin-bottom: 1rem; color: #10b981;">
            <i class="fas fa-money-bill-wave"></i> Cài đặt giá vé (Booking & Vãng lai)
        </h3>

        <form action="admin.php?tab=settings" method="post">
            <input type="hidden" name="action" value="update_pricing">
            
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div class="form-group" style="flex: 1; min-width: 120px;">
                    <label for="price_amount" class="form-label">Số tiền (VNĐ)</label>
                    <input type="number" id="price_amount" name="price_amount" class="form-control"
                        value="<?php echo $current_price_amount; ?>" min="1000" max="500000" step="1000" required>
                </div>
                
                <div style="padding-bottom: 0.75rem; font-size: 1.5rem; font-weight: bold; color: #6b7280;">÷</div>
                
                <div class="form-group" style="flex: 1; min-width: 100px;">
                    <label for="price_minutes" class="form-label">Số phút</label>
                    <input type="number" id="price_minutes" name="price_minutes" class="form-control"
                        value="<?php echo $current_price_minutes; ?>" min="1" max="1440" required>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label for="min_price" class="form-label">Giá tối thiểu (VNĐ)</label>
                    <input type="number" id="min_price" name="min_price" class="form-control"
                        value="<?php echo $current_min_price; ?>" min="1000" max="50000" step="1000" required>
                </div>
            </div>

            <div style="margin-top: 1rem; padding: 1rem; background: #f0fdf4; border-radius: 8px;">
                <p style="margin: 0; color: #166534;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Giá hiện tại:</strong> 
                    <?php echo number_format($current_price_amount, 0, ',', '.'); ?>₫ / <?php echo $current_price_minutes; ?> phút
                    (≈ <strong><?php echo number_format($hourly_display, 0, ',', '.'); ?>₫/giờ</strong>) | 
                    Tối thiểu: <strong><?php echo number_format($current_min_price, 0, ',', '.'); ?>₫</strong>
                </p>
            </div>

            <button type="submit" class="btn btn-success" style="margin-top: 1rem;">
                <i class="fas fa-save"></i> Lưu cài đặt giá
            </button>
        </form>
    </div>

    <div style="background: #fff; padding: 1.5rem; border-radius: 10px;">
        <h3 style="margin-bottom: 1rem;">
            <i class="fas fa-bullhorn"></i> Gửi thông báo
        </h3>

        <form action="admin.php?tab=settings" method="post">
            <input type="hidden" name="action" value="send_notification">

            <div class="form-group">
                <label for="notification_title" class="form-label">Tiêu đề</label>
                <input type="text" id="notification_title" name="notification_title" class="form-control"
                    maxlength="255">
            </div>

            <div class="form-group">
                <label for="notification_message" class="form-label">Nội dung</label>
                <textarea id="notification_message" name="notification_message" class="form-control"
                    rows="5" required placeholder="Nhập nội dung chi tiết của thông báo..."></textarea>
            </div>

            <div class="form-group">
                <label for="notification_type" class="form-label">Loại</label>
                <select id="notification_type" name="notification_type" class="form-control" required>
                    <option value="info">Normal</option>
                    <option value="warning">Cảnh báo</option>
                    <option value="error">Khẩn cấp</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="fas fa-paper-plane"></i> Gửi thông báo
            </button>
        </form>

        <?php 
                $current_notification = null;
                try {
                    $notifications = dbQuery('notifications', 'order=created_at.desc&limit=1');
                    $current_notification = $notifications[0] ?? null;
                } catch (Exception $e) {
                    // Ignore error
                }
                ?>

        <?php if ($current_notification): ?>
        <div
            style="margin-top: 25px; padding: 20px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin: 0; color: #374151; font-size: 1rem;">
                    Thông báo hiện tại:
                </h4>
                <form action="admin.php?tab=settings" method="post" style="margin: 0;"
                    onsubmit="confirmDelete(event)">
                    <input type="hidden" name="action" value="delete_notification">
                    <button type="submit" class="btn btn-danger"
                        style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                        <i class="fas fa-trash-alt"></i> Xóa
                    </button>
                </form>
            </div>

            <div
                style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #3b82f6;">
                <h5 style="margin: 0 0 8px 0; color: #1f2937; font-weight: 600;">
                    <?php echo htmlspecialchars($current_notification['title']); ?>
                </h5>
                <p style="margin: 0 0 10px 0; color: #4b5563; line-height: 1.4;">
                    <?php echo nl2br(htmlspecialchars($current_notification['message'])); ?>
                </p>
                <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">
                    Gửi lúc:
                    <span id="notification-time"
                        data-timestamp="<?php echo htmlspecialchars($current_notification['created_at']); ?>">
                        <?php echo date('d/m/Y - H:i', strtotime($current_notification['created_at'])); ?>
                    </span>
                    <span style="margin-left: 15px;">
                        <?php 
                                $typeText = 'Loại: Normal';
                                if ($current_notification['type'] === 'warning') $typeText = 'Loại: Cảnh báo';
                                if ($current_notification['type'] === 'error') $typeText = 'Loại: Khẩn cấp';
                                echo $typeText;
                                ?>
                    </span>
                </p>
            </div>
        </div>
        <?php else: ?>
        <div
            style="margin-top: 25px; padding: 20px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
            <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 1rem;">
                Thông báo hiện tại:
            </h4>
            <div
                style="background: white; padding: 15px; border-radius: 8px; text-align: center; color: #6b7280;">
                <i class="fas fa-bell-slash" style="font-size: 2rem; color: #d1d5db;margin-top: 20px;"></i>
                <p style="margin: 30px;">Không có thông báo nào</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(event) {
    // Ngăn form submit mặc định
    event.preventDefault();

    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Bạn có chắc chắn muốn xóa tất cả thông báo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Xóa',
        cancelButtonText: 'Hủy',
        allowOutsideClick: false,
        background: '#ffffff',
        color: '#374151'
    }).then((result) => {
        if (result.isConfirmed) {
            // Thực hiện submit form
            event.target.closest('form').submit();
        }
    });

    return false; // Đảm bảo ngăn form submit
}
</script>
