<div class="card">
    <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ</h2>
    
    <!-- Booking Filter Section -->
    <div class="filter-section" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Trạng thái đặt chỗ</label>
                <select id="bookingStatusFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ xác nhận</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="completed">Đã hoàn thành</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Trạng thái thanh toán</label>
                <select id="paymentStatusFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả thanh toán</option>
                    <option value="pending">Chờ thanh toán</option>
                    <option value="completed">Đã thanh toán</option>
                    <option value="failed">Thất bại</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Từ ngày</label>
                <input type="date" id="bookingDateFrom" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Đến ngày</label>
                <input type="date" id="bookingDateTo" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="applyBookingFilters()" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;"> Lọc</button>
                <button onclick="clearBookingFilters()" class="btn" style="background: #6b7280; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;"> Reset</button>
            </div>
        </div>
    </div>

    <?php if (empty($bookings)): ?>
    <div style="text-align: center; padding: 2rem 0;">
        <i class="fas fa-calendar-times"
            style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p>Không có đặt chỗ nào</p>
    </div>
    <?php else: ?>
    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người dùng</th>
                    <th>Biển số</th>
                    <th>Thời gian bắt đầu</th>
                    <th>Thời gian kết thúc</th>
                    <th>Trạng thái đặt chỗ</th>
                    <th>Trạng thái thanh toán</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): 
                            $bookingStatusClass = '';
                            $paymentStatusClass = '';
                            
                            switch ($booking['status']) {
                                case 'pending':
                                    $bookingStatusClass = 'warning';
                                    $bookingStatusText = 'Chờ xác nhận';
                                    break;
                                case 'confirmed':
                                    $bookingStatusClass = 'success';
                                    $bookingStatusText = 'Đã xác nhận';
                                    break;
                                case 'cancelled':
                                    $bookingStatusClass = 'danger';
                                    $bookingStatusText = 'Đã hủy';
                                    break;
                                case 'completed':
                                    $bookingStatusClass = 'info';
                                    $bookingStatusText = 'Đã hoàn thành';
                                    break;
                                default:
                                    $bookingStatusClass = 'warning';
                                    $bookingStatusText = 'Chờ xác nhận';
                            }
                            
                            switch ($booking['payment_status']) {
                                case 'pending':
                                    $paymentStatusClass = 'warning';
                                    $paymentStatusText = 'Chờ thanh toán';
                                    break;
                                case 'completed':
                                    $paymentStatusClass = 'success';
                                    $paymentStatusText = 'Đã thanh toán';
                                    break;
                                case 'failed':
                                    $paymentStatusClass = 'danger';
                                    $paymentStatusText = 'Thanh toán thất bại';
                                    break;
                                case 'expired':
                                    $paymentStatusClass = 'danger';
                                    $paymentStatusText = 'Hết hạn';
                                    break;
                                default:
                                    $paymentStatusClass = 'warning';
                                    $paymentStatusText = 'Chờ thanh toán';
                            }
                        ?>
                <tr>
                    <td><?php echo $booking['id']; ?></td>
                    <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($booking['end_time'])); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $bookingStatusClass; ?>">
                            <?php echo $bookingStatusText; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($booking['status'] === 'cancelled'): ?>
                        <span class="badge badge-danger">Đã hủy</span>
                        <?php else: ?>
                        <span class="badge badge-<?php echo $paymentStatusClass; ?>">
                            <?php echo $paymentStatusText; ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($booking['amount'], 0, ',', '.'); ?>₫</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card Layout -->
    <div class="mobile-card-list">
        <?php foreach ($bookings as $booking): 
                    $bookingStatusClass = '';
                    $paymentStatusClass = '';
                    
                    switch ($booking['status']) {
                        case 'pending':
                            $bookingStatusClass = 'warning';
                            $bookingStatusText = 'Chờ xác nhận';
                            break;
                        case 'confirmed':
                            $bookingStatusClass = 'success';
                            $bookingStatusText = 'Đã xác nhận';
                            break;
                        case 'cancelled':
                            $bookingStatusClass = 'danger';
                            $bookingStatusText = 'Đã hủy';
                            break;
                        case 'completed':
                            $bookingStatusClass = 'info';
                            $bookingStatusText = 'Đã hoàn thành';
                            break;
                        default:
                            $bookingStatusClass = 'warning';
                            $bookingStatusText = 'Chờ xác nhận';
                    }
                    
                    switch ($booking['payment_status']) {
                        case 'pending':
                            $paymentStatusClass = 'warning';
                            $paymentStatusText = 'Chờ thanh toán';
                            break;
                        case 'completed':
                            $paymentStatusClass = 'success';
                            $paymentStatusText = 'Đã thanh toán';
                            break;
                        case 'failed':
                            $paymentStatusClass = 'danger';
                            $paymentStatusText = 'Thanh toán thất bại';
                            break;
                        case 'expired':
                            $paymentStatusClass = 'danger';
                            $paymentStatusText = 'Hết hạn';
                            break;
                        default:
                            $paymentStatusClass = 'warning';
                            $paymentStatusText = 'Chờ thanh toán';
                    }
                ?>
        <div class="mobile-card">
            <div class="mobile-card-header">
                <span><i class="fas fa-calendar"></i> Booking #<?php echo $booking['id']; ?></span>
                <span
                    class="badge badge-<?php echo $bookingStatusClass; ?>"><?php echo $bookingStatusText; ?></span>
            </div>
            <div class="mobile-card-content">
                <div class="mobile-card-row">
                    <span>Người dùng:</span>
                    <span><?php echo htmlspecialchars($booking['full_name']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Biển số:</span>
                    <span><?php echo htmlspecialchars($booking['license_plate']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Thời gian:</span>
                    <span><?php echo date('d/m H:i', strtotime($booking['start_time'])); ?> -
                        <?php echo date('H:i', strtotime($booking['end_time'])); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Thanh toán:</span>
                    <span
                        class="badge badge-<?php echo $paymentStatusClass; ?>"><?php echo $paymentStatusText; ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Thành tiền:</span>
                    <span><strong><?php echo number_format($booking['amount'], 0, ',', '.'); ?>₫</strong></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    // Filter Functions for Bookings
    window.bookingFilterData = <?php echo json_encode($bookings ?? []); ?>;
    
    function applyBookingFilters() {
        const bookingStatus = document.getElementById('bookingStatusFilter')?.value || '';
        const paymentStatus = document.getElementById('paymentStatusFilter')?.value || '';
        const dateFrom = document.getElementById('bookingDateFrom')?.value || '';
        const dateTo = document.getElementById('bookingDateTo')?.value || '';
        
        let filtered = window.bookingFilterData.filter(booking => {
            if (bookingStatus && booking.status !== bookingStatus) {
                return false;
            }
            if (paymentStatus && booking.payment_status !== paymentStatus) {
                return false;
            }
            if (dateFrom || dateTo) {
                const bookingDate = new Date(booking.start_time);
                if (dateFrom && bookingDate < new Date(dateFrom)) return false;
                if (dateTo && bookingDate > new Date(dateTo)) return false;
            }
            return true;
        });
        
        updateBookingDisplay(filtered);
        showFilterResults(filtered.length, window.bookingFilterData.length, 'đặt chỗ');
    }
    
    function clearBookingFilters() {
        ['bookingStatusFilter', 'paymentStatusFilter', 'bookingDateFrom', 'bookingDateTo']
            .forEach(id => {
                const elem = document.getElementById(id);
                if (elem) elem.value = '';
            });
        updateBookingDisplay(window.bookingFilterData);
        hideFilterResults();
    }
    
    function updateBookingDisplay(filteredData) {
        const tbody = document.querySelector('.table-responsive tbody');
        if (tbody && filteredData.length > 0) {
            tbody.innerHTML = filteredData.map(booking => {
                let bookingStatusClass = 'warning', bookingStatusText = 'Chờ xác nhận';
                let paymentStatusClass = 'warning', paymentStatusText = 'Chờ thanh toán';
                
                switch (booking.status) {
                    case 'confirmed': bookingStatusClass = 'success'; bookingStatusText = 'Đã xác nhận'; break;
                    case 'cancelled': bookingStatusClass = 'danger'; bookingStatusText = 'Đã hủy'; break;
                    case 'completed': bookingStatusClass = 'info'; bookingStatusText = 'Đã hoàn thành'; break;
                }
                
                switch (booking.payment_status) {
                    case 'completed': paymentStatusClass = 'success'; paymentStatusText = 'Đã thanh toán'; break;
                    case 'failed': paymentStatusClass = 'danger'; paymentStatusText = 'Thất bại'; break;
                    case 'expired': paymentStatusClass = 'danger'; paymentStatusText = 'Hết hạn'; break;
                }
                
                const startDate = new Date(booking.start_time);
                const endDate = new Date(booking.end_time);
                
                return `
                    <tr>
                        <td>${booking.id}</td>
                        <td>${booking.full_name || 'N/A'}</td>
                        <td>${booking.license_plate}</td>
                        <td>${startDate.toLocaleDateString('vi-VN')} ${startDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})}</td>
                        <td>${endDate.toLocaleDateString('vi-VN')} ${endDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})}</td>
                        <td><span class="badge badge-${bookingStatusClass}">${bookingStatusText}</span></td>
                        <td>${booking.status === 'cancelled' ? '<span class="badge badge-danger">Đã hủy</span>' : `<span class="badge badge-${paymentStatusClass}">${paymentStatusText}</span>`}</td>
                        <td>${Number(booking.amount).toLocaleString()}₫</td>
                    </tr>
                `;
            }).join('');
        }
    }
</script>
