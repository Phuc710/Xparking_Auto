<?php
/**
 * ADMIN.PHP - Trang quản trị (Refactored)
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/admin_functions.php'; // Import functions riêng cho admin

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Require admin login
require_login();
require_admin();

// Handle tab switching
$tab = $_GET['tab'] ?? 'dashboard';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_slot':
            $slot_id = $_POST['slot_id'] ?? '';
            $status = $_POST['status'] ?? '';

            if (empty($slot_id) || empty($status)) {
                set_flash_message('error', 'Thiếu thông tin cần thiết!');
                redirect('admin.php?tab=slots');
                break;
            }

            // Only allow empty <-> maintenance transitions
            if (!in_array($status, ['empty', 'maintenance'])) {
                set_flash_message('error', 'Chỉ có thể chuyển đổi giữa trạng thái trống và bảo trì!');
                redirect('admin.php?tab=slots');
                break;
            }

            if (update_slot_status($slot_id, $status)) {
                set_flash_message('success', 'Cập nhật trạng thái slot thành công!');
            } else {
                set_flash_message('error', 'Có lỗi xảy ra khi cập nhật slot!');
            }
            redirect('admin.php?tab=slots');
            break;

        case 'delete_notification':
            try {
                $db = db();
                $stmt = $db->prepare("DELETE FROM notifications");
                $stmt->execute();
                set_flash_message('success', 'Đã xóa tất cả thông báo thành công!');
            } catch (Exception $e) {
                error_log("Delete notification error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi hệ thống khi xóa thông báo!');
            }
            redirect('admin.php?tab=settings');
            break;
            
        case 'send_notification':
            $title = trim($_POST['notification_title'] ?? '');
            $message = trim($_POST['notification_message'] ?? '');
            $type = $_POST['notification_type'] ?? 'info';
            
            if (empty($title) || empty($message)) {
                set_flash_message('error', 'Vui lòng nhập đầy đủ tiêu đề và nội dung!');
                redirect('admin.php?tab=settings');
                break;
            }
            
            try {
                $db = db();
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $message,
                    $type,
                    date('Y-m-d H:i:s')
                ]);
                
                if ($result) {
                    set_flash_message('success', 'Gửi thông báo thành công!');
                } else {
                    set_flash_message('error', 'Có lỗi xảy ra khi gửi thông báo!');
                }
            } catch (Exception $e) {
                error_log("Send notification error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi hệ thống khi gửi thông báo!');
            }
            redirect('admin.php?tab=settings');
            break;
            
        case 'update_pricing':
            $price_amount = intval($_POST['price_amount'] ?? 5000);
            $price_minutes = intval($_POST['price_minutes'] ?? 60);
            $min_price = intval($_POST['min_price'] ?? 5000);
            
            if ($price_amount < 1000 || $price_amount > 500000) {
                set_flash_message('error', 'Số tiền phải từ 1.000đ đến 500.000đ!');
                redirect('admin.php?tab=settings');
                break;
            }
            
            if ($price_minutes < 1 || $price_minutes > 1440) {
                set_flash_message('error', 'Số phút phải từ 1 đến 1440!');
                redirect('admin.php?tab=settings');
                break;
            }
            
            try {
                $db = db();
                
                // MySQL UPSERT for settings
                $stmt = $db->prepare("
                    INSERT INTO settings (`key`, value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ");
                
                $stmt->execute(['price_amount', (string)$price_amount]);
                $stmt->execute(['price_minutes', (string)$price_minutes]);
                $stmt->execute(['min_price', (string)$min_price]);
                
                set_flash_message('success', 'Cập nhật giá thành công: ' . number_format($price_amount) . 'đ / ' . $price_minutes . ' phút');
            } catch (Exception $e) {
                error_log("Update pricing error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi cập nhật giá!');
            }
            redirect('admin.php?tab=settings');
            break;
    }
}

// Get data for current tab
switch ($tab) {
    case 'dashboard':
        $active_vehicles = get_active_vehicles();
        $slots = get_all_slots();
        break;
    case 'vehicles':
        $all_vehicles = get_all_vehicles_history();
        break;
    case 'slots':
        $slots = get_all_slots();
        break;
        
    case 'users':
        $users = get_all_users();
        break;
        
    case 'bookings':
        $bookings = get_all_bookings();
        break;
        
    case 'payments':
        $payments = get_all_payments();
        break;
        
    case 'logs':
        $logs = get_system_logs();
        break;
        
    case 'revenue':
        $revenue_stats = get_revenue_stats();
        $allPayments = dbGetAll('payments');
        $completedPayments = array_filter($allPayments, function($p) {
            return $p['status'] === 'completed';
        });
        $completed_payments_count = count($completedPayments);
        $total_payments_count = count($allPayments);
        $success_rate = ($total_payments_count > 0) ? ($completed_payments_count / $total_payments_count) * 100 : 0;
        break;
}

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Admin</title>

    <link rel="shortcut icon" href="/LOGO.gif" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <script src="styles/swal-config.js"></script>
</head>

<body>
    <!-- Menu Overlay -->
    <div class="menu-overlay" onclick="closeMobileMenu()"></div>

    <header class="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="/LOGO.gif" alt="XParking">
                <span>XPARKING</span>
            </a>

            <!-- Desktop Navigation -->
            <div class="desktop-nav">
                <a href="index.php" class="nav-link">Trang chủ</a>
                <a href="dashboard.php" class="btn btn-user-page">Trang người dùng</a>
                <a href="index.php?action=logout" class="btn btn-logout">Đăng xuất</a>
            </div>

            <!-- Mobile Hamburger - Always on right -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <!-- Admin Header -->
        <div class="mobile-menu-header">
            <div class="admin-avatar">
                <i class="fas fa-user-shield" style="font-size: 1.5rem;"></i>
            </div>
            <h3>Quản trị viên</h3>
            <p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>

        <!-- Navigation Section -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Navigation</div>
            <ul class="mobile-menu-list">
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=dashboard"
                        class="mobile-menu-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        Tổng quan
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=revenue"
                        class="mobile-menu-link <?php echo $tab === 'revenue' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        Doanh thu
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=slots"
                        class="mobile-menu-link <?php echo $tab === 'slots' ? 'active' : ''; ?>">
                        <i class="fas fa-parking"></i>
                        Quản lý slots
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=users"
                        class="mobile-menu-link <?php echo $tab === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Quản lý người dùng
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=vehicles"
                        class="mobile-menu-link <?php echo $tab === 'vehicles' ? 'active' : ''; ?>">
                        <i class="fas fa-car-side"></i> Lịch sử đỗ xe
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=bookings"
                        class="mobile-menu-link <?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        Lịch sử đặt chỗ
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=payments"
                        class="mobile-menu-link <?php echo $tab === 'payments' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        Lịch sử thanh toán
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=logs"
                        class="mobile-menu-link <?php echo $tab === 'logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        Nhật ký
                    </a>
                </li>
                <li class="mobile-menu-item">
                    <a href="admin.php?tab=settings"
                        class="mobile-menu-link <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i>
                        Cài đặt
                    </a>
                </li>
            </ul>
        </div>

        <!-- Actions Section -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-actions">
                <a href="index.php" class="mobile-menu-btn btn-primary">
                    <i class="fas fa-home"></i> Trang chủ
                </a>
                <a href="dashboard.php" class="mobile-menu-btn btn-primary">
                    <i class="fas fa-user"></i> Trang người dùng
                </a>
                <a href="index.php?action=logout" class="mobile-menu-btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>
    </div>

    <main class="container dashboard">
        <!-- Desktop Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div
                    style="width: 80px; height: 80px; background-color: #e0e7ff; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user-shield" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
                <h3>Quản trị viên</h3>
                <p style="color: var(--gray); font-size: 0.875rem;">
                    <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="admin.php?tab=dashboard"
                        class="sidebar-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Tổng quan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=revenue"
                        class="sidebar-link <?php echo $tab === 'revenue' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Doanh thu
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=slots" class="sidebar-link <?php echo $tab === 'slots' ? 'active' : ''; ?>">
                        <i class="fas fa-parking"></i> Quản lý slots
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=users" class="sidebar-link <?php echo $tab === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Quản lý người dùng
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=vehicles"
                        class="sidebar-link <?php echo $tab === 'vehicles' ? 'active' : ''; ?>">
                        <i class="fas fa-car-side"></i> Lịch sử đỗ xe
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=bookings"
                        class="sidebar-link <?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=payments"
                        class="sidebar-link <?php echo $tab === 'payments' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> Lịch sử thanh toán
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=logs" class="sidebar-link <?php echo $tab === 'logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Nhật ký
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=settings"
                        class="sidebar-link <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> Cài đặt
                    </a>
                </li>
            </ul>
        </aside>

        <section class="content">
            <?php
            $tabFile = __DIR__ . "/admin/tabs/$tab.php";
            if (file_exists($tabFile)) {
                require_once $tabFile;
            } else {
                echo "<div class='card'><div style='text-align:center; padding:40px;'><i class='fas fa-exclamation-circle' style='font-size:3rem; color:#d1d5db; margin-bottom:15px;'></i><h3>Tab not found</h3><p>Không tìm thấy chức năng yêu cầu.</p></div></div>";
            }
            ?>
        </section>
    </main>

    <script>
    // Mobile Menu Functions
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.querySelector('.menu-overlay');
        const toggle = document.querySelector('.mobile-menu-toggle i');

        mobileMenu.classList.toggle('show');
        overlay.classList.toggle('show');

        if (mobileMenu.classList.contains('show')) {
            toggle.classList.remove('fa-bars');
            toggle.classList.add('fa-times');
            document.body.style.overflow = 'hidden';
        } else {
            toggle.classList.remove('fa-times');
            toggle.classList.add('fa-bars');
            document.body.style.overflow = '';
        }
    }

    function closeMobileMenu() {
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.querySelector('.menu-overlay');
        const toggle = document.querySelector('.mobile-menu-toggle i');

        mobileMenu.classList.remove('show');
        overlay.classList.remove('show');
        toggle.classList.remove('fa-times');
        toggle.classList.add('fa-bars');
        document.body.style.overflow = '';
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.mobile-menu-toggle');

        if (!mobileMenu.contains(event.target) && !toggle.contains(event.target)) {
            if (mobileMenu.classList.contains('show')) {
                closeMobileMenu();
            }
        }
    });

    // Close menu when clicking on navigation links
    document.querySelectorAll('.mobile-menu-link, .mobile-menu-btn').forEach(link => {
        link.addEventListener('click', function() {
            setTimeout(() => {
                closeMobileMenu();
            }, 100);
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });

    // Update clock every second
    function updateClock() {
        const now = new Date();
        const time = now.toLocaleTimeString("vi-VN", {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
        const date = now.toLocaleDateString("vi-VN", {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });

        const formatted = `${time} ${date}`;
        const clockEl = document.getElementById("clock");
        if (clockEl) {
            clockEl.innerText = formatted;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
    });

    // Check for flash messages
    <?php 
    $flash = get_flash_message();
    if ($flash):
    ?>
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        })

        Toast.fire({
            icon: '<?php echo $flash["type"]; ?>',
            title: '<?php echo addslashes($flash["message"]); ?>'
        })
    });
    <?php endif; ?>

    // Check for login success message
    <?php if (isset($_SESSION['login_success']) && $_SESSION['login_success']): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        })

        Toast.fire({
            icon: 'success',
            title: 'Đăng nhập thành công!',
            text: 'Chào mừng Admin quay trở lại'
        })
    });
    <?php unset($_SESSION['login_success']); endif; ?>
    </script>
</body>

</html>
