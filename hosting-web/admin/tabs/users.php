<div class="card">
    <h2 class="card-title"><i class="fas fa-users"></i> Quản lý người dùng</h2>
    
    <!-- User Filter Section -->
    <div class="filter-section" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">👤 Tên người dùng</label>
                <input type="text" id="userNameFilter" class="form-control" placeholder="Tìm theo tên..." style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">📧 Email</label>
                <input type="text" id="userEmailFilter" class="form-control" placeholder="Tìm theo email..." style="font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">👑 Vai trò</label>
                <select id="userRoleFilter" class="form-control" style="font-size: 0.875rem;">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Quản trị viên</option>
                    <option value="user">Người dùng</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.875rem; font-weight: 600; color: #374151;">📅 Từ ngày đăng ký</label>
                <input type="date" id="userDateFrom" class="form-control" style="font-size: 0.875rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="applyUserFilters()" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">🔍 Lọc</button>
                <button onclick="clearUserFilters()" class="btn" style="background: #6b7280; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">🔄 Reset</button>
            </div>
        </div>
    </div>

    <?php if (empty($users)): ?>
    <div style="text-align: center; padding: 2rem 0;">
        <i class="fas fa-users"
            style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p>Không có người dùng nào</p>
    </div>
    <?php else: ?>
    <!-- Desktop Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập</th>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Vai trò</th>
                    <th>Ngày đăng ký</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): 
                            $roleClass = $user['role'] === 'admin' ? 'danger' : 'info';
                            $roleText = $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng';
                        ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                    <td><span class="badge badge-<?php echo $roleClass; ?>"><?php echo $roleText; ?></span>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card Layout -->
    <div class="mobile-card-list">
        <?php foreach ($users as $user): 
                    $roleClass = $user['role'] === 'admin' ? 'danger' : 'info';
                    $roleText = $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng';
                ?>
        <div class="mobile-card">
            <div class="mobile-card-header">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></span>
                <span class="badge badge-<?php echo $roleClass; ?>"><?php echo $roleText; ?></span>
            </div>
            <div class="mobile-card-content">
                <div class="mobile-card-row">
                    <span>ID:</span>
                    <span><?php echo $user['id']; ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Username:</span>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Email:</span>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>SĐT:</span>
                    <span><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="mobile-card-row">
                    <span>Ngày đăng ký:</span>
                    <span><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    // Filter Functions for Users  
    window.userFilterData = <?php echo json_encode($users ?? []); ?>;
    
    function applyUserFilters() {
        const userName = document.getElementById('userNameFilter')?.value?.toLowerCase().trim() || '';
        const userEmail = document.getElementById('userEmailFilter')?.value?.toLowerCase().trim() || '';
        const userRole = document.getElementById('userRoleFilter')?.value || '';
        const dateFrom = document.getElementById('userDateFrom')?.value || '';
        
        let filtered = window.userFilterData.filter(user => {
            if (userName && (!user.full_name || !user.full_name.toLowerCase().includes(userName)) && (!user.username || !user.username.toLowerCase().includes(userName))) {
                return false;
            }
            if (userEmail && (!user.email || !user.email.toLowerCase().includes(userEmail))) {
                return false;
            }
            if (userRole && user.role !== userRole) {
                return false;
            }
            if (dateFrom) {
                const userDate = new Date(user.created_at);
                if (userDate < new Date(dateFrom)) return false;
            }
            return true;
        });
        
        updateUserDisplay(filtered);
        showFilterResults(filtered.length, window.userFilterData.length, 'người dùng');
    }
    
    function clearUserFilters() {
        ['userNameFilter', 'userEmailFilter', 'userRoleFilter', 'userDateFrom']
            .forEach(id => {
                const elem = document.getElementById(id);
                if (elem) elem.value = '';
            });
        updateUserDisplay(window.userFilterData);
        hideFilterResults();
    }
    
    function updateUserDisplay(filteredData) {
        const tbody = document.querySelector('.table-responsive tbody');
        if (tbody && filteredData.length >= 0) {
            tbody.innerHTML = filteredData.map(user => {
                const roleClass = user.role === 'admin' ? 'danger' : 'info';
                const roleText = user.role === 'admin' ? 'Quản trị viên' : 'Người dùng';
                const createdDate = new Date(user.created_at);
                
                return `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.username || 'N/A'}</td>
                        <td>${user.full_name || 'N/A'}</td>
                        <td>${user.email || 'N/A'}</td>
                        <td>${user.phone || 'N/A'}</td>
                        <td><span class="badge badge-${roleClass}">${roleText}</span></td>
                        <td>${createdDate.toLocaleDateString('vi-VN')} ${createdDate.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})}</td>
                    </tr>
                `;
            }).join('');
        }
        
        const mobileCards = document.querySelector('.mobile-card-list');
        if (mobileCards && filteredData.length >= 0) {
            mobileCards.innerHTML = filteredData.map(user => {
                const roleClass = user.role === 'admin' ? 'danger' : 'info';
                const roleText = user.role === 'admin' ? 'Quản trị viên' : 'Người dùng';
                const createdDate = new Date(user.created_at);
                
                return `
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <span><i class="fas fa-user"></i> ${user.full_name || 'N/A'}</span>
                            <span class="badge badge-${roleClass}">${roleText}</span>
                        </div>
                        <div class="mobile-card-content">
                            <div class="mobile-card-row"><span>ID:</span><span>${user.id}</span></div>
                            <div class="mobile-card-row"><span>Username:</span><span>${user.username || 'N/A'}</span></div>
                            <div class="mobile-card-row"><span>Email:</span><span>${user.email || 'N/A'}</span></div>
                            <div class="mobile-card-row"><span>SĐT:</span><span>${user.phone || 'N/A'}</span></div>
                            <div class="mobile-card-row"><span>Ngày đăng ký:</span><span>${createdDate.toLocaleDateString('vi-VN')}</span></div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }
</script>
