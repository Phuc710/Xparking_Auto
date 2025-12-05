<div class="card">
    <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ</h2>

    <?php if (empty($bookings)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <p>Bạn chưa có lịch sử đặt chỗ nào</p>
        <a href="#" onclick="checkSlotsAndNavigate(event)" class="btn btn-primary" style="margin-top: 1rem;">Đặt chỗ
            ngay</a>
    </div>
    <?php else: ?>
    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Biển số xe</th>
                    <th>Thời gian bắt đầu</th>
                    <th>Thời gian kết thúc</th>
                    <th>Trạng thái đặt chỗ</th>
                    <th>Trạng thái thanh toán</th>
                    <th>Thành tiền</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): 
                            $bookingStatusClass = '';
                            $paymentStatusClass = '';
                            
                            // Xác định class và text cho trạng thái booking
                            // LOGIC FIX: Nếu thanh toán completed thì booking phải confirmed
                            if ($booking['payment_status'] === 'completed' && $booking['status'] === 'pending') {
                                // Tự động cập nhật booking thành confirmed và tạo ticket
                                dbUpdate('bookings', 'id', $booking['id'], [
                                    'status' => 'confirmed',
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                                
                                // Tạo ticket nếu chưa có
                                $existing_ticket = dbGetOne('tickets', 'booking_id', $booking['id']);
                                if (!$existing_ticket) {
                                    $new_ticket = 'VE' . strtoupper(bin2hex(random_bytes(4)));
                                    $qr_url = SITE_URL . '/payment.php?ticket=' . $new_ticket;
                                    
                                    dbInsert('tickets', [
                                        'ticket_code' => $new_ticket,
                                        'booking_id' => $booking['id'],
                                        'license_plate' => $booking['license_plate'],
                                        'time_in' => $booking['start_time'],
                                        'qr_url' => $qr_url,
                                        'status' => 'PAID',
                                        'amount' => $booking['amount'],
                                        'paid_at' => date('Y-m-d H:i:s')
                                    ]);
                                }
                                $booking['status'] = 'confirmed'; // Update local var
                            }
                            
                            switch ($booking['status']) {
                                case 'pending':
                                    $bookingStatusClass = 'warning';
                                    $bookingStatusText = 'Chờ sử nhận';
                                    break;
                                case 'confirmed':
                                    $bookingStatusClass = 'success';
                                    $bookingStatusText = 'Đã thanh toán';
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
                                    $bookingStatusText = 'Chờ sử nhận';
                            }
                            
                            // Xác định class và text cho trạng thái thanh toán
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
                                case 'cancelled':
                                    $paymentStatusClass = 'danger';
                                    $paymentStatusText = 'Đã hủy';
                                    break;
                                default:
                                    $paymentStatusClass = 'warning';
                                    $paymentStatusText = 'Chờ thanh toán';
                            }
                        ?>
                <tr>
                    <td><?php echo $booking['id']; ?></td>
                    <td style="font-weight: bold; color: var(--primary);">
                        <?php echo htmlspecialchars($booking['license_plate']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($booking['end_time'])); ?></td>
                    <td><span
                            class="badge badge-<?php echo $bookingStatusClass; ?>"><?php echo $bookingStatusText; ?></span>
                    </td>
                    <td><span
                            class="badge badge-<?php echo $paymentStatusClass; ?>"><?php echo $paymentStatusText; ?></span>
                    </td>
                    <td style="font-weight: bold;">
                        <?php echo number_format($booking['amount'] ?? 0, 0, ',', '.'); ?>₫</td>
                    <td>
                        <?php 
                                // Logic hiển thị nút dựa trên trạng thái
                                if ($booking['status'] === 'pending' && $booking['payment_status'] === 'pending'): 
                                ?>
                        <a href="dashboard.php?tab=payment&ref=<?php echo urlencode($booking['payment_ref'] ?? ''); ?>"
                            class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                            Thanh toán
                        </a>
                        <?php elseif ($booking['status'] === 'confirmed' && $booking['payment_status'] === 'completed'): 
                                // Lấy ticket của booking này
                                $ticket = dbGetOne('tickets', 'booking_id', $booking['id']);
                                if ($ticket):
                                ?>
                        <a href="api/generate_ticket.php?code=<?php echo urlencode($ticket['ticket_code']); ?>"
                            class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                            <i class="fas fa-ticket-alt"></i> Xem vé
                        </a>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="color: #6b7280; font-style: italic; font-size: 0.875rem;">--</span>
                        <?php endif; ?>
                    </td>
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
                        
                    // LOGIC FIX cho mobile card: Nếu thanh toán completed thì booking phải confirmed
                    if ($booking['payment_status'] === 'completed' && $booking['status'] === 'pending') {
                        $booking['status'] = 'confirmed'; // Update local var
                    }
                    
                    switch ($booking['status']) {
                        case 'pending':
                            $bookingStatusClass = 'warning';
                            $bookingStatusText = 'Chờ sử nhận';
                            break;
                        case 'confirmed':
                            $bookingStatusClass = 'success';
                            $bookingStatusText = 'Đã thanh toán';
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
                            $bookingStatusText = 'Chờ sử nhận';
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
                        case 'cancelled':
                            $paymentStatusClass = 'danger';
                            $paymentStatusText = 'Đã hủy';
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
                    <span>Biển số:</span>
                    <span
                        style="font-weight: bold; color: var(--primary);"><?php echo htmlspecialchars($booking['license_plate']); ?></span>
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
                    <span
                        style="font-weight: bold;"><?php echo number_format($booking['amount'] ?? 0, 0, ',', '.'); ?>₫</span>
                </div>
            </div>

            <?php if ($booking['status'] === 'pending' && $booking['payment_status'] === 'pending'): ?>
            <div class="mobile-card-actions">
                <a href="dashboard.php?tab=payment&ref=<?php echo urlencode($booking['payment_ref'] ?? ''); ?>"
                    class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Thanh toán
                </a>
            </div>
            <?php elseif ($booking['status'] === 'confirmed' && $booking['payment_status'] === 'completed'): 
                $ticket_mobile = dbGetOne('tickets', 'booking_id', $booking['id']);
                if ($ticket_mobile):
            ?>
            <div class="mobile-card-actions">
                <a href="api/generate_ticket.php?code=<?php echo urlencode($ticket_mobile['ticket_code']); ?>"
                    class="btn btn-success">
                    <i class="fas fa-ticket-alt"></i> Xem vé
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
