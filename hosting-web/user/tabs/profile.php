<div class="card">
    <h2 class="card-title"><i class="fas fa-user-cog"></i> Thông tin cá nhân</h2>

    <div style="margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">Thông tin
            tài khoản</h3>

        <form action="dashboard.php?tab=profile" method="post">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" class="form-control"
                    value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
            </div>

            <div class="form-group">
                <label for="full_name" class="form-label">Họ và tên</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                    value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn btn-primary">Cập nhật thông tin</button>
        </form>
    </div>

    <div>
        <h3 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">Đổi mật
            khẩu</h3>

        <form action="dashboard.php?tab=profile" method="post">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                <input type="password" id="current_password" name="current_password" class="form-control"
                    required>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">Mật khẩu mới</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                    required>
            </div>

            <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
        </form>
    </div>
</div>
