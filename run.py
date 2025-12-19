import os
import sys

os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'
os.environ['OPENCV_VIDEOIO_DEBUG'] = '0'
os.environ['TZ'] = 'Asia/Ho_Chi_Minh'

# Tạo thư mục cần thiết
for d in ['img_in', 'img_out', 'tickets_out']:
    os.makedirs(d, exist_ok=True)

from main import XParkingSystem

if __name__ == "__main__":
    system = XParkingSystem()
    system.run()