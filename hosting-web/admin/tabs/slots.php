<div class="card">
    <h2 class="card-title"><i class="fas fa-parking"></i> Quản lý slots</h2>

    <div class="slot-grid">
        <?php foreach ($slots as $slot): 
                    $statusClass = '';
                    $statusText = '';
                    $statusColor = '';
                    $slotInfo = '';
                    $ticketInfo = '';
                    
                    switch ($slot['actual_status']) {
                        case 'occupied':
                            $statusClass = 'danger';
                            $statusText = 'Đang sử dụng';
                            $statusColor = '#ef4444';
                            // Lấy thông tin xe đang đỗ
                            if (!empty($slot['vehicle_license'])) {
                                $slotInfo = $slot['vehicle_license'];
                            }
                            if (!empty($slot['ticket_code'])) {
                                $ticketInfo = $slot['ticket_code'];
                            }
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
            <?php if ($slotInfo): ?>
            <div style="margin-top: 0.5rem; font-weight: bold; color: #1f2937; font-size: 0.9rem;">
                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($slotInfo); ?>
            </div>
            <?php endif; ?>
            <?php if ($ticketInfo): ?>
            <div style="margin-top: 0.3rem; font-size: 0.8rem; color: #6b7280;">
                <i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($ticketInfo); ?>
            </div>
            <?php endif; ?>
            <div style="margin-top: 0.8rem;">
                <?php if (in_array($slot['actual_status'], ['empty', 'maintenance'])): ?>
                <button class="btn btn-primary"
                    onclick="openEditModal('<?php echo $slot['id']; ?>', '<?php echo $slot['status']; ?>')">Cập nhật</button>
                <?php else: ?>
                <button class="btn" style="background-color: #e5e7eb; color: #6b7280; cursor: not-allowed;"
                    disabled>Đang sử dụng</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="editSlotModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 class="modal-title">Cập nhật trạng thái slot</h3>

        <form action="admin.php?tab=slots" method="post">
            <input type="hidden" name="action" value="update_slot">
            <input type="hidden" id="slot_id" name="slot_id" value="">

            <div class="form-group">
                <label for="status" class="form-label">Trạng thái</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="empty">Hoạt động</option>
                    <option value="maintenance">Bảo trì</option>
                </select>
                <small>Lưu ý: Phương tiện đông đúc.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Cập nhật</button>
        </form>
    </div>
</div>

<script>
    // Modal functions
    function openEditModal(slotId, status) {
        document.getElementById('slot_id').value = slotId;
        document.getElementById('status').value = status;

        // Only allow changing status if slot is empty or in maintenance
        if (status !== 'empty' && status !== 'maintenance') {
            Swal.fire({
                title: 'Không thể thay đổi!',
                text: 'Slot đang được sử dụng, không thể thay đổi trạng thái!',
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        document.getElementById('editSlotModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('editSlotModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('editSlotModal');
        if (event.target === modal) {
            closeModal();
        }
    });
</script>
