"""
Test Camera - Stream 2 webcam
Nhấn Ctrl+C để dừng
"""
import os
import cv2
import signal
import sys

os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'

# Camera config từ config.py
CAMERA_GATE1 = 0
CAMERA_GATE2 = 1

running = True

def signal_handler(sig, frame):
    global running
    print("\n[INFO] Đang dừng...")
    running = False

def main():
    global running
    
    signal.signal(signal.SIGINT, signal_handler)
    
    print("="*50)
    print("  TEST CAMERA - XPARKING")
    print("="*50)
    print(f"  Camera Gate1: {CAMERA_GATE1}")
    print(f"  Camera Gate2: {CAMERA_GATE2}")
    print("  Nhấn Ctrl+C để dừng")
    print("="*50)
    
    # Mở camera
    cap1 = cv2.VideoCapture(CAMERA_GATE1)
    cap2 = cv2.VideoCapture(CAMERA_GATE2)
    
    # Cấu hình camera
    for cap in [cap1, cap2]:
        if cap and cap.isOpened():
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            cap.set(cv2.CAP_PROP_FPS, 30)
            cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
    
    g1_ok = cap1.isOpened()
    g2_ok = cap2.isOpened()
    print(f"[INFO] Gate1: {'OK' if g1_ok else 'FAIL'}")
    print(f"[INFO] Gate2: {'OK' if g2_ok else 'FAIL'}")
    
    if not g1_ok and not g2_ok:
        print("[ERROR] Không có camera nào hoạt động!")
        return
    
    print("[INFO] Đang stream... Nhấn 'q' hoặc Ctrl+C để dừng")
    
    while running:
        frame1 = None
        frame2 = None
        
        if g1_ok:
            ret1, frame1 = cap1.read()
            if ret1:
                cv2.imshow("Gate 1 - Camera IN", frame1)
        
        if g2_ok:
            ret2, frame2 = cap2.read()
            if ret2:
                cv2.imshow("Gate 2 - Camera IN", frame2)
        
        # Nhấn 'q' để thoát
        key = cv2.waitKey(1) & 0xFF
        if key == ord('q'):
            print("\n[INFO] Nhấn 'q' - Đang dừng...")
            break
    
    # Cleanup
    print("[INFO] Giải phóng camera...")
    cap1.release()
    cap2.release()
    cv2.destroyAllWindows()
    print("[INFO] Đã dừng!")

if __name__ == "__main__":
    main()
