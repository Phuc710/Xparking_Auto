<?php
/**
 * PAYMENT.PHP - Trang thanh toán vé xe (Walk-in + Booking)
 * UI Redesign: Minimal & Modern + New Fireworks
 */
require_once 'includes/config.php';

$ticket_code = strtoupper(trim($_GET['ticket'] ?? ''));
if (!preg_match('/^VE[A-F0-9]{8}$/i', $ticket_code)) {
    die('<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Lỗi</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f3f4f6;color:#374151}</style></head><body><h3>❌ Mã vé không hợp lệ</h3></body></html>');
}

$API_URL = SITE_URL . '/api/gateway.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - <?= $ticket_code ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" href="/LOGO.gif" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            line-height: 1.5;
        }

        .payment-card {
            background: var(--card);
            width: 100%;
            max-width: 400px;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 10; /* Đảm bảo card nằm dưới pháo hoa */
        }

        /* Mobile Optimizations */
        @media (max-width: 480px) {
            body {
                padding: 0;
                background: white; /* Native feel */
                align-items: flex-start;
            }
            
            .payment-card {
                max-width: 100%;
                border-radius: 0;
                box-shadow: none;
                min-height: 100vh;
            }
            
            .card-header {
                padding-top: 40px; /* Status bar space */
            }
            
            .amount-value {
                font-size: 3rem;
            }
            
            .btn-action {
                padding: 16px; /* Larger touch target */
                font-size: 1.1rem;
            }
        }

        .card-header {
            padding: 24px 24px 0;
            text-align: center;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.6rem;
            color: var(--text);
            margin-bottom: 8px;
        }
        
        .brand i { color: var(--primary); }

        .ticket-code {
            font-family: 'Monaco', 'Consolas', monospace;
            color: var(--text-light);
            font-size: 0.875rem;
            background: var(--bg);
            padding: 4px 12px;
            border-radius: 100px;
            display: inline-block;
        }

        .card-body {
            padding: 24px;
        }

        /* Views */
        .view-section {
            transition: opacity 0.3s ease;
        }
        
        .hidden { display: none !important; }
        
        /* Info Rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.9375rem;
        }
        
        .info-label { color: var(--text-light); }
        .info-value { font-weight: 500; }

        /* Amount */
        .amount-box {
            text-align: center;
            margin: 24px 0;
        }
        
        .amount-label {
            font-size: 0.875rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .amount-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }

        /* QR Code */
        .qr-container {
            background: white;
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            margin: 0 auto 20px;
            width: fit-content;
            position: relative;
        }
        
        .qr-container img {
            display: block;
            width: 180px;
            height: 180px;
            border-radius: 8px;
        }
        
        .scan-hint {
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-success { background: #d1fae5; color: #059669; }
        .status-used { background: #f3f4f6; color: #4b5563; }
        .status-error { background: #fee2e2; color: #dc2626; }

        /* Success View */
        .success-view {
            text-align: center;
            padding: 20px 0;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
        }
        
        .success-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 8px;
        }
        
        .success-msg {
            color: var(--text-light);
            margin-bottom: 24px;
        }

        .btn-action {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--bg);
            color: var(--text);
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .btn-action:hover { background: #e5e7eb; }

        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .animate-slide-up { animation: slideUp 0.4s ease-out; }
        .animate-pop-in { animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        /* Loading Spinner */
        .spinner {
            width: 20px; height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Overstay Alert */
        .overstay-alert {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #b45309;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-bottom: 16px;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        /* Download QR Button */
        .btn-download-qr {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            margin-top: 16px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }

        .btn-download-qr:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-download-qr:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>

<div class="payment-card animate-slide-up">
    <div class="card-header">
        <div class="brand">
            <span>🚗 XPARKING</span>
        </div>
    </div>

    <div class="card-body">
        <div id="view-pending" class="view-section">
            
            <div id="overstay-box" class="overstay-alert hidden">
                <span style="font-size: 1.2rem">⚠️</span>
                <div>
                    <strong>Cần thanh toán phí phát sinh</strong>
                    <div style="margin-top: 2px">Thời gian quá giờ: <b id="overstay-mins">0</b> phút</div>
                </div>
            </div>

            <div class="amount-box">
                <div class="amount-label">Tổng thanh toán</div>
                <div class="amount-value" id="amount">--</div>
                <div style="margin-top: 8px">
                    <span class="status-badge status-pending">
                        <div class="spinner"></div> Đang chờ thanh toán
                    </span>
                </div>
            </div>

            <div class="qr-container">
                <img id="qr-img" src="" alt="QR Code">
            </div>

            <div class="scan-hint">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                Sử dụng App Ngân hàng để quét
            </div>

            <!-- Download QR Button -->
            <button onclick="downloadQRCode()" class="btn-download-qr">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Tải QR Code
            </button>
            
            <div class="info-row" style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px;">
                <span class="info-label">Giờ vào</span>
                <span class="info-value" id="time-in">--:--</span>
            </div>
            <div class="info-row">
                <span class="info-label">Thời lượng</span>
                <span class="info-value" id="duration">-- phút</span>
            </div>
        </div>

        <div id="view-success" class="view-section hidden success-view animate-pop-in">
            <div class="success-icon">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="success-title">Thanh toán thành công!</h2>
            <p class="success-msg">Cảm ơn bạn đã sử dụng dịch vụ</p>
            
            <div class="amount-value" id="paid-amount" style="font-size: 2rem; margin-bottom: 24px;">--</div>
            
            <div style="background: #f0fdf4; padding: 16px; border-radius: 12px; margin-bottom: 24px;">
                <p style="color: #166534; font-weight: 600;">VÉ ĐÃ ĐƯỢC THANH TOÁN</p>
            </div>

            
        </div>

        <div id="view-used" class="view-section hidden success-view">
             <div class="success-icon" style="background: var(--text-light)">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="success-title" style="color: var(--text)">Vé đã sử dụng</h2>
            <p class="success-msg">Hẹn gặp lại quý khách!</p>
            
            <div class="info-row" style="margin-top: 20px;">
                <span class="info-label">Giờ vào</span>
                <span class="info-value" id="used-in">--:--</span>
            </div>
            <div class="info-row">
                <span class="info-label">Giờ ra</span>
                <span class="info-value" id="used-out">--:--</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tổng tiền</span>
                <span class="info-value" id="used-amount">--</span>
            </div>
        </div>
        
        <div id="view-expired" class="view-section hidden success-view">
            <div class="success-icon" style="background: var(--warning)">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 class="success-title" style="color: var(--warning)">Vé đã hết hạn</h2>
            <p class="success-msg">Booking đã quá thời gian và xe chưa vào bãi</p>
            <div style="background: #fef3c7; padding: 16px; border-radius: 12px;">
                <p style="color: #b45309; font-weight: 600;">Vui lòng đặt vé mới nếu cần</p>
            </div>
        </div>

        <div id="view-error" class="view-section hidden" style="text-align: center; padding: 20px;">
            <div style="font-size: 3rem; margin-bottom: 10px;">❌</div>
            <h3 id="error-msg">Có lỗi xảy ra</h3>
        </div>

    </div>
</div>

<script>
    const TICKET = '<?= $ticket_code ?>';
    const API_URL = '<?= $API_URL ?>';
    const BANK_INFO = {
        ID: '<?= BANK_ID ?>',
        ACC: '<?= BANK_ACCOUNT ?>',
        NAME: '<?= BANK_NAME ?>'
    };
    
    let pollTimer = null;
    let isCompleted = false;
    let lastStatus = '';

    // Format helpers
    const fmtMoney = n => parseInt(n).toLocaleString('vi-VN') + 'đ';
    const fmtDate = s => s ? new Date(s).toLocaleString('vi-VN', {hour:'2-digit', minute:'2-digit', day:'2-digit', month:'2-digit'}) : '--:--';

    // QR Gen
    const getQR = (amount, content) => 
        `https://img.vietqr.io/image/${BANK_INFO.ID}-${BANK_INFO.ACC}-compact.png?amount=${amount}&addInfo=${encodeURIComponent(content)}`;

    // Payment success handler - simple version
    let hasShowedSuccess = false;

    // UI Switcher
    function switchView(viewId) {
        document.querySelectorAll('.view-section').forEach(el => el.classList.add('hidden'));
        document.getElementById(viewId).classList.remove('hidden');
    }

    // Download QR Code Function
    function downloadQRCode() {
        const qrImage = document.getElementById('qr-img');
        if (!qrImage || !qrImage.src) {
            alert('QR Code chưa sẵn sàng để tải xuống');
            return;
        }

        // Create canvas to convert image
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
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
                a.download = 'XParking_QR_' + TICKET + '_' + Date.now() + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 'image/png');
        };
        
        img.onerror = function() {
            // Fallback: direct download
            const a = document.createElement('a');
            a.href = qrImage.src;
            a.download = 'XParking_QR_' + TICKET + '.png';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        };
        
        img.src = qrImage.src;
    }

    // Main logic
    async function checkStatus() {
        if (isCompleted) return;
        
        try {
            const res = await fetch(`${API_URL}?action=get_ticket&ticket_code=${TICKET}&_t=${Date.now()}`);
            const data = await res.json();
            
            if (!data.success) {
                switchView('view-error');
                document.getElementById('error-msg').innerText = 'Vé không tồn tại';
                return;
            }

            const t = data.ticket;
            
            // Handle State
            if (t.status === 'USED') {
                isCompleted = true;
                clearInterval(pollTimer);
                
                document.getElementById('used-in').innerText = fmtDate(t.time_in);
                document.getElementById('used-out').innerText = fmtDate(t.time_out);
                document.getElementById('used-amount').innerText = fmtMoney(t.amount || 0);
                switchView('view-used');
                return;
            }

            if (t.status === 'PAID') {
                // Check if expired (booking hết hạn mà xe chưa vào)
                if (t.is_expired) {
                    isCompleted = true;
                    clearInterval(pollTimer);
                    switchView('view-expired');
                    return;
                }
                
                // Check Overstay (xe trong bãi quá giờ)
                if (t.has_overstay && t.overstay_amount > 0) {
                    // Show Overstay Payment
                    document.getElementById('overstay-box').classList.remove('hidden');
                    document.getElementById('overstay-mins').innerText = t.overstay_minutes;
                    
                    document.getElementById('amount').innerText = fmtMoney(t.overstay_amount);
                    document.getElementById('qr-img').src = getQR(t.overstay_amount, t.overstay_payment_ref);
                    
                    document.getElementById('time-in').innerText = fmtDate(t.time_in);
                    document.getElementById('duration').innerText = `Quá ${t.overstay_minutes} phút`;
                    
                    switchView('view-pending');
                } else {
                    // Fully Paid
                    if (lastStatus !== 'PAID' && !hasShowedSuccess) {
                        hasShowedSuccess = true;
                        // Simple success indication without confetti
                    }
                    isCompleted = true;
                    clearInterval(pollTimer);
                    
                    document.getElementById('paid-amount').innerText = fmtMoney(t.amount || 0);
                    switchView('view-success');
                }
                return;
            }

            // Normal Pending
            lastStatus = t.status;
            document.getElementById('overstay-box').classList.add('hidden');
            document.getElementById('amount').innerText = fmtMoney(t.amount || 0);
            document.getElementById('qr-img').src = getQR(t.amount || 0, TICKET);
            document.getElementById('time-in').innerText = fmtDate(t.time_in);
            document.getElementById('duration').innerText = (t.minutes || 0) + ' phút';
            
            switchView('view-pending');

        } catch (e) {
            console.error(e);
        }
    }

    // Init
    checkStatus();
    pollTimer = setInterval(checkStatus, 1500); // 1.5s polling

</script>
</body>
</html>