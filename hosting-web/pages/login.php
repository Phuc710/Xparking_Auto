<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        line-height: 1.6;
        color: var(--dark);
        background-color: #e9e9e9;
    }

    .image-container {
        text-align: center; /* căn giữa chữ + ảnh */
    }

    .image-text {
        font-size: 22px;
        font-weight: bold;
        color: black;  /* chữ màu đen */
    }

    .image-container img {
        width: 180px;
        display: block;
        margin: auto;
    }
</style>

<div class="container" style="margin: 3rem auto;">
    <div class="form-container">
        <div class="image-container">
            <img src="/LOGO.gif" alt="Login">
            <div class="image-text">Đăng nhập</div>
        </div>

        <form action="index.php?action=login" method="post" style="margin-top: 1.5rem;">
            <div class="form-group">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary form-btn">Đăng nhập</button>
        </form>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a></p>
        </div>
    </div>
</div>

<script>
// Check for flash messages (Error/Success)
<?php 
$flash = get_flash_message();
if ($flash):
?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '<?php echo $flash["type"] === "success" ? "Thành công!" : "Lỗi!"; ?>',
        text: '<?php echo addslashes($flash["message"]); ?>',
        icon: '<?php echo $flash["type"]; ?>',
        timer: 2000,
        showConfirmButton: false,
        timerProgressBar: true
    });
});
<?php endif; ?>

// Check for register success message
<?php if (isset($_SESSION['register_success']) && $_SESSION['register_success']): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'Đăng ký thành công!',
        text: 'Tài khoản của bạn đã được tạo, vui lòng đăng nhập',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        timerProgressBar: true
    });
});
<?php unset($_SESSION['register_success']); endif; ?>
</script>
