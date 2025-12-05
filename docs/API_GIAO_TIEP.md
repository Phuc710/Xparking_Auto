# 📡 TÀI LIỆU API GIAO TIẾP (PYTHON CLIENT ↔ PHP SERVER)

Tài liệu này mô tả các API endpoint được sử dụng để giao tiếp giữa ứng dụng Python tại bãi xe và Web Server PHP.

**Base URL:** `https://xparking.elementfx.com/api`

---

## 1. Gateway API (`gateway.php`)

Tất cả các yêu cầu dữ liệu đều đi qua cổng này với tham số `action`.

**Method:** `GET`

### 🎫 Nhóm Vé (Ticket)

#### `create_ticket`

Tạo vé mới cho xe vào (Walk-in).

- **Params:**
  - `action`: `create_ticket`
  - `license_plate`: Biển số xe
- **Response:**
  ```json
  {
    "success": true,
    "ticket": {
      "ticket_code": "VE12345678",
      "qr_url": "..."
    }
  }
  ```

#### `get_ticket`

Lấy thông tin vé.

- **Params:** `action=get_ticket`, `ticket_code`
- **Response:** Thông tin vé, giờ vào, trạng thái.

#### `verify_ticket`

Xác thực vé (thường dùng thủ công hoặc debug).

- **Params:** `action=verify_ticket`, `ticket_code`, `license_plate`

#### `use_ticket`

Đánh dấu vé đã sử dụng (thường gọi sau khi checkout).

- **Params:** `action=use_ticket`, `ticket_code`

---

### 📅 Nhóm Booking (Đặt chỗ)

#### `check_booking`

Kiểm tra xe có đặt chỗ trước không.

- **Params:** `action=check_booking`, `license_plate`
- **Response:**
  ```json
  {
    "has_booking": true,
    "booking_id": 123,
    "ticket_code": "BK..."
  }
  ```

#### `get_booking`

Lấy chi tiết booking của xe.

- **Params:** `action=get_booking`, `license_plate`

#### `update_booking`

Cập nhật trạng thái booking.

- **Params:** `action=update_booking`, `booking_id`, `status` (`in_parking`, `completed`, `cancelled`)

---

### 🚗 Nhóm Xe (Vehicle Operations)

#### `checkin`

Ghi nhận xe đã vào bãi thành công (Cam kết vào slot).

- **Params:**
  - `action`: `checkin`
  - `license_plate`: Biển số xe
  - `slot_id`: Vị trí đỗ (Ví dụ: `A01`)
  - `ticket_code`: Mã vé

#### `checkout`

Ghi nhận xe ra khỏi bãi.

- **Params:**
  - `action`: `checkout`
  - `ticket_code`: Mã vé
  - `license_plate`: Biển số xe (Optional check)

#### `verify_exit_full`

Lấy toàn bộ thông tin cần thiết để xử lý xe ra (Tối ưu hóa 1 lần gọi).

- **Params:** `action=verify_exit_full`, `license_plate`
- **Response:**
  ```json
  {
    "found": true,
    "ticket_code": "VE...",
    "status": "PENDING/PAID",
    "amount": 5000,
    "has_overstay": false,
    "allow_exit": true
  }
  ```

#### `get_vehicle_by_plate`

Tìm xe đang gửi trong bãi.

- **Params:** `action=get_vehicle_by_plate`, `license_plate`

---

### 🅿️ Nhóm Vị Trí Đỗ (Slots)

#### `get_slots`

Lấy danh sách tất cả slot.

- **Params:** `action=get_slots`

#### `update_slot`

Cập nhật trạng thái slot (thường dùng bởi Admin hoặc sensor sync).

- **Params:** `action=update_slot`, `slot_id`, `status` (`empty`, `occupied`)

---

## 2. Image Upload API (`upload_image.php`)

API chuyên dụng để upload ảnh xe/vé.

**Method:** `POST`
**Content-Type:** `application/json`

### `capture_and_upload`

- **Payload:**
  ```json
  {
      "type": "entry" | "exit" | "ticket",
      "ticket_code": "VE12345678",
      "image": "base64_string..."
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "path": "/uploads/entry/...",
      "size_kb": 45.2
    }
  }
  ```

---

## 🔒 Bảo Mật & Bypass

- **Anti-Bot:** Hệ thống cloudflare sử dụng AES Challenge (`toNumbers`). Python Client tự động giải mã cookie `__test` để bypass.
- **Session:** Sử dụng `requests.Session` để duy trì kết nối và cookie.
- **User-Agent:** Giả lập trình duyệt để tránh bị chặn.
