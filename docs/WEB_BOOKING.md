# 🌐 HỆ THỐNG WEB & BOOKING

Tài liệu này mô tả các chức năng của hệ thống Web Server quản lý XParking.

**URL:** `https://xparking.elementfx.com`

---

## 🖥️ Giao Diện Quản Lý (Dashboard)

### 1. Dashboard Tổng Quan (`dashboard.php`)

- **Thống kê thời gian thực:**
  - Số lượng xe đang trong bãi.
  - Số lượt xe vào/ra trong ngày.
  - Doanh thu ước tính trong ngày.
- **Biểu đồ:**
  - Biểu đồ xu hướng doanh thu (`api/get_revenue_trend.php`).
  - Biểu đồ phân bổ loại xe/giờ cao điểm.

### 2. Quản Lý Vé & Booking

- **Danh sách vé:** Xem lịch sử các vé đã phát hành.
- **Đặt chỗ (Booking):**
  - Người dùng có thể đặt trước chỗ đỗ qua Web/App (giả định từ API `check_booking`).
  - Hệ thống sinh mã đặt chỗ.
  - Khi xe đến, camera nhận diện biển số và tự động match với Booking ID.

### 3. Quản Lý Vị Trí (Slots Status)

- Hiển thị trực quan trạng thái 4 vị trí đỗ (`A01` - `A04`).
- Cập nhật trạng thái: `Trống` (Xanh) hoặc `Có xe` (Đỏ).
- Đồng bộ dữ liệu từ Python Client qua API `update_slot`.

---

## 💳 Hệ Thống Thanh Toán (Payment)

### 1. Cổng Thanh Toán (`payment.php`)

- Giao diện cho khách hàng thanh toán phí gửi xe.
- Nhập biển số hoặc mã vé để tra cứu số tiền.
- Hiển thị QR Code chuyển khoản.

### 2. Tích Hợp SePay (`api/webhook_sepay.php`)

- Hệ thống tích hợp cổng thanh toán SePay để nhận thông báo chuyển khoản tự động.
- **Quy trình:**
  1.  Khách chuyển khoản theo QR.
  2.  Ngân hàng báo cho SePay.
  3.  SePay gọi Webhook về `webhook_sepay.php`.
  4.  Hệ thống cập nhật trạng thái vé thành `PAID`.
  5.  Python Client tại cổng ra nhận biết vé đã trả -> Mở cổng.

---

## 🔧 Cấu Trúc Backend (PHP)

### Thư mục `api/`

Chứa các logic xử lý chính:

- `gateway.php`: Cổng giao tiếp chung, điều hướng request dựa trên tham số `action`.
- `csdl.php`: Kết nối Database MySQL.
- `ticket_functions.php`: Các hàm xử lý vé (tạo, lấy, cập nhật).
- `check_payment.php`: Kiểm tra trạng thái thanh toán.
- `upload_image.php`: Xử lý upload ảnh từ client.

### Cron Jobs (`api/cron_job.php`)

- Chạy định kỳ để dọn dẹp dữ liệu rác.
- Xử lý các vé quá hạn hoặc booking không đến.

---

## 📱 Mobile / User App (Tiềm năng)

- Hệ thống Web được thiết kế Responsive để hoạt động tốt trên điện thoại.
- Người dùng có thể truy cập để:
  - Đặt chỗ trước.
  - Xem trạng thái bãi đỗ còn trống không.
  - Thanh toán online trước khi ra xe.
