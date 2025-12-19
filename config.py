import os
import time
import threading
import cv2
import logging
from datetime import datetime, timezone, timedelta

os.environ['TZ'] = 'Asia/Ho_Chi_Minh'
if hasattr(time, 'tzset'):
    time.tzset()
VN_TZ = timezone(timedelta(hours=7))

logger = logging.getLogger('XParking')

class SystemConfig:
    """Cấu hình hệ thống và state"""
    def __init__(self):
        self.config = {
            'site_url': 'https://xparking.elementfx.com',
            'mqtt_broker': '10.105.11.197',
            'mqtt_port': 1883,
            # ESP32-CAM IP addresses (ENTRY - xe vào)
            'esp32_cam_gate1': '10.105.11.205',
            'esp32_cam_gate2': '10.105.11.202',
            # Webcam indices (EXIT - xe ra)
            'camera_out_gate1': 0,
            'camera_out_gate2': 1,
            'price_per_minute': 1000,
            'min_price': 5000,
            'gas_threshold': 4000,
            'email_recipient': 'athanhphuc7102005@gmail.com',
            'email_sender': '',
            'email_password': ''
        }
        
        # State
        self.is_running = False
        self.emergency_mode = False
        self.waiting_for_qr = False
        self.waiting_for_plate = False
        self.waiting_for_qr_gate2 = False
        self.waiting_for_plate_gate2 = False
        self.qr_scan_result = None
        self.qr_scan_result_gate2 = None
        self.plate_frame_result_gate1 = None
        self.plate_frame_result_gate2 = None
        self.current_exit_plate = None
        self.current_exit_plate_gate2 = None
        self.pending_entry = None
        self.pending_entry_gate2 = None
        self.pending_exit = None
        self.pending_exit_gate2 = None
        
        # Frame buffers - ESP32-CAM (xe VÀO - entry)
        self.latest_frame_in_gate1 = None
        self.latest_frame_in_gate2 = None
        self.frame_lock_in_gate1 = threading.Lock()
        self.frame_lock_in_gate2 = threading.Lock()
        
        # Frame buffers - Webcam (xe RA - exit)
        self.latest_frame_out_gate1 = None
        self.latest_frame_out_gate2 = None
        self.frame_lock_out_gate1 = threading.Lock()
        self.frame_lock_out_gate2 = threading.Lock()
        
        # Webcam objects (for EXIT)
        self.vid_out_gate1 = None
        self.vid_out_gate2 = None
        self.camera_thread_gate1 = None
        self.camera_thread_gate2 = None
        
        # Slot status - now managed by DB (settings table)
        # Total: 50, Occupied: tracking in DB

    def get_vn_time(self, fmt='%Y-%m-%d %H:%M:%S'):
        return datetime.now(VN_TZ).strftime(fmt)
    
    def get_vn_iso(self):
        return datetime.now(VN_TZ).isoformat()

class CameraManager:
    """Quản lý camera - không có UI"""
    def __init__(self, config):
        self.config = config

    def init_cameras(self):
        """Khởi tạo 2 webcam cho xe RA (EXIT)"""
        try:
            self.release_cameras()
            
            self.config.vid_out_gate1 = cv2.VideoCapture(self.config.config['camera_out_gate1'])
            self.config.vid_out_gate2 = cv2.VideoCapture(self.config.config['camera_out_gate2'])
            
            for cam in [self.config.vid_out_gate1, self.config.vid_out_gate2]:
                if cam and cam.isOpened():
                    cam.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                    cam.set(cv2.CAP_PROP_FPS, 30)
                    cam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
                    cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            
            self.config.is_running = True
            
            # Start camera threads for EXIT webcams
            self.config.camera_thread_gate1 = threading.Thread(
                target=self._camera_reader, args=(self.config.vid_out_gate1, 1), daemon=True)
            self.config.camera_thread_gate2 = threading.Thread(
                target=self._camera_reader, args=(self.config.vid_out_gate2, 2), daemon=True)
            
            self.config.camera_thread_gate1.start()
            self.config.camera_thread_gate2.start()
            
            g1 = "OK" if self.config.vid_out_gate1 and self.config.vid_out_gate1.isOpened() else "FAIL"
            g2 = "OK" if self.config.vid_out_gate2 and self.config.vid_out_gate2.isOpened() else "FAIL"
            logger.info(f"Webcam (EXIT): Gate1={g1}, Gate2={g2}")
            return True
            
        except Exception as e:
            logger.error(f"Camera init error: {e}")
            return False

    def _camera_reader(self, camera, gate):
        """Thread đọc frames từ webcam (EXIT)"""
        while self.config.is_running and camera and camera.isOpened():
            try:
                ret, frame = camera.read()
                if ret:
                    if gate == 1:
                        with self.config.frame_lock_out_gate1:
                            self.config.latest_frame_out_gate1 = frame.copy()
                    else:
                        with self.config.frame_lock_out_gate2:
                            self.config.latest_frame_out_gate2 = frame.copy()
                time.sleep(0.03)
            except Exception as e:
                logger.error(f"Camera {gate} error: {e}")
                time.sleep(1)

    def capture_frame(self, camera_type='out', gate=1):
        """Lấy frame từ camera
        - camera_type='out': Webcam (xe RA)
        - camera_type='in': ESP32-CAM buffer (xe VÀO)
        """
        try:
            if camera_type == 'out':
                # Webcam for EXIT - đọc từ thread buffer
                if gate == 1:
                    for _ in range(10):
                        with self.config.frame_lock_out_gate1:
                            if self.config.latest_frame_out_gate1 is not None:
                                return self.config.latest_frame_out_gate1.copy()
                        time.sleep(0.1)
                    return None
                else:
                    for _ in range(10):
                        with self.config.frame_lock_out_gate2:
                            if self.config.latest_frame_out_gate2 is not None:
                                return self.config.latest_frame_out_gate2.copy()
                        time.sleep(0.1)
                    return None
            else:
                # ESP32-CAM for ENTRY - đọc từ HTTP callback buffer
                if gate == 1:
                    with self.config.frame_lock_in_gate1:
                        if self.config.latest_frame_in_gate1 is not None:
                            return self.config.latest_frame_in_gate1.copy()
                    return None
                else:
                    with self.config.frame_lock_in_gate2:
                        if self.config.latest_frame_in_gate2 is not None:
                            return self.config.latest_frame_in_gate2.copy()
                    return None
        except Exception as e:
            logger.error(f"Camera {camera_type} gate{gate} error: {e}")
            return None
    
    def set_esp32_frame(self, frame, gate=1):
        """Lưu frame từ ESP32-CAM (cho ENTRY)"""
        try:
            if gate == 1:
                with self.config.frame_lock_in_gate1:
                    self.config.latest_frame_in_gate1 = frame.copy()
            else:
                with self.config.frame_lock_in_gate2:
                    self.config.latest_frame_in_gate2 = frame.copy()
        except:
            pass
    
    def release_cameras(self):
        """Giải phóng webcams"""
        self.config.is_running = False
        time.sleep(0.1)  # Cho thread dừng
        
        try:
            if self.config.vid_out_gate1 and self.config.vid_out_gate1.isOpened():
                self.config.vid_out_gate1.release()
        except:
            pass
        
        try:
            if self.config.vid_out_gate2 and self.config.vid_out_gate2.isOpened():
                self.config.vid_out_gate2.release()
        except:
            pass
        
        self.config.vid_out_gate1 = None
        self.config.vid_out_gate2 = None
        logger.info("Webcams Released")