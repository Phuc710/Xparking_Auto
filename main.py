import sys
import logging
import threading
import time
import signal
import cv2

from config import SystemConfig, CameraManager
from email_handler import EmailHandler
from functions import SystemFunctions
from QUET_BSX import OptimizedLPR
from db_api import DatabaseAPI
import http_server

import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s | %(message)s',
    datefmt='%H:%M:%S',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger('XParking')
logging.getLogger('urllib3').setLevel(logging.WARNING)

class XParkingSystem:
    def __init__(self):
        logger.info("=== XPARKING BÃI XE THÔNG MINH ===")
        
        self.config = SystemConfig()
        self.camera = CameraManager(self.config)
        self.lpr = OptimizedLPR()
        self.db = DatabaseAPI(self.config.config)
        self.email = EmailHandler(self.config)
        
        self.functions = SystemFunctions(
            self.config, self.camera, self.lpr, self.db, self.email
        )
        
        self._shutdown_event = threading.Event()
        self._ai_loaded = threading.Event()
        self._stream_thread = None
        
        # Status tracking
        self.status = {
            'api': False,
            'mqtt_g1': False,
            'mqtt_g2': False,
            'cam_g1': False,
            'cam_g2': False,
            'ai': False
        }

    def run(self):
        """Chạy hệ thống - console only"""
        try:
            # API - đã kết nối trong __init__
            self.status['api'] = self.db.connected
            
            # HTTP Server (health check only)
            logger.info("Starting HTTP server...")
            http_server.start_server(port=5000)
            
            # Log ESP32-CAM IPs
            esp32_g1 = self.config.config['esp32_cam_gate1']
            esp32_g2 = self.config.config['esp32_cam_gate2']
            logger.info(f"ESP32-CAM (ENTRY): Gate1={esp32_g1}, Gate2={esp32_g2}")
            
            # MQTT
            logger.info("Connecting MQTT...")
            mqtt_ok = self.functions.init_mqtt()
            self.status['mqtt_g1'] = mqtt_ok
            self.status['mqtt_g2'] = mqtt_ok
            
            # Webcams for EXIT
            logger.info("Init webcams (EXIT)...")
            self.camera.init_cameras()
            self.status['cam_g1'] = self.config.vid_out_gate1 is not None and self.config.vid_out_gate1.isOpened()
            self.status['cam_g2'] = self.config.vid_out_gate2 is not None and self.config.vid_out_gate2.isOpened()
            
            # AI Model (background)
            logger.info("Loading AI model...")
            threading.Thread(target=self._load_ai, daemon=True).start()
            
            # Cho AI load xong (max 30s)
            self._ai_loaded.wait(timeout=30)
            
            # Hiển thị trạng thái
            self._print_status()
            
            # Start webcam stream UI for security guard
            logger.info("Starting webcam stream UI...")
            self._stream_thread = threading.Thread(target=self._stream_webcams, daemon=True)
            self._stream_thread.start()
            
            # Main loop
            while not self._shutdown_event.is_set():
                time.sleep(1)
                
        except KeyboardInterrupt:
            logger.info("Bị gián đoạn")
        finally:
            self.shutdown()

    def _load_ai(self):
        if self.lpr.load_models():
            logger.info("AI model loaded")
            self.status['ai'] = True
        else:
            logger.error("AI model failed")
            self.status['ai'] = False
        self._ai_loaded.set()
    
    def _print_status(self):
        """In trạng thái hệ thống"""
        logger.info("=" * 50)
        logger.info("         TRANG THAI HE THONG")
        logger.info("=" * 50)
        logger.info(f"  API Server    : {'OK' if self.status['api'] else 'FAIL'}")
        logger.info(f"  MQTT Gate1    : {'OK' if self.status['mqtt_g1'] else 'FAIL'}")
        logger.info(f"  MQTT Gate2    : {'OK' if self.status['mqtt_g2'] else 'FAIL'}")
        logger.info(f"  Webcam EXIT G1: {'OK' if self.status['cam_g1'] else 'FAIL'}")
        logger.info(f"  Webcam EXIT G2: {'OK' if self.status['cam_g2'] else 'FAIL'}")
        logger.info(f"  ESP32-CAM G1  : {self.config.config['esp32_cam_gate1']}")
        logger.info(f"  ESP32-CAM G2  : {self.config.config['esp32_cam_gate2']}")
        logger.info(f"  AI Model      : {'OK' if self.status['ai'] else 'FAIL'}")
        logger.info("=" * 50)
        
        all_ok = all(self.status.values())
        if all_ok:
            logger.info("  >>> HỆ THỐNG SẴN SÀNG <<<")
        else:
            logger.warning("  >>> CÓ LỖI - KIỂM TRA LẠI <<<")
        logger.info("=" * 50)
        logger.info("Nhấn Ctrl+C để dừng hệ thống.")

    def _stream_webcams(self):
        """Stream liên tục 2 webcam EXIT cho bảo vệ xem"""
        try:
            logger.info("[Stream] Webcam UI started")
            
            # Tạo cửa sổ và đặt vị trí
            cv2.namedWindow("Gate 1 - EXIT Camera", cv2.WINDOW_NORMAL)
            cv2.namedWindow("Gate 2 - EXIT Camera", cv2.WINDOW_NORMAL)
            cv2.moveWindow("Gate 1 - EXIT Camera", 50, 50)
            cv2.moveWindow("Gate 2 - EXIT Camera", 700, 50)
            
            while not self._shutdown_event.is_set():
                # Gate 1
                if self.status['cam_g1']:
                    with self.config.frame_lock_out_gate1:
                        if self.config.latest_frame_out_gate1 is not None:
                            frame1 = self.config.latest_frame_out_gate1.copy()
                            cv2.putText(frame1, "Gate 1 - EXIT", (10, 30), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
                            cv2.imshow("Gate 1 - EXIT Camera", frame1)
                
                # Gate 2
                if self.status['cam_g2']:
                    with self.config.frame_lock_out_gate2:
                        if self.config.latest_frame_out_gate2 is not None:
                            frame2 = self.config.latest_frame_out_gate2.copy()
                            cv2.putText(frame2, "Gate 2 - EXIT", (10, 30), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
                            cv2.imshow("Gate 2 - EXIT Camera", frame2)
                
                # Kiểm tra phím 'q' để tắt (không bắt buộc)
                key = cv2.waitKey(30) & 0xFF
                if key == ord('q'):
                    logger.info("[Stream] User pressed 'q' to close stream")
                    break
                    
        except Exception as e:
            logger.error(f"[Stream] Error: {e}")
        finally:
            cv2.destroyAllWindows()
            logger.info("[Stream] Webcam UI stopped")
    
    def shutdown(self):
        if self._shutdown_event.is_set():
            return  # Da shutdown roi
        logger.info("Shutting down...")
        self._shutdown_event.set()
        self._ai_loaded.set()  # Unblock neu dang cho AI
        
        # Đóng stream UI
        try:
            cv2.destroyAllWindows()
        except:
            pass
        
        # Giai phong camera truoc
        try:
            self.camera.release_cameras()
        except:
            pass
        
        # Tat MQTT
        try:
            if hasattr(self, 'functions'):
                self.functions.shutdown()
        except:
            pass
        
        logger.info("System stopped")

if __name__ == "__main__":
    system = XParkingSystem()
    signal.signal(signal.SIGINT, lambda s, f: system.shutdown())
    system.run()