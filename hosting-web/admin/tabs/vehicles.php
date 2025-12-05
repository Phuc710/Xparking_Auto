<div class="card">
    <h2 class="card-title"><i class="fas fa-car-side"></i> Lịch sử gửi xe</h2>
    
    <!-- Vehicle Filter Section -->
    <div class="filter-section" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Biển số xe</label>
                <input type="text" id="licensePlateFilter" class="form-control" placeholder="Nhập biển số..." style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Loại vé</label>
                <select id="ticketTypeFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả loại vé</option>
                    <option value="booking">Đặt trước</option>
                    <option value="walkin">Vãng lai</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Trạng thái</label>
                <select id="vehicleStatusFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="ACTIVE">Trong bãi</option>
                    <option value="PAID">Đã thanh toán</option>
                    <option value="USED">Đã ra</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Từ ngày</label>
                <input type="date" id="vehicleDateFrom" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;"> Đến ngày</label>
                <input type="date" id="vehicleDateTo" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="applyVehicleFilters()" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;"> Lọc</button>
                <button onclick="clearVehicleFilters()" class="btn" style="background: #6b7280; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;"> Reset</button>
            </div>
        </div>
    </div>

    <?php if (empty($all_vehicles)): ?>
    <div style="text-align: center; padding: 2rem 0;">
        <i class="fas fa-car-crash"
            style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p>Không có lịch sử gửi xe nào</p>
    </div>
    <?php else: ?>
    
    <!-- Stats Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <?php 
        $inPark = count(array_filter($all_vehicles, fn($v) => $v['status'] === 'ACTIVE'));
        $totalFee = array_sum(array_column($all_vehicles, 'amount'));
        ?>
        <div style="background: #ecfdf5; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #059669;"><?php echo $inPark; ?></div>
            <div style="font-size: 0.85rem; color: #065f46;">Đang trong bãi</div>
        </div>
        <div style="background: #eff6ff; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #2563eb;"><?php echo count($all_vehicles); ?></div>
            <div style="font-size: 0.85rem; color: #1e40af;">Tổng lượt</div>
        </div>
        <div style="background: #fef3c7; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.25rem; font-weight: bold; color: #d97706;"><?php echo number_format($totalFee); ?>đ</div>
            <div style="font-size: 0.85rem; color: #92400e;">Tổng thu</div>
        </div>
    </div>

    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Mã vé</th>
                    <th>Biển số xe</th>
                    <th>Loại</th>
                    <th>Thời gian vào</th>
                    <th>Thời gian ra</th>
                    <th>Phí (VNĐ)</th>
                    <th>Phí thêm</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_vehicles as $t): ?>
                <tr>
                    <td><code style="background:#f3f4f6; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($t['ticket_code']); ?></code></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($t['license_plate'] ?? 'N/A'); ?></span></td>
                    <td><?php echo $t['ticket_type'] === 'booking' ? '<span class="badge badge-primary">Đặt trước</span>' : '<span class="badge badge-warning">Vãng lai</span>'; ?></td>
                    <td><?php echo $t['time_in_formatted']; ?></td>
                    <td><?php echo $t['time_out_formatted'] ?? '<span style="color:#9ca3af">Chưa ra</span>'; ?></td>
                    <td style="font-weight:600; color:#059669;"><?php echo number_format($t['amount'] ?? 0); ?></td>
                    <td><?php if (!empty($t['overstay_fee'])): ?><span style="color:#dc2626; font-weight:600;">+<?php echo number_format($t['overstay_fee']); ?></span><?php else: ?><span style="color:#9ca3af">-</span><?php endif; ?></td>
                    <td><span class="badge badge-<?php echo $t['status_class']; ?>"><?php echo $t['status_label']; ?></span></td>
                    <td><button class="btn btn-sm" onclick="showTicketDetail('<?php echo $t['ticket_code']; ?>')" style="padding:4px 10px; font-size:0.8rem; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer;"><i class="fas fa-info-circle"></i> Chi tiết</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card-list">
        <?php foreach ($all_vehicles as $t): ?>
        <div class="mobile-card" onclick="showTicketDetail('<?php echo $t['ticket_code']; ?>')" style="cursor:pointer;">
            <div class="mobile-card-header">
                <span><code style="background:#e0e7ff; padding:2px 6px; border-radius:4px;"><?php echo $t['ticket_code']; ?></code></span>
                <span class="badge badge-<?php echo $t['status_class']; ?>"><?php echo $t['status_label']; ?></span>
            </div>
            <div class="mobile-card-content">
                <div class="mobile-card-row"><span>Biển số:</span><span class="badge badge-info"><?php echo htmlspecialchars($t['license_plate'] ?? 'N/A'); ?></span></div>
                <div class="mobile-card-row"><span>Loại:</span><span><?php echo $t['type_label']; ?></span></div>
                <div class="mobile-card-row"><span>Vào:</span><span><?php echo $t['time_in_formatted']; ?></span></div>
                <div class="mobile-card-row"><span>Ra:</span><span><?php echo $t['time_out_formatted'] ?? 'Chưa ra'; ?></span></div>
                <div class="mobile-card-row"><span>Phí:</span><span style="font-weight:600; color:#059669;"><?php echo number_format($t['amount'] ?? 0); ?>đ<?php if (!empty($t['overstay_fee'])): ?> <span style="color:#dc2626;">+<?php echo number_format($t['overstay_fee']); ?>đ</span><?php endif; ?></span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Ticket Detail Modal -->
