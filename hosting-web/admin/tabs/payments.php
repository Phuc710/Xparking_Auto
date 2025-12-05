<?php
// Lọc payments
$filter_type = $_GET['type'] ?? '';
$filter_plate = strtoupper(trim($_GET['plate'] ?? ''));
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

$filtered_payments = $payments;
if ($filter_type || $filter_plate || $filter_from || $filter_to) {
    $filtered_payments = array_filter($payments, function($p) use ($filter_type, $filter_plate, $filter_from, $filter_to) {
        // Lọc theo loại
        if ($filter_type === 'booking' && !$p['booking_id']) return false;
        if ($filter_type === 'walkin' && $p['booking_id']) return false;
        
        // Lọc theo BSX
        if ($filter_plate && stripos($p['license_plate'] ?? '', $filter_plate) === false) return false;
        
        // Lọc theo ngày
        if ($filter_from && $p['payment_time']) {
            if (date('Y-m-d', strtotime($p['payment_time'])) < $filter_from) return false;
        }
        if ($filter_to && $p['payment_time']) {
            if (date('Y-m-d', strtotime($p['payment_time'])) > $filter_to) return false;
        }
        
        return true;
    });
}
?>
<div class="card">
    <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Lịch sử thanh toán</h2>

    <!-- Filter Form -->
    <form method="get" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <input type="hidden" name="tab" value="payments">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: end;">
            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.3rem;">Loại</label>
                <select name="type" class="form-control" style="padding: 0.5rem;">
                    <option value="">Tất cả</option>
                    <option value="booking" <?php echo $filter_type === 'booking' ? 'selected' : ''; ?>>Đặt chỗ</option>
                    <option value="walkin" <?php echo $filter_type === 'walkin' ? 'selected' : ''; ?>>Vãng lai</option>
                </select>
            </div>
            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.3rem;">Biển số xe</label>
                <input type="text" name="plate" class="form-control" placeholder="VD: 77A12345" 
                    value="<?php echo htmlspecialchars($filter_plate); ?>" style="padding: 0.5rem;">
            </div>
            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.3rem;">Từ ngày</label>
                <input type="date" name="from" class="form-control" value="<?php echo $filter_from; ?>" style="padding: 0.5rem;">
            </div>
            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.3rem;">Đến ngày</label>
                <input type="date" name="to" class="form-control" value="<?php echo $filter_to; ?>" style="padding: 0.5rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    <i class="fas fa-search"></i> Lọc
                </button>
                <a href="admin.php?tab=payments" class="btn" style="background: #e5e7eb; padding: 0.5rem 1rem;">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
    </form>

    <?php 
    $total_filtered = 0;
    foreach ($filtered_payments as $p) {
        if ($p['status'] === 'completed') $total_filtered += floatval($p['amount']);
    }
    ?>
    <div style="margin-bottom: 1rem; padding: 0.8rem; background: #ecfdf5; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
        <span><strong><?php echo count($filtered_payments); ?></strong> kết quả</span>
        <span style="color: #059669; font-weight: bold;">Tổng: <?php echo number_format($total_filtered, 0, ',', '.'); ?>₫</span>
    </div>

    <?php if (empty($filtered_payments)): ?>
    <div style="text-align: center; padding: 2rem 0;">
        <i class="fas fa-money-bill-wave"
            style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p>Không có thanh toán nào</p>
    </div>
    <?php else: ?>
    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người dùng</th>
                    <th>Loại</th>
                    <th>Biển số xe</th>
                    <th>Số tiền</th>
                    <th>Thời gian thanh toán</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_payments as $payment): 
                            $statusClass = '';
                            $statusText = '';
                            
                            switch ($payment['status']) {
                                case 'pending':
                                    $statusClass = 'warning';
                                    $statusText = 'Chờ thanh toán';
                                    break;
                                case 'completed':
                                    $statusClass = 'success';
                                    $statusText = 'Đã thanh toán';
                                    break;
                                case 'failed':
                                    $statusClass = 'danger';
                                    $statusText = 'Thanh toán thất bại';
                                    break;
                                case 'expired':
                                    $statusClass = 'danger';
                                    $statusText = 'Hết hạn';
                                    break;
                                default:
                                    $statusClass = 'warning';
                                    $statusText = 'Chờ thanh toán';
                            }
                            
                            $paymentType = '';
                            $paymentBSX = $payment['license_plate'] ?? '';
                            if ($payment['booking_id']) {
                                $paymentType = '<span class="badge badge-info">Đặt chỗ</span>';
                            } elseif ($payment['vehicle_id']) {
                                $paymentType = '<span class="badge badge-warning">Vãng lai</span>';
                            } else {
                                $paymentType = '<span class="badge">Khác</span>';
                            }
                        ?>
                <tr>
                    <td><?php echo $payment['id']; ?></td>
                    <td><?php echo $payment['full_name'] ? htmlspecialchars($payment['full_name']) : 'N/A'; ?></td>
                    <td><?php echo $paymentType; ?></td>
                    <td><?php echo $paymentBSX ? htmlspecialchars($paymentBSX) : 'N/A'; ?></td>
                    <td><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</td>
                    <td><?php echo $payment['payment_time'] ? date('d/m/Y H:i', strtotime($payment['payment_time'])) : 'N/A'; ?></td>
                    <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card Layout -->
    <div class="mobile-card-list">
        <?php foreach ($filtered_payments as $payment): 
                    $statusClass = '';
                    $statusText = '';
                    
                    switch ($payment['status']) {
                        case 'pending':
                            $statusClass = 'warning';
                            $statusText = 'Chờ thanh toán';
                            break;
                        case 'completed':
                            $statusClass = 'success';
                            $statusText = 'Đã thanh toán';
                            break;
                        case 'failed':
                            $statusClass = 'danger';
                            $statusText = 'Thanh toán thất bại';
                            break;
                        case 'expired':
                            $statusClass = 'danger';
                            $statusText = 'Hết hạn';
                            break;
                        default:
                            $statusClass = 'warning';
                            $statusText = 'Chờ thanh toán';
                    }
                    
                    $paymentType = '';
                    if ($payment['booking_id']) {
                        $paymentType = 'Đặt chỗ #' . $payment['booking_id'];
                    } elseif ($payment['vehicle_id']) {
                        $paymentType = 'Xe ra #' . $payment['vehicle_id'];
                        if ($payment['license_plate']) {
                            $paymentType .= ' (' . $payment['license_plate'] . ')';
                        }
                    } else {
                        $paymentType = 'Khác';
                    }
                ?>
        <div class="mobile-card">
            <div class="mobile-card-header">
                <span><i class="fas fa-money-bill"></i> Payment #<?php echo $payment['id']; ?></span>
                <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
            </div>
            <div class="mobile-card-content">
                <div class="mobile-card-row">
                    <span>Người dùng:</span>
                    <span><?php echo $payment['full_name'] ? htmlspecialchars($payment['full_name']) : 'N/A'; ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Loại:</span>
                    <span><?php echo $paymentType; ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Mã ref:</span>
                    <span><?php echo htmlspecialchars($payment['payment_ref']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Số tiền:</span>
                    <span><strong><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</strong></span>
                </div>
                <div class="mobile-card-row">
                    <span>Thời gian:</span>
                    <span><?php echo $payment['payment_time'] ? date('d/m/Y H:i', strtotime($payment['payment_time'])) : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
