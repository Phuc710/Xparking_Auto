# 🚗 QUY TRÌNH XE RA (EXIT FLOW)

Tài liệu này mô tả quy trình xử lý khi xe ra khỏi bãi, bao gồm nhận diện, tính phí và xác thực thanh toán.

## 📋 Sơ Đồ Tổng Quan (Song Song Hóa)

Hệ thống sử dụng kỹ thuật xử lý song song (Parallel Processing) để tối ưu thời gian chờ:

1.  **Nhận diện** (Trigger + Camera + AI)
2.  **Xử lý Song Song:**
    - _Task A:_ Gọi API lấy thông tin xe & phí.
    - _Task B:_ Quét QR vé (nếu cần xác thực kép).
3.  **Kiểm tra & Tính phí**
4.  **Thanh toán (Checkout)**
5.  **Mở Barrier**

---

## 🛠️ Chi Tiết Các Bước

### 1. Khởi Tạo & Nhận Diện

- **Trigger:** Xe đi vào vùng cảm biến cổng ra (Gate 1 hoặc Gate 2).
- **LCD:** `NHAN DIEN BSX` / `VUI LONG CHO...`
- **Camera:** Chụp ảnh từ ESP32-CAM (Camera Out).
- **AI:** Nhận diện biển số xe (LPR).
- **Lưu ảnh:** Lưu ảnh xe ra vào thư mục local (Async).

### 2. Xử Lý Song Song (Parallel Tasking)

Hệ thống thực hiện đồng thời 2 tác vụ để giảm độ trễ:

#### Task A: Lấy Dữ Liệu Xe (API/Cache)

- **Cache Check:** Kiểm tra trong bộ nhớ đệm cục bộ (`ExitCacheManager`). Nếu mới truy vấn gần đây (< 5 phút), dùng lại kết quả để tiết kiệm API call.
- **API Call:** Nếu không có Cache, gọi `db.verify_exit_full(plate)`.
  - API trả về toàn bộ thông tin: Có tồn tại không? Mã vé là gì? Đã thanh toán chưa? Phí bao nhiêu? Có quá giờ không?

#### Task B: Quét Vé QR (Verification)

- Hệ thống kích hoạt chế độ chờ quét QR (`waiting_for_qr`).
- Camera liên tục chụp và giải mã QR Code.
- **Mục đích:** Đối chiếu mã vé trong QR với mã vé trên hệ thống (Security check).

### 3. Kiểm Tra Logic (Validation)

Sau khi có kết quả từ cả 2 task (hoặc timeout):

1.  **Khớp Mã Vé:** So sánh `Ticket Code` từ API và từ QR (nếu quét được).
2.  **Kiểm Tra Thanh Toán:**
    - **Status = `PENDING`:** Chưa thanh toán. LCD hiển thị số tiền. Yêu cầu khách quét QR thanh toán.
    - **Status = `USED`:** Vé đã dùng rồi (Cảnh báo gian lận).
    - **Status = `PAID`:** Đã thanh toán hợp lệ.
3.  **Kiểm Tra Quá Giờ (Overstay):**
    - Nếu xe đỗ quá giờ quy định sau khi thanh toán (ví dụ: > 15 phút).
    - Yêu cầu thanh toán phụ phí (`overstay_fee`).

### 4. Checkout & Mở Cổng

Nếu tất cả điều kiện hợp lệ:

- **Checkout (Async):**
  - Gọi API `db.checkout(ticket_code)` để đóng phiên gửi xe.
  - Xóa Cache liên quan đến xe này.
  - Upload ảnh xe ra lên Server.
- **Mở Barrier:** Gửi lệnh MQTT `open`.
- **LCD:** `TAM BIET` / `HEN GAP LAI`.

---

## ⚠️ Các Tình Huống Đặc Biệt

### A. Xe Không Có Trong Hệ Thống

- API trả về `found: False`.
- Lý do: Nhận diện sai biển số, hoặc xe vào chui không qua hệ thống.
- Xử lý: LCD báo `XE KHONG CO` / `TRONG HE THONG`. Bảo vệ cần can thiệp thủ công.

### B. Chưa Thanh Toán

- LCD hiển thị số tiền cần trả.
- Khách hàng quét mã QR thanh toán (MoMo/Bank).
- Hệ thống chờ Webhook hoặc Polling xác nhận thanh toán thành công mới mở cổng.

### C. Lỗi Mạng/API

- Không kết nối được Server.
- LCD báo `LOI KET NOI`.
- Nếu có Cache hợp lệ, có thể cho phép ra (tùy cấu hình Offline Mode - _hiện tại code yêu cầu online_).
