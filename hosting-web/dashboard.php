<?php
/**
 * DASHBOARD.PHP - Trang điều khiển người dùng (Refactored)
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/user_functions.php'; // Import functions riêng cho user

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_login();

$user = get_user($_SESSION['user_id']);
$tab = $_GET['tab'] ?? 'overview';

// Xử lý các hành động POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_profile':
            $email = $_POST['email'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if (update_user_profile($_SESSION['user_id'], $email, $full_name, $phone)) {
                set_flash_message('success', 'Cập nhật thông tin thành công!');
            } else {
                set_flash_message('error', 'Có lỗi xảy ra khi cập nhật thông tin!');
            }
            redirect('dashboard.php?tab=profile');
            break;
            
        case 'cancel_payment':
            $payment_ref = $_POST['payment_ref'] ?? '';
            
            if (empty($payment_ref)) {
                set_flash_message('error', 'Thiếu mã thanh toán!');
                redirect('dashboard.php?tab=bookings');
                break;
            }
            
            $result = cancel_payment($payment_ref, $_SESSION['user_id']);
            
            if ($result['success']) {
                set_flash_message('success', $result['message']);
            } else {
                set_flash_message('error', $result['message']);
            }
            redirect('dashboard.php?tab=bookings');
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                set_flash_message('error', 'Mật khẩu xác nhận không khớp!');
                redirect('dashboard.php?tab=profile');
                break;
            }
            
            $result = change_user_password($_SESSION['user_id'], $current_password, $new_password);
            
            if ($result['success']) {
                set_flash_message('success', $result['message']);
            } else {
                set_flash_message('error', $result['message']);
            }
            redirect('dashboard.php?tab=profile');
            break;
            
        case 'create_booking':
            $license_plate = trim(strtoupper($_POST['license_plate'] ?? ''));
            $start_date = $_POST['start_date'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $duration = intval($_POST['duration'] ?? 1);
            
            // Validate các trường bắt buộc
            if (empty($license_plate) || empty($start_date) || empty($start_time)) {
                set_flash_message('error', 'Vui lòng điền đầy đủ thông tin!');
                redirect('dashboard.php?tab=booking');
                break;
            }
            
            // Chuẩn hóa biển số (bỏ dấu -, khoảng trắng)
            $license_plate = str_replace(['-', ' '], '', $license_plate);
            
            // Validate thời gian đỗ
            if ($duration < 1 || $duration > 24) {
                set_flash_message('error', 'Thời gian đỗ phải từ 1 đến 24 giờ!');
                redirect('dashboard.php?tab=booking');
                break;
            }
            
            try {
                // Tạo thời gian bắt đầu và kết thúc
                $start_datetime = new DateTime("$start_date $start_time");
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new DateInterval("PT{$duration}H"));
                
                // Kiểm tra thời gian đặt chỗ không được trong quá khứ
                $now = new DateTime();
                if ($start_datetime < $now) {
                    set_flash_message('error', 'Thời gian đặt chỗ không được trong quá khứ!');
                    redirect('dashboard.php?tab=booking');
                    break;
                }
                
                // Format cho database
                $start_time_db = $start_datetime->format('Y-m-d H:i:s');
                $end_time_db = $end_datetime->format('Y-m-d H:i:s');
                
                // Tạo booking (KHÔNG gán slot - slot được gán khi xe vào bãi)
                $result = create_booking($_SESSION['user_id'], $license_plate, $start_time_db, $end_time_db);
                
                if ($result['success']) {
                    set_flash_message('success', 'Đặt chỗ thành công! Vui lòng thanh toán trong vòng 10 phút.');
                    redirect('dashboard.php?tab=payment&ref=' . $result['payment_ref']);
                } else {
                    set_flash_message('error', $result['message']);
                    redirect('dashboard.php?tab=booking');
                }
                
            } catch (Exception $e) {
                error_log("Booking form error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi xử lý thời gian. Vui lòng kiểm tra lại!');
                redirect('dashboard.php?tab=booking');
            }
            break;
            
        case 'cancel_booking':
            $booking_id = $_POST['booking_id'] ?? '';
            
            if (empty($booking_id)) {
                set_flash_message('error', 'Booking ID không hợp lệ!');
                redirect('dashboard.php?tab=bookings');
                break;
            }
            
            $result = cancel_booking($booking_id, $_SESSION['user_id']);
            
            if ($result['success']) {
                set_flash_message('success', $result['message']);
            } else {
                set_flash_message('error', $result['message']);
            }
            
            redirect('dashboard.php?tab=bookings');
            break;
    }
}

// Lấy dữ liệu theo tab hiện tại
switch ($tab) {
    case 'bookings':
        $bookings = get_user_bookings($_SESSION['user_id']);
        break;
    case 'overview':
        $user_stats = get_user_statistics($_SESSION['user_id']);
        $slots = get_slots_display_status();
        break;
    case 'booking':
        $available_slots = get_booking_available_slots();
        break;
    case 'vehicles':
        $user_vehicles = get_user_vehicles($_SESSION['user_id']);
        break;
        
    case 'payment':
        $payment_ref = $_GET['ref'] ?? '';
        $renew = isset($_GET['renew']); // Flag để tạo QR mới
        $qr_data = null;

        if ($payment_ref) {
            try {
                // Lấy payment từ Database
                $payment = get_payment_by_ref($payment_ref);
                
                if ($payment) {
                    // Tính seconds elapsed từ created_at
                    $created_time = new DateTime($payment['created_at']);
                    $now = new DateTime();
                    $seconds_elapsed = $now->getTimestamp() - $created_time->getTimestamp();
                    $time_remaining = max(0, (QR_EXPIRE_MINUTES * 60) - $seconds_elapsed);
                    
                    // Kiểm tra trạng thái
                    if ($payment['status'] === 'completed') {
                        set_flash_message('success', 'Thanh toán đã được hoàn thành!');
                        redirect('dashboard.php?tab=bookings');
                        break;
                    }
                    
                    if (in_array($payment['status'], ['cancelled', 'failed'])) {
                        set_flash_message('error', 'Thanh toán đã bị hủy hoặc thất bại!');
                        redirect('dashboard.php?tab=bookings');
                        break;
                    }
                    
                    // Nếu đã hết hạn hoặc status = expired
                    if ($payment['status'] === 'expired' || $time_remaining <= 0) {
                        // Cập nhật status thành expired nếu chưa
                        if ($payment['status'] !== 'expired') {
                            dbUpdate('payments', 'id', $payment['id'], ['status' => 'expired']);
                        }
                        
                        // Hủy booking liên quan nếu chưa hủy
                        if ($payment['booking_id']) {
                            $booking = dbGetOne('bookings', 'id', $payment['booking_id']);
                            if ($booking && !in_array($booking['status'], ['cancelled', 'completed'])) {
                                dbUpdate('bookings', 'id', $payment['booking_id'], ['status' => 'cancelled']);
                            }
                        }
                        
                        // Hiển thị trang đã hết hạn - yêu cầu tạo đơn mới
                        $qr_data = [
                            'success' => false,
                            'expired' => true,
                            'payment_ref' => $payment_ref,
                            'amount' => $payment['amount']
                        ];
                    } else if ($payment['status'] === 'pending') {
                        // Payment còn hiệu lực - hiển thị QR với thời gian còn lại
                        $payment_id = $payment['id'];
                        $qr_data = generate_payment_qr($payment_id);
                        $qr_data['time_remaining'] = $time_remaining;
                    }
                } else {
                    set_flash_message('error', 'Không tìm thấy thanh toán!');
                    redirect('dashboard.php?tab=bookings');
                }
            } catch (Exception $e) {
                error_log("Payment error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi hệ thống!');
                redirect('dashboard.php?tab=bookings');
            }
        } else {
            set_flash_message('error', 'Thiếu mã thanh toán.');
            redirect('dashboard.php?tab=bookings');
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking</title>

    <link rel="shortcut icon" href="/LOGO.gif" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="styles/swal-config.js"></script>
    <script>
    // Check for login success message
    <?php if (isset($_SESSION['login_success']) && $_SESSION['login_success']): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Đăng nhập thành công!',
            text: 'Chào mừng bạn quay trở lại',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
            timerProgressBar: true
        });
    });
    <?php unset($_SESSION['login_success']); endif; ?>
    
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
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="styles/dashboard.css">

    <script>
    // Check slot availability trước khi vào trang booking
    function checkSlotsAndNavigate(event) {
        event.preventDefault();
        
        Swal.fire({
            title: 'Đang kiểm tra chỗ trống...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
        
        fetch('<?php echo SITE_URL; ?>/api/slots_status.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.available > 0) {
                    // Có chỗ trống - cho vào trang booking
                    window.location.href = 'dashboard.php?tab=booking';
                } else {
                    // Hết chỗ - hiện thông báo
                    const d = data.data;
                    Swal.fire({
                        title: 'Xin lỗi! Bãi đỗ đã đầy',
                        html: '<div style="text-align:left;padding:10px 20px;">' +
                              '<p style="margin:10px 0;"><strong>Tình trạng bãi xe:</strong></p>' +
                              '<p style="margin:5px 0;">• Tổng số chỗ: <strong>' + (d?.total || 0) + '</strong></p>' +
                              '<p style="margin:5px 0;">• Chỗ trống: <strong style="color:#10b981;">' + (d?.empty || 0) + '</strong></p>' +
                              '<p style="margin:5px 0;">• Đang có xe: <strong style="color:#ef4444;">' + (d?.occupied || 0) + '</strong></p>' +
                              '<p style="margin:5px 0;">• Bảo trì: <strong style="color:#6b7280;">' + (d?.maintenance || 0) + '</strong></p>' +
                              '<p style="margin:5px 0;">• Đã đặt trước: <strong style="color:#f59e0b;">' + (d?.reserved || 0) + '</strong></p>' +
                              '<hr style="margin:15px 0;border:none;border-top:1px solid #e5e7eb;">' +
                              '<p style="margin:10px 0;color:#6b7280;">Vui lòng quay lại sau hoặc liên hệ quản lý để được hỗ trợ.</p>' +
                              '</div>',
                        icon: 'warning',
                        confirmButtonText: 'Yes',
                        confirmButtonColor: '#f59e0b',
                        width: '500px'
                    });
                }
            })
            .catch(err => {
                console.error('Check slots error:', err);
                Swal.fire({
                    title: 'Lỗi kết nối',
                    text: 'Không thể kiểm tra tình trạng bãi xe. Vui lòng thử lại!',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
    }
    
    // Hủy thanh toán
    function confirmCancelPayment(paymentRef) {
        Swal.fire({
            title: "Xác nhận hủy thanh toán?",
            text: "Bạn sẽ phải thanh toán lại từ đầu!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: '<span style="padding: 0 2rem;">HỦY THANH TOÁN</span>',
            cancelButtonText: '<span style="padding: 0 2rem;">TIẾP TỤC</span>',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#10b981',
            reverseButtons: true,
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancelPaymentRef').value = paymentRef;
                document.getElementById('cancelPaymentForm').submit();
            }
        });
    }

    // Hủy booking
    function confirmCancelBooking(bookingId) {
        Swal.fire({
            title: "Xác nhận hủy đặt chỗ?",
            text: "Bạn sẽ không thể hoàn tác hành động này!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: 'Xác nhận hủy',
            cancelButtonText: 'Giữ lại',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#10b981',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancelBookingId').value = bookingId;
                document.getElementById('cancelBookingForm').submit();
            }
        });
    }
    </script>
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
            <ul class="desktop-nav">
                <li><a href="index.php" class="nav-link">Trang chủ</a></li>
                <li><a href="#" onclick="checkSlotsAndNavigate(event)" class="nav-link">Đặt chỗ</a></li>
                <?php if (is_admin()): ?>
                <li><a href="admin.php" class="btn user-page">Quản trị</a></li>
                <?php endif; ?>
                <li><a href="index.php?action=logout" class="btn logout">Đăng xuất</a></li>
            </ul>

            <!-- Mobile Hamburger -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="user-avatar">
                <i class="fas fa-user" style="font-size: 1.5rem;"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>

        <div class="mobile-menu-nav">
            <a href="dashboard.php?tab=overview" class="<?php echo $tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Tổng quan
            </a>
            <a href="#" onclick="checkSlotsAndNavigate(event)" class="<?php echo $tab === 'booking' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i> Đặt chỗ mới
            </a>
            <a href="dashboard.php?tab=bookings" class="<?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ
            </a>
            <a href="dashboard.php?tab=profile" class="<?php echo $tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> Thông tin cá nhân
            </a>

            <div style="border-top: 1px solid #e5e7eb; margin: 1rem 0; padding-top: 1rem;">
                <a href="index.php">
                    <i class="fas fa-home"></i> Trang chủ
                </a>
                <?php if (is_admin()): ?>
                <a href="admin.php">
                    <i class="fas fa-shield-alt"></i> Quản trị
                </a>
                <?php endif; ?>
                <a href="index.php?action=logout">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>
    </div>

    <main class="container dashboard">
        <!-- Desktop Sidebar -->
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div
                    style="width: 80px; height: 80px; background-color: #e0e7ff; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></h3>
                <p style="color: var(--gray); font-size: 0.875rem;">
                    <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=overview"
                        class="sidebar-link <?php echo $tab === 'overview' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Tổng quan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="#" onclick="checkSlotsAndNavigate(event)"
                        class="sidebar-link <?php echo $tab === 'booking' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-plus"></i> Đặt chỗ mới
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=bookings"
                        class="sidebar-link <?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=profile"
                        class="sidebar-link <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i> Thông tin cá nhân
                    </a>
                </li>
            </ul>
        </aside>

        <section class="content">
            <?php
            $tabFile = __DIR__ . "/user/tabs/$tab.php";
            if (file_exists($tabFile)) {
                require_once $tabFile;
            } else {
                echo "<div class='card'><div style='text-align:center; padding:40px;'><i class='fas fa-exclamation-circle' style='font-size:3rem; color:#d1d5db; margin-bottom:15px;'></i><h3>Tab not found</h3><p>Không tìm thấy chức năng yêu cầu.</p></div></div>";
            }
            ?>
        </section>
    </main>

    <!-- Các form ẩn để xử lý hành động -->
    <form id="cancelBookingForm" method="post" action="dashboard.php?tab=bookings" style="display: none;">
        <input type="hidden" name="action" value="cancel_booking">
        <input type="hidden" name="booking_id" id="cancelBookingId">
    </form>

    <form id="cancelPaymentForm" method="post" action="dashboard.php?tab=bookings" style="display: none;">
        <input type="hidden" name="action" value="cancel_payment">
        <input type="hidden" name="payment_ref" id="cancelPaymentRef">
    </form>

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

    // Close menu on click outside
    document.addEventListener('click', function(event) {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.mobile-menu-toggle');

        if (!mobileMenu.contains(event.target) && !toggle.contains(event.target)) {
            if (mobileMenu.classList.contains('show')) {
                closeMobileMenu();
            }
        }
    });

    // Close menu on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });

    // Slot selection
    function selectSlot(element, slotId) {
        document.querySelectorAll('.slot-card').forEach(s => s.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('slot_id').value = slotId;
        updateEstimatedPrice();
    }

    function updateEstimatedPrice() {
        const el = document.getElementById('estimated_price');
        if (el) el.textContent = (parseInt(document.getElementById('duration')?.value || 1) * 5000).toLocaleString('vi-VN') + '₫';
    }

    // Payment handlers - REBUILT POLLING SYSTEM
    var paymentDone = false;
    var pollTimer = null;
    
    function handlePaymentSuccess(amount) {
        if (paymentDone) return;
        paymentDone = true;
        
        console.log('Payment success detected:', amount);
        
        // Stop polling immediately
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (window.paymentCountdownInterval) {
            clearInterval(window.paymentCountdownInterval);
        }
        
        // Update UI elements
        const statusEl = document.getElementById('payment-status');
        const timerEl = document.getElementById('countdown-timer');
        const msgEl = document.getElementById('payment-message');
        
        if (statusEl) {
            statusEl.className = 'badge badge-success';
            statusEl.textContent = 'Đã thanh toán';
        }
        if (timerEl) {
            timerEl.textContent = '✓ Hoàn tất';
            timerEl.style.color = '#10b981';
        }
        if (msgEl) {
            msgEl.innerHTML = '<div style="text-align:center"><i class="fas fa-check-circle" style="color:#10b981;font-size:3rem"></i><p style="margin-top:10px;color:#10b981;font-weight:600;">Thanh toán thành công!</p></div>';
        }
        
        // Show success notification with fireworks
        showPaymentSuccess(amount).then(() => {
            // Redirect after animation completes
            setTimeout(() => {
                window.location.href = 'dashboard.php?tab=bookings';
            }, 500);
        });
    }

    function handlePaymentExpired() {
        if (paymentDone) return;
        paymentDone = true;
        
        console.log('Payment expired');
        
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (window.paymentCountdownInterval) {
            clearInterval(window.paymentCountdownInterval);
        }
        
        const statusEl = document.getElementById('payment-status');
        const msgEl = document.getElementById('payment-message');
        
        if (statusEl) {
            statusEl.className = 'badge badge-danger';
            statusEl.textContent = 'Hết hạn';
        }
        if (msgEl) {
            msgEl.innerHTML = '<div style="text-align:center"><i class="fas fa-times-circle" style="color:#ef4444;font-size:2rem;margin-bottom:10px"></i><p><strong>QR Code đã hết hạn!</strong></p><p style="color:#6b7280;font-size:0.9rem;">Vui lòng tạo đơn hàng mới</p></div>';
        }
    }

    // REBUILT: Simplified and more reliable polling
    function checkPaymentStatus(ref) {
        if (paymentDone) return;
        
        fetch('<?php echo SITE_URL; ?>/api/check_payment.php?ref=' + encodeURIComponent(ref) + '&_t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                
                if (data.status === 'completed') {
                    const amount = Number(data.amount || 0).toLocaleString('vi-VN') + '₫';
                    handlePaymentSuccess(amount);
                } else if (data.status === 'expired' || data.status === 'cancelled' || data.status === 'failed') {
                    handlePaymentExpired();
                }
                // For pending status, continue polling
            })
            .catch(error => {
                console.error('Payment poll error:', error);
                // Continue polling on error
            });
    }

    // Start improved polling system
    function startPaymentPolling(ref) {
        if (!ref || paymentDone) return;
        
        
        // Reset state
        paymentDone = false;
        if (pollTimer) clearInterval(pollTimer);
        
        // First check after 2 seconds
        setTimeout(() => {
            if (!paymentDone) checkPaymentStatus(ref);
        }, 2000);
        
        // Then poll every 1.5 seconds for faster detection
        pollTimer = setInterval(() => {
            if (!paymentDone) {
                checkPaymentStatus(ref);
            } else {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }, 1500);
        
        // Safety timeout after 15 minutes
        setTimeout(() => {
            if (pollTimer && !paymentDone) {
                clearInterval(pollTimer);
                handlePaymentExpired();
            }
        }, 15 * 60 * 1000);
    }


    // ===== QR CODE DOWNLOAD FUNCTION =====
    function downloadQRCode() {
        const qrImage = document.getElementById('qr-code-img');
        if (!qrImage || !qrImage.src) {
            // Use Toast instead of undefined showNotification
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'error',
                title: 'Không tìm thấy QR code để tải xuống!'
            });
            return;
        }

        // Create a canvas to convert image to downloadable format
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Wait for image to load if not already loaded
        const img = new Image();
        img.crossOrigin = 'anonymous';
        
        img.onload = function() {
            canvas.width = img.width;
            canvas.height = img.height;
            
            // Draw white background
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw QR code
            ctx.drawImage(img, 0, 0);
            
            // Convert to blob and download
            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'XParking_QR_' + new Date().getTime() + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                // Show success toast
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'QR code đã được tải xuống!'
                });
            }, 'image/png');
        };
        
        img.onerror = function() {
            // Fallback: try to download directly from source
            const a = document.createElement('a');
            a.href = qrImage.src;
            a.download = 'XParking_QR_' + new Date().getTime() + '.png';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            // Show success toast for fallback
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'QR code đã được tải xuống!'
            });
        };
        
        img.src = qrImage.src;
    }

    // License plate uppercase
    const lp = document.getElementById('license_plate');
    if (lp) lp.addEventListener('input', function() { this.value = this.value.toUpperCase(); });

    // Duration change
    const dur = document.getElementById('duration');
    if (dur) dur.addEventListener('input', updateEstimatedPrice);

    // Check for flash messages - Use Toast for dashboard
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

    document.addEventListener('DOMContentLoaded', function() {
        const currentTab = '<?php echo $tab; ?>';
        if (currentTab === 'payment') {
            const urlParams = new URLSearchParams(window.location.search);
            const paymentRef = urlParams.get('ref');
            if (paymentRef && !paymentDone) {
                startPaymentPolling(paymentRef);
            }
        }
    });
    </script>
</body>

</html>