<div id="ticketDetailModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; padding:10px; overflow:auto;">
    <div style="background:white; max-width:1200px; width:95%; margin:10px auto; border-radius:16px; overflow:hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="background:linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color:white; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; display:flex; align-items:center; gap:8px;"><i class="fas fa-ticket-alt"></i> Chi tiết lịch sử gửi xe</h3>
            <button onclick="closeTicketModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; font-size:1.5rem; cursor:pointer; padding:8px 12px; border-radius:8px; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">&times;</button>
        </div>
        <div id="ticketDetailContent" style="padding:24px;"></div>
    </div>
</div>
<script>
    const ticketData = <?php echo json_encode($all_vehicles ?? []); ?>;
    function showTicketDetail(code) {
        const t = ticketData.find(x => x.ticket_code === code);
        if (!t) return;
        const entryImg = t.entry_image ? `<img src="${t.entry_image}" style="max-width:100%; border-radius:8px;">` : '<div style="background:#f3f4f6; padding:40px; text-align:center; border-radius:8px; color:#9ca3af;"><i class="fas fa-image" style="font-size:2rem;"></i><br>Không có ảnh</div>';
        const exitImg = t.exit_image ? `<img src="${t.exit_image}" style="max-width:100%; border-radius:8px;">` : '<div style="background:#f3f4f6; padding:40px; text-align:center; border-radius:8px; color:#9ca3af;"><i class="fas fa-image" style="font-size:2rem;"></i><br>Không có ảnh</div>';
        const ticketImg = t.ticket_image ? `<div><p style="font-weight:600; margin-bottom:8px;">🎫 Ảnh vé</p><img src="${t.ticket_image}" style="max-width:100%; border-radius:8px;"></div>` : '';
        const overstayHtml = t.overstay_fee ? `<div style="background:linear-gradient(135deg, #fee2e2, #fef2f2); padding:16px; border-radius:12px; margin:16px 0; border-left:4px solid #ef4444;"><h4 style="color:#dc2626; margin:0 0 8px 0;"><i class="fas fa-exclamation-triangle"></i> Phí quá giờ</h4><div style="color:#7f1d1d;">Thời gian: ${t.overstay_minutes} phút | Phí: <strong>+${Number(t.overstay_fee).toLocaleString()}đ</strong></div></div>` : '';
        
        document.getElementById('ticketDetailContent').innerHTML = `
            <div style="background:linear-gradient(135deg, #f8fafc, #ffffff); padding:20px; border-radius:16px; margin-bottom:20px; border:1px solid #e2e8f0;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px;">
                    <div>
                        <h4 style="color:#1f2937; margin-bottom:16px; border-bottom:2px solid #e5e7eb; padding-bottom:8px;"><i class="fas fa-info-circle" style="color:#3b82f6;"></i> Thông tin vé</h4>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Mã vé:</strong> <code style="background:#e0e7ff; padding:6px 12px; border-radius:8px; font-weight:600;">${t.ticket_code}</code></p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Biển số:</strong> <span class="badge badge-info">${t.license_plate || 'N/A'}</span></p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Loại:</strong> ${t.ticket_type === 'booking' ? '<span class="badge badge-primary">Đặt trước</span>' : '<span class="badge badge-warning">Vãng lai</span>'}</p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Slot:</strong> ${t.slot_id || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="color:#1f2937; margin-bottom:16px; border-bottom:2px solid #e5e7eb; padding-bottom:8px;"><i class="fas fa-clock" style="color:#059669;"></i> Thời gian & Chi phí</h4>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Vào:</strong> <span style="color:#059669; font-weight:600;">${t.time_in_formatted}</span></p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Ra:</strong> <span style="color:${t.time_out_formatted ? '#059669' : '#f59e0b'}; font-weight:600;">${t.time_out_formatted || 'Chưa ra'}</span></p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Phí:</strong> <span style="color:#059669; font-weight:bold; font-size:1.2rem;">${Number(t.amount || 0).toLocaleString()}đ</span></p>
                        <p style="margin:8px 0; display:flex; justify-content:space-between;"><strong>Trạng thái:</strong> <span class="badge badge-${t.status_class}">${t.status_label}</span></p>
                    </div>
                </div>
            </div>
            ${overstayHtml}
            <hr style="margin:20px 0; border:none; border-top:1px solid #e5e7eb;">
            <h4 style="margin-bottom:16px; color:#1f2937;"><i class="fas fa-camera" style="color:#3b82f6;"></i> Hình ảnh lưu trữ</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                <div><div style="background:#ecfdf5; padding:12px; border-radius:8px; margin-bottom:12px; text-align:center; border:1px solid #bbf7d0;"><i class="fas fa-sign-in-alt" style="color:#059669;"></i> <strong>Ảnh xe vào</strong></div>${entryImg}</div>
                <div><div style="background:#fef3c7; padding:12px; border-radius:8px; margin-bottom:12px; text-align:center; border:1px solid #fde68a;"><i class="fas fa-sign-out-alt" style="color:#d97706;"></i> <strong>Ảnh xe ra</strong></div>${exitImg}</div>
                ${ticketImg ? `<div>${ticketImg}</div>` : ''}
            </div>`;
        document.getElementById('ticketDetailModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    function closeTicketModal() { document.getElementById('ticketDetailModal').style.display = 'none'; document.body.style.overflow = ''; }
    document.getElementById('ticketDetailModal')?.addEventListener('click', function(e) { if (e.target === this) closeTicketModal(); });

    // Filter Functions for Vehicles
    window.vehicleFilterData = <?php echo json_encode($all_vehicles ?? []); ?>;
    
    function applyVehicleFilters() {
        const licensePlate = document.getElementById('licensePlateFilter')?.value?.toUpperCase()?.trim() || '';
        const ticketType = document.getElementById('ticketTypeFilter')?.value || '';
        const status = document.getElementById('vehicleStatusFilter')?.value || '';
        const dateFrom = document.getElementById('vehicleDateFrom')?.value || '';
        const dateTo = document.getElementById('vehicleDateTo')?.value || '';
        
        let filtered = window.vehicleFilterData.filter(ticket => {
            if (licensePlate && (!ticket.license_plate || !ticket.license_plate.toUpperCase().includes(licensePlate))) {
                return false;
            }
            if (ticketType && ticket.ticket_type !== ticketType) {
                return false;
            }
            if (status && ticket.status !== status) {
                return false;
            }
            if (dateFrom || dateTo) {
                const ticketDateStr = ticket.time_in_formatted ? ticket.time_in_formatted.split(' ')[0] : '';
                if (ticketDateStr) {
                    const parts = ticketDateStr.split('/');
                    if (parts.length === 3) {
                        const ticketDate = new Date(parts[2] + '-' + parts[1] + '-' + parts[0]);
                        if (dateFrom && ticketDate < new Date(dateFrom)) return false;
                        if (dateTo && ticketDate > new Date(dateTo)) return false;
                    }
                }
            }
            return true;
        });
        
        updateVehicleDisplay(filtered);
        showFilterResults(filtered.length, window.vehicleFilterData.length, 'lịch sử xe');
    }
    
    function clearVehicleFilters() {
        ['licensePlateFilter', 'ticketTypeFilter', 'vehicleStatusFilter', 'vehicleDateFrom', 'vehicleDateTo']
            .forEach(id => {
                const elem = document.getElementById(id);
                if (elem) elem.value = '';
            });
        updateVehicleDisplay(window.vehicleFilterData);
        hideFilterResults();
    }
    
    function updateVehicleDisplay(filteredData) {
        const tbody = document.querySelector('.table-responsive tbody');
        if (tbody) {
            tbody.innerHTML = filteredData.map(t => `
                <tr>
                    <td><code style="background:#f3f4f6; padding:2px 6px; border-radius:4px;">${t.ticket_code}</code></td>
                    <td><span class="badge badge-info">${t.license_plate || 'N/A'}</span></td>
                    <td>${t.ticket_type === 'booking' ? '<span class="badge badge-primary">Đặt trước</span>' : '<span class="badge badge-warning">Vãng lai</span>'}</td>
                    <td>${t.time_in_formatted}</td>
                    <td>${t.time_out_formatted || '<span style="color:#9ca3af">Chưa ra</span>'}</td>
                    <td style="font-weight:600; color:#059669;">${Number(t.amount || 0).toLocaleString()}</td>
                    <td>${t.overstay_fee ? `<span style="color:#dc2626; font-weight:600;">+${Number(t.overstay_fee).toLocaleString()}</span>` : '<span style="color:#9ca3af">-</span>'}</td>
                    <td><span class="badge badge-${t.status_class}">${t.status_label}</span></td>
                    <td><button class="btn btn-sm" onclick="showTicketDetail('${t.ticket_code}')" style="padding:4px 10px; font-size:0.8rem; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer;"><i class="fas fa-info-circle"></i> Chi tiết</button></td>
                </tr>
            `).join('');
        }
        
        const mobileCards = document.querySelector('.mobile-card-list');
        if (mobileCards) {
            mobileCards.innerHTML = filteredData.map(t => `
                <div class="mobile-card" onclick="showTicketDetail('${t.ticket_code}')" style="cursor:pointer;">
                    <div class="mobile-card-header">
                        <span><code style="background:#e0e7ff; padding:2px 6px; border-radius:4px;">${t.ticket_code}</code></span>
                        <span class="badge badge-${t.status_class}">${t.status_label}</span>
                    </div>
                    <div class="mobile-card-content">
                        <div class="mobile-card-row"><span>Biển số:</span><span class="badge badge-info">${t.license_plate || 'N/A'}</span></div>
                        <div class="mobile-card-row"><span>Loại:</span><span>${t.type_label || (t.ticket_type === 'booking' ? 'Đặt trước' : 'Vãng lai')}</span></div>
                        <div class="mobile-card-row"><span>Vào:</span><span>${t.time_in_formatted}</span></div>
                        <div class="mobile-card-row"><span>Ra:</span><span>${t.time_out_formatted || 'Chưa ra'}</span></div>
                        <div class="mobile-card-row"><span>Phí:</span><span style="font-weight:600; color:#059669;">${Number(t.amount || 0).toLocaleString()}đ${t.overstay_fee ? ` <span style="color:#dc2626;">+${Number(t.overstay_fee).toLocaleString()}đ</span>` : ''}</span></div>
                    </div>
                </div>
            `).join('');
        }
    }
</script>
