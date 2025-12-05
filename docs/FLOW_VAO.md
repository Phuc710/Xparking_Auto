# 🚗 QUY TRÌNH XE VÀO (ENTRY FLOW)

Tài liệu này mô tả chi tiết quy trình xử lý khi một xe tiến vào bãi đỗ (Gate 1).

## 📋 Sơ Đồ Tổng Quan

1.  **Phát hiện xe** (Trigger)
2.  **Chụp ảnh & Nhận diện** (Camera + AI)
3.  **Kiểm tra Booking/Slot** (Logic)
4.  **Tạo/Lấy Vé** (Ticket System)
5.  **Mở Barrier & Hướng dẫn** (LCD + Barrier)
6.  **Xác nhận vào vị trí đỗ** (Sensor + MQTT)
7.  **Lưu trữ & Đồng bộ** (Database + Cloud)

---

## 🛠️ Chi Tiết Các Bước

### 1. Khởi Tạo (Trigger)

- Hệ thống nhận tín hiệu từ cảm biến hoặc camera phát hiện chuyển động tại cổng vào.
- Hiển thị thông báo trên màn hình LCD: `NHAN DIEN` / `VUI LONG CHO`.

### 2. Thu Thập Dữ Liệu (Data Capture)

- **Camera:** Chụp ảnh từ Camera Input (`camera_in`).
- **Retry:** Hệ thống thử chụp tối đa 3 lần nếu ảnh bị lỗi hoặc mờ.
- **LPR (License Plate Recognition):**
  - Ảnh được gửi qua module AI (`OptimizedLPR`).
  - Mô hình nhận diện vị trí biển số -> Cắt ảnh -> OCR đọc ký tự.
  - Trả về: Chuỗi biển số (Ví dụ: `59T112345`) và độ tin cậy (Confidence).
  - _Lưu ý:_ Các ký tự đặc biệt (-, space) sẽ được loại bỏ.

### 3. Xử Lý Logic (Business Logic)

#### A. Kiểm Tra Booking (Đặt chỗ trước)

- Hệ thống gọi `TicketManager.get_booking_ticket(plate)`.
- Nếu tìm thấy booking hợp lệ cho biển số này:
  - Lấy thông tin vé đã đặt (`ticket_code`, `booking_id`).
  - Trạng thái: **Ưu tiên vào**.

#### B. Xe Vãng Lai (Walk-in)

- Nếu không có booking:
  - Kiểm tra số chỗ trống (`db.get_available_slots()`).
  - **Nếu Hết chỗ:**
    - LCD: `BAI XE DAY` / `VUI LONG QUAY LAI`.
    - Kết thúc quy trình.
  - **Nếu Còn chỗ:**
    - Tạo vé vãng lai mới (`TicketManager.create_walk_in_ticket`).
    - Sinh mã vé (`ticket_code`) và QR Code.

### 4. Điều Khiển Phần Cứng

- **In Vé (Chỉ xe vãng lai):** Máy in nhiệt in phiếu chứa QR Code và thông tin giờ vào.
- **LCD:** Hiển thị `MOI XE VAO` và Biển số/Mã vé.
- **Barrier:** Gửi lệnh MQTT `open` tới ESP32 để mở cổng.

### 5. Pending Entry (Chờ xác nhận đỗ)

- Hệ thống lưu thông tin vào bộ nhớ tạm (`pending_entry`):
  - Biển số, Mã vé, Frame ảnh.
  - Danh sách các slot trống được phép đỗ.
- Gửi lệnh `MONITOR_SLOTS` qua MQTT tới các cảm biến vị trí đỗ.

### 6. Xác Nhận Đỗ Xe (Finalize)

- **Sensor Trigger:** Khi cảm biến tại slot phát hiện có xe vào (`CAR_ENTERED_SLOT`).
- **Commit:**
  - Cập nhật trạng thái Slot trên GUI -> Đỏ (Occupied).
  - **Upload Ảnh:** Gửi ảnh chụp lúc vào lên Server (API `upload_image`).
  - **Database Checkin:** Gọi API `checkin` để lưu phiên gửi xe chính thức.
  - **Update Booking:** Nếu là xe booking, cập nhật trạng thái thành `in_parking`.
- **Hoàn tất:** LCD hiển thị lời chào và quay về trạng thái chờ.

---

## ⚠️ Xử Lý Lỗi (Exception Handling)

- **Lỗi Camera:** LCD báo `LOI CAMERA`.
- **Không nhận diện được biển số:** LCD báo `KHONG NHAN DIEN`. Yêu cầu tài xế điều chỉnh xe.
- **Xe không vào slot (Timeout):** Nếu sau một khoảng thời gian xe không vào slot, hệ thống hủy `pending_entry` để tránh treo slot ảo.
