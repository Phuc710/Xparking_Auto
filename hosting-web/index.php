<?php
// Main index file index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Handle login/logout actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (login_user($username, $password)) {
                    // Store success message in session and redirect
                    $_SESSION['login_success'] = true;
                    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
                } else {
                    set_flash_message('error', 'Tên đăng nhập hoặc mật khẩu không đúng!');
                    redirect('index.php?page=login');
                }
            }
            break;
            
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                $email = $_POST['email'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $phone = $_POST['phone'] ?? '';
                
                // Basic validation
                if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
                    set_flash_message('error', 'Vui lòng điền đầy đủ thông tin!');
                    redirect('index.php?page=register');
                }
                
                if ($password !== $confirm_password) {
                    set_flash_message('error', 'Mật khẩu xác nhận không khớp!');
                    redirect('index.php?page=register');
                }
                
                $result = register_user($username, $password, $email, $full_name, $phone);
                
                if ($result['success']) {
                    // Store success message in session and redirect
                    $_SESSION['register_success'] = true;
                    redirect('index.php?page=login');
                } else {
                    set_flash_message('error', $result['message']);
                    redirect('index.php?page=register');
                }
            }
            break;
            
        case 'logout':
            logout_user();
            set_flash_message('success', 'Đăng xuất thành công!');
            redirect('index.php');
            break;
    }
}

// Determine which page to display
$page = $_GET['page'] ?? 'home';

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Parking</title>
    <link rel="shortcut icon" href="/LOGO.gif" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <link rel="stylesheet" href="styles/index.css">
    <script src="styles/swal-config.js"></script>
    <link rel="preconnect" href="https://embed.tawk.to" crossorigin>
    <link rel="preconnect" href="https://va.tawk.to" crossorigin>
    <link rel="dns-prefetch" href="//embed.tawk.to">
    <link rel="dns-prefetch" href="//va.tawk.to">
</head>


<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API = Tawk_API || {},
    Tawk_LoadStart = new Date();
(function() {
    var s1 = document.createElement("script"),
        s0 = document.getElementsByTagName("script")[0];
    s1.async = true;
    s1.src = 'https://embed.tawk.to/68b93b1eb27e571923f065f7/1j49ots2s';
    s1.charset = 'UTF-8';
    s1.setAttribute('crossorigin', '*');
    s0.parentNode.insertBefore(s1, s0);
})();
</script>
<!--End of Tawk.to Script-->

