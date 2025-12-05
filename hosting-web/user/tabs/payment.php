<div class="card">
    <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Thanh toán QR Code</h2>

    <?php if (isset($qr_data) && isset($qr_data['expired']) && $qr_data['expired']): ?>
    <!-- HIỂN THỊ KHI QR ĐÃ HẾT HẠN -->
    <div style="text-align: center; padding: 3rem 1rem;">
        <i class="fas fa-clock" style="font-size: 4rem; color: #ef4444; margin-bottom: 1.5rem;"></i>
        <h3 style="color: #ef4444; margin-bottom: 1rem;">QR Code đã hết hạn!</h3>
        <p style="color: #666; margin-bottom: 0.5rem;">Mã thanh toán: <strong><?php echo htmlspecialchars($qr_data['payment_ref']); ?></strong></p>
        <p style="color: #666; margin-bottom: 0.5rem;">Số tiền: <strong><?php echo number_format($qr_data['amount'], 0, ',', '.'); ?>₫</strong></p>
        <p style="color: #ef4444; margin-bottom: 2rem;"><i class="fas fa-exclamation-triangle"></i> Đơn hàng này đã hết hạn. Vui lòng tạo đơn mới.</p>
        
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="#" onclick="checkSlotsAndNavigate(event)" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Đặt chỗ mới
            </a>
            <a href="dashboard.php?tab=bookings" class="btn user-page">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    <?php elseif (isset($qr_data) && $qr_data['success']): ?>
    <div class="payment-layout">
        <!-- Phần QR Code -->
        <div class="qr-section">
            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <img id="qr-code-img" src="<?php echo $qr_data['qr_code']; ?>" alt="QR Code" style="border-radius: 8px;">
            </div>

            <div class="payment-amount">
                <?php echo number_format($qr_data['amount'], 0, ',', '.'); ?>₫
            </div>

            <p style="color: #666; margin-bottom: 1rem;">Quét mã QR bằng ứng dụng ngân hàng</p>

            <!-- Download QR Button -->
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <button onclick="downloadQRCode()" class="btn" style="background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-download"></i>
                    Tải QR Code
                </button>
            </div>

            <div class="status-indicator">
                <p>Trạng thái: <span id="payment-status" class="badge badge-warning">Đang chờ thanh toán</span></p>
                <p><span class="countdown-timer" style="color: #f59e0b; font-weight: 600;">
                    Hết hạn sau: <span id="countdown-timer">--:--</span>
                </span></p>
            </div>
            
            <!-- COUNTDOWN SCRIPT - Chạy ngay lập tức -->
            <script>
            (function() {
                var timeLeft = <?php echo intval($qr_data['time_remaining'] ?? (QR_EXPIRE_MINUTES * 60)); ?>;
                var timerEl = document.getElementById('countdown-timer');
                var statusEl = document.getElementById('payment-status');
                var msgEl = document.getElementById('payment-message');
                
                function formatTime(seconds) {
                    var m = Math.floor(seconds / 60);
                    var s = seconds % 60;
                    return m + ':' + (s < 10 ? '0' : '') + s;
                }
                
                function updateTimer() {
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        timerEl.textContent = '0:00';
                        timerEl.style.color = '#ef4444';
                        
                        // Cập nhật UI khi hết hạn
                        if (statusEl) {
                            statusEl.className = 'badge badge-danger';
                            statusEl.textContent = 'Hết hạn';
                        }
                        if (msgEl) {
                            msgEl.innerHTML = '<div style="text-align:center;padding:1rem;"><i class="fas fa-times-circle" style="color:#ef4444;font-size:3rem;margin-bottom:10px;display:block;"></i><p style="color:#ef4444;font-weight:600;">QR Code đã hết hạn!</p><p style="color:#666;">Vui lòng tạo đơn đặt chỗ mới.</p></div>';
                        }
                        
                        // Redirect sau 3 giây
                        setTimeout(function() {
                            window.location.href = 'dashboard.php?tab=bookings';
                        }, 3000);
                        return;
                    }
                    
                    timerEl.textContent = formatTime(timeLeft);
                    
                    // Đổi màu theo thời gian còn lại
                    if (timeLeft <= 60) {
                        timerEl.style.color = '#ef4444'; // Đỏ - dưới 1 phút
                    } else if (timeLeft <= 180) {
                        timerEl.style.color = '#f59e0b'; // Cam - dưới 3 phút
                    } else {
                        timerEl.style.color = '#10b981'; // Xanh
                    }
                    
                    timeLeft--;
                }
                
                // Chạy ngay lập tức
                updateTimer();
                var countdownInterval = setInterval(updateTimer, 1000);
                
                // Lưu interval để có thể clear khi thanh toán thành công
                window.paymentCountdownInterval = countdownInterval;
            })();
            </script>
        </div>

        <!-- Phần thông tin trạng thái -->
        <div class="status-section">
            <h3 style="margin-bottom: 1.5rem; color: var(--primary);">
                <i class="fas fa-info-circle"></i> Thông tin thanh toán
            </h3>

            <div class="payment-details">
                <table>
                    <tr>
                        <td><strong>Mã thanh toán:</strong></td>
                        <td style="font-family: monospace; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($qr_data['reference']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Ngân hàng:</strong></td>
                        <td><?php echo htmlspecialchars($qr_data['bank_info']['bank']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Số tài khoản:</strong></td>
                        <td><?php echo htmlspecialchars($qr_data['bank_info']['account']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tên tài khoản:</strong></td>
                        <td><?php echo htmlspecialchars($qr_data['bank_info']['name']); ?></td>
                    </tr>
                </table>
            </div>

            <div
                style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 5px; margin: 1.5rem 0;">
                <p style="margin: 0; color: #92400e; font-size: 0.9rem;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Lưu ý quan trọng:</strong><br>
                    • QR Code sẽ hết hạn sau <?php echo QR_EXPIRE_MINUTES; ?> phút<br>
                    • Vui lòng thanh toán chính xác số tiền để hệ thống tự động xác nhận<br>
                    • Nội dung chuyển khoản sẽ tự động điền
                </p>
            </div>

            <div id="payment-message" style="text-align: center; margin: 1.5rem 0; color: #666;">
            </div>

            <div class="action-buttons">
                <a href="dashboard.php?tab=bookings" class="btn user-page">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>

                <button
                    onclick="confirmCancelPayment('<?php echo htmlspecialchars($qr_data['reference']); ?>')"
                    class="btn btn-danger">Hủy thanh toán</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
