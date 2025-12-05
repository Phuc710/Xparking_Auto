<?php
/**
 * GENERATE TICKET - Hiển thị vé xe (vãng lai + đặt trước)
 * URL: /api/generate_ticket.php?code=VE12345678
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/csdl.php';

$ticket_code = strtoupper($_GET['code'] ?? '');
if (empty($ticket_code)) die('Mã vé không hợp lệ');

// Lấy thông tin ticket
$ticket = dbGetOne('tickets', 'ticket_code', $ticket_code);
if (!$ticket) die('Không tìm thấy vé');

// Kiểm tra quyền truy cập
$is_walk_in = empty($ticket['booking_id']); // Vé vãng lai không có booking_id

if (!$is_walk_in) {
    // Vé đặt trước - yêu cầu đăng nhập và kiểm tra owner
    if (!is_logged_in()) {
        header('Location: ../index.php?page=login');
        exit;
    }
    $booking = dbGetOne('bookings', 'id', $ticket['booking_id']);
    if (!$booking || $booking['user_id'] != $_SESSION['user_id']) {
        die('Bạn không có quyền xem vé này');
    }
}

// Format thời gian
$time_in = $ticket['time_in'] ? date('H:i:s', strtotime($ticket['time_in'])) : date('H:i:s', strtotime($ticket['created_at']));
$date_in = $ticket['time_in'] ? date('d/m/Y', strtotime($ticket['time_in'])) : date('d/m/Y', strtotime($ticket['created_at']));
$status_text = $ticket['status'] === 'PAID' ? 'ĐÃ THANH TOÁN' : ($ticket['status'] === 'USED' ? 'ĐÃ SỬ DỤNG' : 'CHƯA THANH TOÁN');

// QR Data - URL đến trang thanh toán
$qr_content = 'http://xparking.elementfx.com/payment.php?ticket=' . $ticket['ticket_code'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vé Gửi Xe - <?php echo $ticket_code; ?></title>
    <link rel="shortcut icon" href="/LOGO.gif" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        
        .ticket-container {
            width: 100%;
            max-width: 450px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Header */
        .ticket-header {
            background: #1A2530;
            padding: 30px 20px;
            text-align: center;
        }
        
        .ticket-header h1 {
            color: #E67E22;
            font-size: 40px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        /* Body */
        .ticket-body {
            padding: 30px 20px;
            text-align: center;
        }
        
        .ticket-title {
            color: #2C3E50;
            font-size: 45px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        
        /* QR Code */
        .qr-wrapper {
            display: inline-block;
            padding: 15px;
            border: 2px solid #BDC3C7;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        #qrcode {
            width: 240px;
            height: 240px;
        }
        
        #qrcode img {
            width: 100%;
            height: 100%;
        }
        
        /* Dashed line */
        .dashed-line {
            border: none;
            border-top: 2px dashed #BDC3C7;
            margin: 20px 20px;
        }
        
        /* Info rows */
        .info-section {
            padding: 0 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
        }
        
        .info-label {
            color: #000;
            font-size: 18px;
        }
        
        .info-value {
            color: #2C3E50;
            font-size: 22px;
            font-weight: bold;
        }
        
        /* Footer */
        .ticket-footer {
            background: #1A2530;
            padding: 25px 20px;
            text-align: center;
        }
        
        .footer-text {
            color: #fff;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .hotline {
            color: #E67E22;
            font-size: 18px;
            font-weight: bold;
        }
        
        /* Actions */
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .btn-download {
            background: #E67E22;
            color: #fff;
        }
        
        .btn-back {
            background: #3498db;
            color: #fff;
        }
        
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .actions {
                display: none;
            }
            .ticket-container {
                box-shadow: none;
            }
        }

        /* Laptop/PC Tweaks for Compact View */
        @media (min-width: 1024px) {
            body {
                justify-content: center;
                padding: 10px;
            }
            
            .ticket-container {
                max-width: 380px; /* Smaller width */
                border-radius: 15px;
            }
            
            .ticket-header {
                padding: 15px 10px;
            }
            
            .ticket-header h1 {
                font-size: 28px; /* Smaller font */
            }
            
            .ticket-body {
                padding: 15px 10px;
            }
            
            .ticket-title {
                font-size: 32px;
                margin-bottom: 15px;
            }
            
            .qr-wrapper {
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 10px;
            }
            
            #qrcode {
                width: 180px; /* Smaller QR */
                height: 180px;
            }
            
            .dashed-line {
                margin: 15px 15px;
            }
            
            .info-row {
                padding: 8px 0;
            }
            
            .info-label {
                font-size: 15px;
            }
            
            .info-value {
                font-size: 18px;
            }
            
            .ticket-footer {
                padding: 15px 10px;
            }
            
            .footer-text {
                font-size: 14px;
            }
            
            .hotline {
                font-size: 16px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container" id="ticket">
        <!-- Header -->
        <div class="ticket-header">
            <h1>XPARKING</h1>
        </div>
        
        <!-- Body -->
        <div class="ticket-body">
            <div class="ticket-title">VÉ GỬI XE</div>
            
            <!-- QR Code -->
            <div class="qr-wrapper">
                <div id="qrcode"></div>
            </div>
        </div>
        
        <hr class="dashed-line">
        
        <!-- Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Mã vé:</span>
                <span class="info-value"><?php echo htmlspecialchars($ticket_code); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Giờ vào:</span>
                <span class="info-value"><?php echo $time_in; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Ngày vào:</span>
                <span class="info-value"><?php echo $date_in; ?></span>
            </div>
        </div>
        
        <hr class="dashed-line">
        
        <!-- Footer -->
        <div class="ticket-footer">
            <div class="footer-text" style="font-size: 18px; font-weight: bold; color: <?php echo $ticket['status'] === 'PAID' ? '#2ecc71' : '#e74c3c'; ?>;">
                <?php echo $status_text; ?>
            </div>
            <div class="hotline">HOTLINE: 0812.420.710</div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="actions">
        <button class="btn btn-download" onclick="downloadTicket()">
            <i class="fas fa-download"></i> Tải vé
        </button>
        <?php if (!$is_walk_in): ?>
        <a href="../dashboard.php?tab=bookings" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
        <?php endif; ?>
    </div>
    
    <script>
        // Generate QR Code
        document.addEventListener('DOMContentLoaded', function() {
            var qr = qrcode(0, 'M');
            qr.addData('<?php echo $qr_content; ?>');
            qr.make();
            document.getElementById('qrcode').innerHTML = qr.createImgTag(6, 0);
        });
        
        // Download ticket as image
        function downloadTicket() {
            const ticket = document.getElementById('ticket');
            
            html2canvas(ticket, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'VE_<?php echo $ticket_code; ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>