<body>
    <script>document.body.classList.add('js-enabled');</script>
    <div class="menu-overlay" onclick="closeMobileMenu()"></div>

    <header class="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="/LOGO.gif" alt="XParking">
                <span>XPARKING</span>
            </a>

            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-menu" id="navMenu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=about" class="nav-link">Giới thiệu</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=weather" class="nav-link">Thời tiết</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=contact" class="nav-link">Liên hệ</a>
                </li>
                <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a href="dashboard.php" class="btn btn-primary">Bảng điều khiển</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?action=logout" class="btn btn-outline">Đăng xuất</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="index.php?page=login" class="btn btn-primary">Đăng nhập</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=register" class="btn btn-outline">Đăng ký</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <main>
        <?php
        // Load the appropriate page content
        switch ($page) {
            case 'login':
                include 'pages/login.php';
                break;
                
            case 'register':
                include 'pages/register.php';
                break;
                
            case 'about':
                include 'pages/about.php';
                break;
                
            case 'weather':
                include 'pages/weather.php';
                break;
                
            case 'contact':
                include 'pages/contact.php';
                break;
                
            default:
                // Home page with new layout
                ?>
        <section class="hero-section">
            <div class="container">
                <div class="hero-content fade-up">
                    <h1 class="hero-title">HỆ THỐNG ĐỖ XE THÔNG MINH<br>CÔNG NGHỆ TIÊN TIẾN</h1>
                    <p class="hero-subtitle">Toàn bộ được quản lý thông minh với AI nhận diện biển số, thanh toán QR tự
                        động</p>
                    <a href="#services" class="hero-button">
                        <i class="fas fa-rocket"></i>
                        Xem thêm
                    </a>
                </div>
            </div>
        </section>

        <section id="services" class="services-section">
            <div class="container">
                <div class="section-header fade-up">
                    <h2 class="section-title">Các gói dịch vụ đỗ xe</h2>
                    <p class="section-subtitle">Lựa chọn gói dịch vụ phù hợp với nhu cầu của bạn, từ cá nhân đến doanh
                        nghiệp</p>
                </div>

                <div class="services-grid">
                    <div class="service-card fade-up">
                        <div class="service-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h3 class="service-title">Đỗ xe thông thường</h3>
                        <p class="service-description">Dịch vụ đỗ xe cơ bản với hệ thống AI nhận diện biển số và thanh
                            toán QR code tiện lợi.</p>
                        <ul class="service-features">
                            <li>Giá cả phải chăng: 5.000đ/giờ</li>
                            <li>Thanh toán linh hoạt khi ra bãi</li>
                            <li>Không cần đăng ký trước</li>
                            <li>AI nhận diện biển số tự động</li>
                        </ul>
                    </div>

                    <div class="service-card fade-up">
                        <div class="service-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="service-title">Đặt chỗ trước</h3>
                        <p class="service-description">Đảm bảo chỗ đỗ an toàn cho phương tiện với dịch vụ đặt trước
                            thông minh.</p>
                        <ul class="service-features">
                            <li>Cùng mức giá: 5.000đ/giờ</li>
                            <li>Thanh toán trước an toàn</li>
                            <li>Cam kết có chỗ đỗ</li>
                            <li>Hệ thống phạt nếu không sử dụng</li>
                        </ul>
                    </div>

                    <div class="service-card fade-up">
                        <div class="service-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="service-title">Gói doanh nghiệp</h3>
                        <p class="service-description">Giải pháp toàn diện cho doanh nghiệp với nhiều tính năng quản lý
                            nâng cao.</p>
                        <ul class="service-features">
                            <li>Giá ưu đãi theo từng gói dịch vụ</li>
                            <li>Quản lý nhiều phương tiện cùng lúc</li>
                            <li>Báo cáo và thống kê chi tiết</li>
                            <li>Hỗ trợ khách hàng ưu tiên 24/7</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="process-section">
            <div class="container">
                <div class="section-header fade-up">
                    <h2 class="section-title">Quy trình sử dụng dịch vụ</h2>
                    <p class="section-subtitle">4 bước đơn giản để trải nghiệm dịch vụ đỗ xe thông minh XParking</p>
                </div>

                <div class="process-steps">
                    <div class="process-step fade-up">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Đăng ký tài khoản</h3>
                        <p class="step-description">Tạo tài khoản XParking nhanh chóng trên website hoặc ứng dụng di
                            động</p>
                    </div>

                    <div class="process-step fade-up">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Đặt chỗ (tùy chọn)</h3>
                        <p class="step-description">Đặt trước chỗ đỗ nếu cần đảm bảo có chỗ trong thời gian cao điểm</p>
                    </div>

                    <div class="process-step fade-up">
                        <div class="step-number">3</div>
                        <h3 class="step-title">Vào bãi đỗ xe</h3>
                        <p class="step-description">Hệ thống AI tự động nhận diện biển số và mở cửa barrier</p>
                    </div>

                    <div class="process-step fade-up">
                        <div class="step-number">4</div>
                        <h3 class="step-title">Ra bãi & thanh toán</h3>
                        <p class="step-description">Quét thẻ RFID và thanh toán qua mã QR code tiện lợi</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="pricing-section">
            <div class="container">
                <div class="section-header fade-up">
                    <h2 class="section-title">Bảng giá dịch vụ</h2>
                </div>

                <div class="pricing-table-container fade-up">
                    <table class="pricing-table">
                        <thead>
                            <tr>
                                <th>Loại dịch vụ</th>
                                <th>Mức giá</th>
                                <th>Tính năng đặc biệt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Đỗ xe thông thường</td>
                                <td>5.000đ/giờ</td>
                                <td>Thanh toán khi ra bãi, linh hoạt</td>
                            </tr>
                            <tr>
                                <td>Đặt chỗ trước</td>
                                <td>5.000đ/giờ</td>
                                <td>Thanh toán trước, đảm bảo có chỗ</td>
                            </tr>
                            <tr>
                                <td>Gói ngày (24h)</td>
                                <td>50.000đ/ngày</td>
                                <td>Không giới hạn ra vào trong ngày</td>
                            </tr>
                            <tr>
                                <td>Gói tuần (7 ngày)</td>
                                <td>300.000đ/tuần</td>
                                <td>Tiết kiệm 14% so với giá ngày</td>
                            </tr>
                            <tr>
                                <td>Gói tháng (30 ngày)</td>
                                <td>1.000.000đ/tháng</td>
                                <td>Tiết kiệm 33% so với giá ngày</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cta-section fade-up">
                    <div class="cta-buttons">
                        <a href="index.php?page=register" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Đăng ký ngay
                        </a>
                        <a href="index.php?page=contact" class="btn btn-outline">
                            <i class="fas fa-phone"></i>
                            Liên hệ tư vấn
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <?php
                break;
        }
        ?>
    </main>
    <?php
    if (in_array($page, ['home', 'about', 'weather', 'contact'])): ?>
    <footer class="footer">
        <div class="container footer-container">
            <div class="footer-section">
                <h3 class="footer-title">Mô tả</h3>
                <p>Hệ thống đỗ xe thông minh với công nghệ hiện đại, thanh toán tự động và quản lý hiệu quả. Chúng tôi
                    mang đến trải nghiệm đỗ xe thuận tiện và an toàn nhất cho bạn.</p>
            </div>

            <div class="footer-section">
                <h3 class="footer-title">Liên hệ</h3>
                <ul class="footer-links">
                    <li class="footer-link">
                        <i class="fas fa-map-marker-alt"></i>
                        <a>Đ. Nguyễn Kiệm/371 Đ. Hạnh Thông, Gò Vấp, HCM</a>
                    </li>
                    <li class="footer-link">
                        <i class="fas fa-phone"></i>
                        <a href="tel:0812420710">(+84) 0812420710</a>
                    </li>
                    <li class="footer-link">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:tp710@gmail.com">phucngxit710@gmail.com</a>
                    </li>
                </ul>
            </div>

            <div class="footer-section">
                <h3 class="footer-title">Liên kết nhanh</h3>
                <ul class="footer-links">
                    <li class="footer-link"><a href="index.php">Trang chủ</a></li>
                    <li class="footer-link"><a href="index.php?page=about">Giới thiệu</a></li>
                    <li class="footer-link"><a href="index.php?page=weather">Thời tiết</a></li>
                    <li class="footer-link"><a href="index.php?page=contact">Liên hệ</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3 class="footer-title">Kết nối với chúng tôi</h3>
                <p>Theo dõi chúng tôi trên mạng xã hội để cập nhật thông tin mới nhất</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/thanhphuc0710" target="_blank" class="social-link"
                        aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://x.com/nguyen_tha61270" target="_blank" class="social-link" aria-label="Twitter"><i
                            class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://www.instagram.com/phucc.nt/" target="_blank" class="social-link"
                        aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2025 XParking. Phát triển bởi <a href="https://github.com/Phuc710" target="_blank"
                        style="text-decoration: none">Phucx</a> ❤️.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <script>
    // Mobile menu toggle functions
    function toggleMobileMenu() {
        const navMenu = document.getElementById('navMenu');
        const overlay = document.querySelector('.menu-overlay');
        const toggle = document.querySelector('.mobile-menu-toggle i');

        if (!navMenu) {
            console.error('Nav menu not found');
            return;
        }

        navMenu.classList.toggle('show');
        overlay.classList.toggle('show');

        if (navMenu.classList.contains('show')) {
            toggle.classList.remove('fa-bars');
            toggle.classList.add('fa-times');
            document.body.style.overflow = 'hidden';
        } else {
            toggle.classList.remove('fa-times');
            toggle.classList.add('fa-bars');
            document.body.style.overflow = '';
        }
    }

    // Close mobile menu
    function closeMobileMenu() {
        const navMenu = document.getElementById('navMenu');
        const overlay = document.querySelector('.menu-overlay');
        const toggle = document.querySelector('.mobile-menu-toggle i');

        if (navMenu) {
            navMenu.classList.remove('show');
            overlay.classList.remove('show');
            toggle.classList.remove('fa-times');
            toggle.classList.add('fa-bars');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Intersection Observer for fade-up animations
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observe all fade-up elements
        document.querySelectorAll('.fade-up').forEach(element => {
            observer.observe(element);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Form validation with modern popup
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });

                if (!isValid) {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Thông tin chưa đầy đủ!',
                        text: 'Vui lòng điền đầy đủ các trường bắt buộc.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // Password match validation for registration
                if (form.id === 'registerForm') {
                    const password = form.querySelector('#password');
                    const confirmPassword = form.querySelector('#confirm_password');

                    if (password && confirmPassword && password.value !== confirmPassword
                        .value) {
                        confirmPassword.style.borderColor = '#ef4444';
                        event.preventDefault();
                        Swal.fire({
                            title: 'Mật khẩu không khớp!',
                            text: 'Mật khẩu xác nhận phải giống với mật khẩu đã nhập.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ef4444'
                        });
                        return;
                    }

                    // Email validation
                    const email = form.querySelector('#email');
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (email && !emailPattern.test(email.value)) {
                        email.style.borderColor = '#ef4444';
                        event.preventDefault();
                        Swal.fire({
                            title: 'Email không hợp lệ!',
                            text: 'Vui lòng nhập địa chỉ email đúng định dạng.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ef4444'
                        });
                        return;
                    }

                    // Password strength validation
                    if (password && password.value.length < 6) {
                        password.style.borderColor = '#ef4444';
                        event.preventDefault();
                        Swal.fire({
                            title: 'Mật khẩu quá yếu!',
                            text: 'Mật khẩu phải có ít nhất 6 ký tự.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ef4444'
                        });
                        return;
                    }
                }

                // Show loading state for form submission
                const submitBtn = form.querySelector(
                    'button[type="submit"], input[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.textContent;
                    submitBtn.innerHTML = '<div class="loading"></div> Đang xử lý...';
                    submitBtn.disabled = true;

                    // Re-enable button after 10 seconds as fallback
                    setTimeout(() => {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }, 10000);
                }
            });
        });
    });

    // Form field focus effects
    document.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        field.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '';
            }
        });
    });

    // Handle flash messages on home page (e.g., logout success)
    <?php 
    $flash = get_flash_message();
    if ($flash):
    ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '<?php echo $flash["type"] === "success" ? "Thành công!" : "Lỗi!"; ?>',
            text: '<?php echo addslashes($flash["message"]); ?>',
            icon: '<?php echo $flash["type"]; ?>',
            timer: 2500,
            showConfirmButton: false,
            timerProgressBar: true
        });
    });
    <?php endif; ?>
    </script>
</body>

</html>