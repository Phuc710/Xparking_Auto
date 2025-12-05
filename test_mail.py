# test_email.py
import logging
from email_handler import EmailHandler

# Class config giả lập để truyền vào EmailHandler
class DummyConfig:
    def __init__(self):
        self.config = {
            "email_sender": "",          # Gmail gửi đi
            "email_password": "",          # App password (16 ký tự, viết liền)
            "email_recipient": "athanhphuc7102005@gmail.com"  # Mail nhận
        }

def main():
    logging.basicConfig(level=logging.INFO)

    # Tạo email handler
    email_handler = EmailHandler(DummyConfig())

    while True:
        print("\nChọn hành động:")
        print("1. Gửi email cảnh báo (send_alert_email)")
        print("2. Gửi email phục hồi (send_recovery_email)")
        print("0. Thoát")

        choice = input("Nhập lựa chọn: ").strip()

        if choice == "1":
            gas_level = "500ppm"
            location = "Tầng hầm B1"
            success = email_handler.send_alert_email(gas_level, location)
            print("Kết quả:", "Thành công ✅" if success else "Thất bại ❌")

        elif choice == "2":
            success = email_handler.send_recovery_email()
            print("Kết quả:", "Thành công ✅" if success else "Thất bại ❌")

        elif choice == "0":
            break
        else:
            print("Lựa chọn không hợp lệ!")

if __name__ == "__main__":
    main()
