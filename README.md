# 🚗 XParking Auto - Hệ Thống Quản Lý Bãi Đỗ Xe Thông Minh

XParking Auto là một giải pháp toàn diện để quản lý bãi đỗ xe tự động, sử dụng công nghệ nhận diện biển số xe (AI/Computer Vision), tích hợp IoT (Arduino/MQTT) và quản lý dữ liệu tập trung. Hệ thống được thiết kế để tối ưu hóa quy trình gửi giữ xe, tăng cường an ninh và tiết kiệm nhân lực.

![Status](https://img.shields.io/badge/Status-Active-brightgreen)
![Python](https://img.shields.io/badge/Python-3.8%2B-blue)
![License](https://img.shields.io/badge/License-MIT-yellow)

## ✨ Tính Năng Nổi Bật

- **📷 Nhận diện biển số xe tự động (ALPR):**
  - Sử dụng công nghệ AI (Torch, OpenCV) để nhận diện biển số xe vào/ra với độ chính xác cao.
  - Tự động chụp ảnh và lưu trữ hình ảnh xe lúc vào và ra.
- **🖥️ Giao diện quản lý trực quan (GUI):**
  - Hiển thị camera trực tiếp (Luồng vào/Luồng ra).
  - Trạng thái các vị trí đỗ xe (Trống/Có xe).
  - Thống kê số lượng xe trong bãi.
  - Cảnh báo trạng thái hệ thống (Kết nối Camera, MQTT, AI, Cảm biến).
- **🤖 Tích hợp IoT & Phần cứng:**
  - Giao tiếp với Arduino qua giao thức MQTT để điều khiển barrier (cổng chắn).
  - Tích hợp cảm biến khí gas/cháy nổ để cảnh báo an toàn.
  - Hệ thống đèn báo trạng thái chỗ đỗ.
- **💰 Quản lý thanh toán & Vé:**
  - Tự động tính toán phí gửi xe dựa trên thời gian gửi (Cấu hình giá theo phút/giờ).
  - Hỗ trợ vé lượt và vé tháng.
  - Gửi email thông báo (ví dụ: vé tháng sắp hết hạn).
- **☁️ Đồng bộ dữ liệu Cloud:**
  - Kết nối với hệ thống Web Server (PHP/MySQL) để lưu trữ lịch sử ra vào, doanh thu.
  - API tích hợp để quản lý từ xa.

## 🛠️ Yêu Cầu Hệ Thống

### Phần cứng

- Máy tính chạy Windows/Linux/MacOS (Khuyên dùng Windows cho GUI Tkinter ổn định).
- Camera IP hoặc Webcam (Tối thiểu 2 camera: 1 vào, 1 ra).
- Mạch Arduino (ESP8266/ESP32) cho điều khiển cổng (Tuỳ chọn).
- Kết nối mạng Internet.

### Phần mềm

- Python 3.8 trở lên.
- Các thư viện phụ thuộc (xem `requirements.txt`).

## ⚙️ Cài Đặt

1.  **Clone dự án:**

    ```bash
    git clone https://github.com/username/Xparking_Auto.git
    cd Xparking_Auto
    ```

2.  **Tạo môi trường ảo (Khuyên dùng):**

    ```bash
    python -m venv venv
    # Windows
    venv\Scripts\activate
    # Linux/Mac
    source venv/bin/activate
    ```

3.  **Cài đặt các thư viện cần thiết:**
    ```bash
    pip install -r requirements.txt
    ```
    _Lưu ý: Việc cài đặt `torch` và `opencv` có thể mất một chút thời gian._

## 🔧 Cấu Hình

Mở file `config.py` để chỉnh sửa các thông số phù hợp với hệ thống của bạn:

- **MQTT:** Cấu hình địa chỉ IP Broker, Port (Mặc định: `192.168.1.127`).
- **Camera:** Chỉnh `camera_in_gate1`, `camera_in_gate2` thành ID của camera (0, 1) hoặc URL luồng RTSP.
- **Giá vé:**
  - `price_per_minute`: Giá tiền mỗi phút.
  - `min_price`: Giá tối thiểu.
- **API:** Đường dẫn đến Server quản lý (`site_url`).
- **Email:** Cấu hình tài khoản gửi mail thông báo.

## 🚀 Hướng Dẫn Sử Dụng

1.  **Khởi động hệ thống:**
    Chạy file `main.py` để mở giao diện quản lý:

    ```bash
    python main.py
    ```

2.  **Trên giao diện:**
    - Hệ thống sẽ tự động kết nối Camera và MQTT.
    - Khi có xe vào vùng nhận diện, hệ thống sẽ đọc biển số và mở barrier (nếu được cấu hình tự động) hoặc chờ xác nhận.
    - Thông tin xe, thời gian vào/ra sẽ được hiển thị và lưu vào cơ sở dữ liệu.

## 📚 Tài Liệu Chi Tiết

- [🚗 Quy trình Xe Vào (Entry Flow)](docs/FLOW_VAO.md)
- [🚀 Quy trình Xe Ra (Exit Flow)](docs/FLOW_RA.md)
- [📡 Tài liệu API Giao Tiếp](docs/API_GIAO_TIEP.md)
- [🌐 Hệ thống Web & Booking](docs/WEB_BOOKING.md)

## 📂 Cấu Trúc Dự Án

- `main.py`: File khởi chạy chính của chương trình.
- `config.py`: Chứa các cấu hình hệ thống và lớp quản lý giao diện (GUIManager).
- `functions.py`: Chứa logic xử lý chính (Business Logic).
- `QUET_BSX.py`: Module xử lý nhận diện biển số xe (License Plate Recognition).
- `ticket_system.py`: Quản lý vé và tính tiền.
- `mqtt_gate1.py`, `mqtt_gate2.py`: Script giả lập hoặc xử lý giao tiếp MQTT riêng lẻ.
- `requirements.txt`: Danh sách thư viện Python cần thiết.
- `arduino/`: Mã nguồn cho vi điều khiển Arduino (C++).
- `hosting-web/`: Mã nguồn Website quản lý (PHP/HTML/CSS).

## 📊 Hiệu Năng

Hệ thống đã được kiểm thử thực tế với quy mô **100 xe/ngày**:

- **Peak time:** Xử lý tốt 15-20 xe/giờ.
- **Response time:** API phản hồi nhanh (100-300ms).
- **Tài nguyên:** Sử dụng CPU và RAM ở mức thấp, hoạt động ổn định trên các máy cấu hình tầm trung.

## 🤝 Đóng Góp

Mọi đóng góp để cải thiện dự án đều được hoan nghênh. Vui lòng tạo Pull Request hoặc mở Issue nếu bạn gặp lỗi.

## 📜 Giấy Phép

Dự án này được phân phối dưới giấy phép MIT. Xem file LICENSE để biết thêm chi tiết.
