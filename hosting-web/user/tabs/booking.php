<?php
// Lấy giá từ settings
$price_amount_s = dbGetOne('settings', 'key', 'price_amount');
$price_minutes_s = dbGetOne('settings', 'key', 'price_minutes');
$booking_price_amount = $price_amount_s ? intval($price_amount_s['value']) : 5000;
$booking_price_minutes = $price_minutes_s ? intval($price_minutes_s['value']) : 60;
$hourly_price = ($booking_price_minutes > 0) ? round($booking_price_amount * 60 / $booking_price_minutes) : 5000;

// Kiểm tra còn slot trống không
$has_available = count($available_slots) > 0;
?>
<div class="card">
    <h2 class="card-title"><i class="fas fa-calendar-plus"></i> Đặt chỗ mới</h2>

    <?php if (!$has_available): ?>
    <!-- Hết slot -->
    <div style="text-align: center; padding: 3rem 1rem;">
        <i class="fas fa-parking" style="font-size: 4rem; color: #ef4444; margin-bottom: 1rem;"></i>
        <h3 style="color: #ef4444; margin-bottom: 0.5rem;">HẾT CHỖ ĐỖ XE!</h3>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">Xin lỗi quý khách, hiện tại bãi xe đã đầy.<br>Vui lòng quay lại sau!</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Về trang chủ</a>
            <button onclick="location.reload()" class="btn" style="background: #6b7280; color: white;">
                <i class="fas fa-sync"></i> Thử lại
            </button>
        </div>
    </div>
    <?php else: ?>

    <form action="dashboard.php?tab=booking" method="post">
        <input type="hidden" name="action" value="create_booking">

        <div class="form-group">
            <label for="license_plate" class="form-label">Biển số xe</label>
            <input type="text" id="license_plate" name="license_plate" class="form-control"
                placeholder="VD: 77A12345" maxlength="15" required
                style="text-transform: uppercase;">
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="start_date" class="form-label">Ngày đặt</label>
                <input type="date" id="start_date" name="start_date" class="form-control"
                    min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="start_time" class="form-label">Giờ đặt</label>
                <input type="time" id="start_time" name="start_time" class="form-control"
                    value="<?php echo date('H:i'); ?>" required>
            </div>

            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="duration" class="form-label">Thời gian đỗ (giờ)</label>
                <input type="number" id="duration" name="duration" class="form-control" min="1" max="24"
                    value="1" required onchange="updatePrice()">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Giá dự kiến</label>
            <div id="estimated_price" class="payment-amount"><?php echo number_format($hourly_price, 0, ',', '.'); ?>₫</div>
            <p style="color: #6b7280;">Giá: <?php echo number_format($hourly_price, 0, ',', '.'); ?>₫/giờ</p>
        </div>

        <button type="submit" class="btn btn-primary" id="btnSubmit">
            <i class="fas fa-calendar-check"></i> Đặt chỗ ngay
        </button>
    </form>

    <script>
    const HOURLY_PRICE = <?php echo $hourly_price; ?>;
    const API_URL = '<?php echo SITE_URL; ?>/api/slots_status.php';
    
    function updatePrice() {
        const duration = parseInt(document.getElementById('duration').value) || 1;
        const total = duration * HOURLY_PRICE;
        document.getElementById('estimated_price').textContent = total.toLocaleString('vi-VN') + '₫';
    }
    
    // Real-time check slots khi submit
    document.querySelector('form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang kiểm tra...';
        
        try {
            const res = await fetch(API_URL + '?_t=' + Date.now());
            const data = await res.json();
            
            if (!data.success || data.available === 0) {
                alert('❌ Bãi xe đã đầy! Không còn chỗ trống.\nVui lòng thử lại sau.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-calendar-check"></i> Đặt chỗ ngay';
                return;
            }
            
            // Có slot trống → submit form
            this.submit();
        } catch (err) {
            console.error(err);
            // Nếu API lỗi, vẫn submit để backend check
            this.submit();
        }
    });
    </script>
    <?php endif; ?>
</div>
