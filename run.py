import sys
import signal
import logging
import os

# Suppress OpenCV warnings BEFORE importing anything with OpenCV
os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'
os.environ['OPENCV_VIDEOIO_DEBUG'] = '0'

from main import XParkingSystem

def setup_environment():
    """Thiet lap moi truong chay"""
    # Cau hinh timezone
    os.environ['TZ'] = 'Asia/Ho_Chi_Minh'
    
    # Dam bao cac thu muc can thiet ton tai
    os.makedirs('logs', exist_ok=True)
    os.makedirs('temp', exist_ok=True)
    os.makedirs('img_in', exist_ok=True)      # Ảnh xe vào
    os.makedirs('img_out', exist_ok=True)     # Ảnh xe ra
    os.makedirs('tickets_out', exist_ok=True) # Ảnh vé QR scan thành công

def signal_handler(sig, frame):
    """Xu ly tin hieu tat he thong"""
    logger = logging.getLogger('XParking')
    logger.info(f"Signal {sig} received - shutting down gracefully")
    
    # Tat he thong neu da khoi tao
    if 'system' in globals() and system:
        system.shutdown()
    
    print("\nHe thong XParking da dung.")
    sys.exit(0)

def check_dependencies():
    """Kiem tra cac dependency can thiet"""
    required_modules = [
        'cv2', 'tkinter', 'PIL', 'paho.mqtt.client', 
        'requests', 'threading', 'json'
    ]
    
    missing_modules = []
    
    for module in required_modules:
        try:
            __import__(module)
        except ImportError:
            missing_modules.append(module)
    
    if missing_modules:
        print("LOI: Thieu cac module bat buoc:")
        for module in missing_modules:
            print(f"  - {module}")
        print("\nVui long cai dat cac module bi thieu va thu lai.")
        sys.exit(1)

def print_system_info():
    """In thong tin he thong"""
    print("Thong tin he thong:")
    print(f"  Phien ban Python: {sys.version.split()[0]}")
    print(f"  Nen tang: {sys.platform}")
    
    # Kiem tra camera
    try:
        import cv2
        print(f"  Phien ban OpenCV: {cv2.__version__}")
        
        # Test camera access
        cap = cv2.VideoCapture(0)
        if cap.isOpened():
            print("  Camera 0: San sang")
            cap.release()
        else:
            print("  Camera 0: Khong san sang")
            
    except Exception as e:
        print(f"  Kiem tra Camera: Loi - {e}")
    
    print()

def main():
    """Ham chinh khoi chay he thong"""
    global system
    system = None
    
    try:
        # Thiet lap moi truong
        setup_environment()
        
        # Kiem tra dependencies
        print("Dang kiem tra cac module can thiet...")
        check_dependencies()
        print("✓ Tat ca cac module da duoc cai dat day du")
        
        # In thong tin he thong
        print_system_info()
        
        # Dang ky signal handlers
        signal.signal(signal.SIGINT, signal_handler)  # Ctrl+C
        signal.signal(signal.SIGTERM, signal_handler) # Terminate
        
        # Tao va chay he thong
        system = XParkingSystem()
        system.run()
        
    except KeyboardInterrupt:
        print("\nDa nhan lenh ngat tu ban phim")
        signal_handler(signal.SIGINT, None)
        
    except Exception as e:
        logger = logging.getLogger('XParking')
        logger.error(f"Loi khoi dong he thong: {e}")
        print(f"LOI: Khoi dong he thong that bai - {e}")
        
        # In stack trace cho debug
        import traceback
        traceback.print_exc()
        
        sys.exit(1)
        
    finally:
        # Don dep
        if system:
            system.shutdown()
        print("Don dep he thong hoan tat")

if __name__ == "__main__":
    main()