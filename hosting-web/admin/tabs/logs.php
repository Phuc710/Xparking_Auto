<div class="card">
    <h2 class="card-title"><i class="fas fa-history"></i> Nhật ký hệ thống</h2>

    <?php if (empty($logs)): ?>
    <div style="text-align: center; padding: 2rem 0;">
        <i class="fas fa-history"
            style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p>Không có nhật ký nào</p>
    </div>
    <?php else: ?>
    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sự kiện</th>
                    <th>Mô tả</th>
                    <th>Người dùng</th>
                    <th>IP</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $log['id']; ?></td>
                    <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                    <td><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card Layout -->
    <div class="mobile-card-list">
        <?php foreach ($logs as $log): ?>
        <div class="mobile-card">
            <div class="mobile-card-header">
                <span><i class="fas fa-clipboard-list"></i>
                    <?php echo htmlspecialchars($log['event_type']); ?></span>
                <span class="badge badge-info">#<?php echo $log['id']; ?></span>
            </div>
            <div class="mobile-card-content">
                <div class="mobile-card-row">
                    <span>Mô tả:</span>
                    <span><?php echo htmlspecialchars($log['description']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Người dùng:</span>
                    <span><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'N/A'; ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>IP:</span>
                    <span><?php echo htmlspecialchars($log['ip_address']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Thời gian:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
