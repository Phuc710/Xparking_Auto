"""
Stream Webcam - Hiển thị liên tục 2 webcam EXIT (xe ra)
Nhấn 'q' hoặc Ctrl+C để dừng
"""
import os
import cv2
import signal
import sys

os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'

# Camera config từ config.py
CAMERA_OUT_GATE1 = 0  # camera_out_gate1
CAMERA_OUT_GATE2 = 1  # camera_out_gate2

running = True

def signal_handler(sig, frame):
    global running
    print("\n[INFO] Đang dừng...")
    running = False

def main():
    global running
    
    signal.signal(signal.SIGINT, signal_handler)
    
    print("="*60)
    print("  STREAM WEBCAM - XPARKING (EXIT CAMERAS)")
    print("="*60)
    print(f"  Camera OUT Gate1: {CAMERA_OUT_GATE1}")
    print(f"  Camera OUT Gate2: {CAMERA_OUT_GATE2}")
    print("  Nhấn 'q' hoặc Ctrl+C để dừng")
    print("="*60)
    
    # Mở camera OUT (xe ra)
    cap_out1 = cv2.VideoCapture(CAMERA_OUT_GATE1)
    cap_out2 = cv2.VideoCapture(CAMERA_OUT_GATE2)
    
    # Cấu hình camera
    for cap in [cap_out1, cap_out2]:
        if cap and cap.isOpened():
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            cap.set(cv2.CAP_PROP_FPS, 30)
            cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
    
    out1_ok = cap_out1.isOpened()
    out2_ok = cap_out2.isOpened()
    print(f"[INFO] OUT Gate1: {'OK' if out1_ok else 'FAIL'}")
    print(f"[INFO] OUT Gate2: {'OK' if out2_ok else 'FAIL'}")
    
    if not out1_ok and not out2_ok:
        print("[ERROR] Không có camera nào hoạt động!")
        return
    
    print("[INFO] Đang stream... Nhấn 'q' hoặc Ctrl+C để dừng")
    print("[INFO] 2 cửa sổ: 'Gate 1 - Camera OUT' và 'Gate 2 - Camera OUT'")
    
    # Đặt vị trí cửa sổ
    if out1_ok:
        cv2.namedWindow("Gate 1 - Camera OUT", cv2.WINDOW_NORMAL)
        cv2.moveWindow("Gate 1 - Camera OUT", 50, 50)
    
    if out2_ok:
        cv2.namedWindow("Gate 2 - Camera OUT", cv2.WINDOW_NORMAL)
        cv2.moveWindow("Gate 2 - Camera OUT", 700, 50)
    
    while running:
        # Camera OUT Gate 1
        if out1_ok:
            ret1, frame1 = cap_out1.read()
            if ret1:
                # Thêm text overlay
                cv2.putText(frame1, "Gate 1 - EXIT", (10, 30), 
                           cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
                cv2.imshow("Gate 1 - Camera OUT", frame1)
        
        # Camera OUT Gate 2
        if out2_ok:
            ret2, frame2 = cap_out2.read()
            if ret2:
                # Thêm text overlay
                cv2.putText(frame2, "Gate 2 - EXIT", (10, 30), 
                           cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
                cv2.imshow("Gate 2 - Camera OUT", frame2)
        
        # Nhấn 'q' để thoát
        key = cv2.waitKey(1) & 0xFF
        if key == ord('q'):
            print("\n[INFO] Nhấn 'q' - Đang dừng...")
            break
    
    # Cleanup
    print("[INFO] Giải phóng camera...")
    cap_out1.release()
    cap_out2.release()
    cv2.destroyAllWindows()
    print("[INFO] Đã dừng!")

if __name__ == "__main__":
    main()
