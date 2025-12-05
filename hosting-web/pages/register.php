<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        line-height: 1.6;
        color: var(--dark);
        background-color: #e9e9e9;
    }
</style>
<div class="container" style="margin: 3rem auto;">
    <div class="form-container">
        <h2 class="form-title">Đăng ký tài khoản</h2>
        
        <form id="registerForm" action="index.php?action=register" method="post">
            <div class="form-group">
                <label for="full_name" class="form-label">Họ và tên</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" required>
                <small class="form-text">Chỉ sử dụng chữ cái, số và dấu gạch dưới</small>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="phone" class="form-label">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <small class="form-text">Tối thiểu 6 ký tự</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary form-btn">Đăng ký</button>
        </form>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a></p>
        </div>
    </div>
</div>

<!-- SweetAlert for flash messages on register page -->
<?php 
$flash = get_flash_message();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '<?php echo $flash["type"] === "success" ? "Thành công!" : "Lỗi!"; ?>',
        text: '<?php echo addslashes($flash["message"]); ?>',
        icon: '<?php echo $flash["type"]; ?>',
        timer: 3000,
        showConfirmButton: false,
        timerProgressBar: true
    });
});
</script>
<?php endif; ?>