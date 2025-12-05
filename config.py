import os
import sys
import time
import json
import threading
from datetime import datetime, timezone, timedelta
import paho.mqtt.client as mqtt
import tkinter as tk
from tkinter import ttk
import cv2
import logging
from PIL import Image, ImageTk

# Cấu hình timezone VN
os.environ['TZ'] = 'Asia/Ho_Chi_Minh'
time.tzset() if hasattr(time, 'tzset') else None
VN_TZ = timezone(timedelta(hours=7))

logger = logging.getLogger('XParking')
class SystemConfig:
    """Chứa các hằng số cấu hình và các trạng thái hệ thống"""
    def __init__(self):
        self.config = {
            # API (gọi qua PHP gateway, PHP kết nối MySQL)
            'site_url': 'https://xparking.elementfx.com',
            # MQTT
            'mqtt_broker': '192.168.1.127',
            'mqtt_port': 1883,
            # Camera
            'camera_in_gate1': 0,
            'camera_in_gate2': 1, 
            # Parking
            'price_per_minute': 1000,
            'min_price': 5000,
            'gas_threshold': 4000,
            # Email
            'email_recipient': 'athanhphuc7102005@gmail.com',
            'email_sender': '',
            'email_password': ''
        }
        
        self.vehicle_processing = False
        self.emergency_mode = False
        self.gas_alert_sent = False
        self.is_running = False
        self.waiting_for_qr = False
        
        self.current_entry_data = {}
        self.current_exit_plate = None
        self.pending_entry = None  # Lưu entry chờ commit khi xe vào slot
        self.qr_scan_result = None
        
        self.latest_frame_in = None
        self.latest_frame_out = None
        self.frame_lock_in = threading.Lock()
        self.frame_lock_out = threading.Lock()
        
        self.root = None
        self.status_labels = {}
        self.slot_indicators = {}
        self.cam_in_label = None
        self.cam_out_label = None
        self.plate_in_label = None
        self.plate_out_label = None
        self.emergency_label = None
        self.stats_label = None
        self.time_label = None
        self.vid_in = None
        self.vid_out = None
        self.camera_thread_in = None
        self.camera_thread_out = None

    def get_vn_time(self, format_str='%Y-%m-%d %H:%M:%S'):
        return datetime.now(VN_TZ).strftime(format_str)
    
    def get_vn_iso(self):
        """Trả về ISO format với timezone cho database"""
        return datetime.now(VN_TZ).isoformat()

class GUIManager:
    def __init__(self, system_config):
        self.config = system_config

    def init_gui(self, main_system):
        self.config.root = tk.Tk()
        self.config.root.title("QUẢN LÝ BÃI ĐỖ XE THÔNG MINH")
        self.config.root.geometry("1200x700")
        self.config.root.configure(bg='#1a1a2e')
        
        # Style configuration
        style = ttk.Style()
        style.theme_use('clam')
        style.configure('TFrame', background='#1a1a2e')
        style.configure('TLabel', background='#1a1a2e', foreground='white')
        
        # Header
        header_frame = tk.Frame(self.config.root, bg='#16213e', height=80)
        header_frame.pack(fill='x', pady=(0, 10))
        header_frame.pack_propagate(False)
        
        header_label = tk.Label(header_frame, text="XPARKING", 
                                font=('Arial', 24, 'bold'), bg='#16213e', fg='#00ff41')
        header_label.pack(pady=20)
        
        # Main container
        main_container = tk.Frame(self.config.root, bg='#1a1a2e')
        main_container.pack(fill='both', expand=True, padx=20)
        
        # Left panel - Camera feeds
        left_panel = tk.Frame(main_container, bg='#0f3460', width=600)
        left_panel.pack(side='left', fill='both', expand=True, padx=(0, 10))
        
        self._create_camera_section(left_panel)
        
        # Right panel - Status and controls
        right_panel = tk.Frame(main_container, bg='#0f3460', width=500)
        right_panel.pack(side='right', fill='both', expand=True)
        
        self._create_status_section(right_panel)
        self._create_slots_section(right_panel)
        self._create_stats_section(right_panel)

        # Tạo status indicators
        self.create_status_indicator(self.config.status_frame, "MQTT", "mqtt_status")
        self.create_status_indicator(self.config.status_frame, "Camera Vào", "cam_in_status")
        self.create_status_indicator(self.config.status_frame, "Camera Ra", "cam_out_status")
        self.create_status_indicator(self.config.status_frame, "AI Model", "ai_status")
        self.create_status_indicator(self.config.status_frame, "Cảm biến Gas", "gas_status")
        
        self.config.root.after(1000, self.update_time)

        # Gán main_system cho root để truy cập update trong payment
        main_system.root = self.config.root 
        
        logger.info("Đã khởi tạo giao diện GUI")
        return self.config.root
    
    def _create_camera_section(self, parent):
        """Tạo section camera"""
        # Camera IN
        cam_in_frame = tk.LabelFrame(parent, text="CAMERA VÀO", 
                                     font=('Arial', 12, 'bold'),
                                     bg='#0f3460', fg='white')
        cam_in_frame.pack(padx=10, pady=10, fill='x')
        
        self.config.cam_in_label = tk.Label(cam_in_frame, bg='#1a1a2e', width=400, height=150)
        self.config.cam_in_label.pack(padx=5, pady=5)
        
        self.config.plate_in_label = tk.Label(cam_in_frame, text="Biển số: ---",
                                       font=('Arial', 11), bg='#0f3460', fg='cyan')
        self.config.plate_in_label.pack(pady=5)
        
        # Camera OUT
        cam_out_frame = tk.LabelFrame(parent, text="CAMERA RA", 
                                      font=('Arial', 12, 'bold'),
                                      bg='#0f3460', fg='white')
        cam_out_frame.pack(padx=10, pady=10, fill='x')
        
        self.config.cam_out_label = tk.Label(cam_out_frame, bg='#1a1a2e', width=400, height=150)
        self.config.cam_out_label.pack(padx=5, pady=5)
        
        self.config.plate_out_label = tk.Label(cam_out_frame, text="Biển số: ---",
                                        font=('Arial', 11), bg='#0f3460', fg='cyan')
        self.config.plate_out_label.pack(pady=5)
    
    def _create_status_section(self, parent):
        """Tạo section trạng thái hệ thống"""
        self.config.status_frame = tk.LabelFrame(parent, text="TRẠNG THÁI HỆ THỐNG",
                                         font=('Arial', 12, 'bold'),
                                         bg='#0f3460', fg='white')
        self.config.status_frame.pack(padx=10, pady=10, fill='x')
        
        # Emergency indicator
        self.config.emergency_label = tk.Label(self.config.status_frame, text="HOẠT ĐỘNG BÌNH THƯỜNG",
                                        font=('Arial', 14, 'bold'),
                                        bg='#0f3460', fg='#00ff41')
        self.config.emergency_label.pack(pady=10)
    
    def _create_slots_section(self, parent):
        """Tạo section slots"""
        slots_frame = tk.LabelFrame(parent, text="TRẠNG THÁI CHỖ ĐỖ",
                                    font=('Arial', 12, 'bold'),
                                    bg='#0f3460', fg='white')
        slots_frame.pack(padx=10, pady=10, fill='x')
        
        slots_grid = tk.Frame(slots_frame, bg='#0f3460')
        slots_grid.pack(pady=10)
        
        for i in range(4):
            slot_id = f"A0{i+1}"
            slot_frame = tk.Frame(slots_grid, bg='#1a1a2e', relief='raised', bd=2)
            slot_frame.grid(row=i//2, column=i%2, padx=10, pady=10)
            
            slot_label = tk.Label(slot_frame, text=slot_id,
                                  font=('Arial', 16, 'bold'),
                                  bg='#00ff41', fg='black',
                                  width=8, height=3)
            slot_label.pack()
            self.config.slot_indicators[slot_id] = slot_label
    
    def _create_stats_section(self, parent):
        """Tạo section thống kê"""
        stats_frame = tk.LabelFrame(parent, text="THỐNG KÊ",
                                    font=('Arial', 12, 'bold'),
                                    bg='#0f3460', fg='white')
        stats_frame.pack(padx=10, pady=10, fill='x')
        
        self.config.stats_label = tk.Label(stats_frame, text="Xe trong bãi: 0/4",
                                    font=('Arial', 11), bg='#0f3460', fg='cyan')
        self.config.stats_label.pack(pady=5)
        
        self.config.time_label = tk.Label(stats_frame, text="",
                                   font=('Arial', 11), bg='#0f3460', fg='yellow')
        self.config.time_label.pack(pady=5)
    
    def create_status_indicator(self, parent_frame, label_text, status_key):
        """Tạo indicator trạng thái"""
        frame = tk.Frame(parent_frame, bg='#0f3460')
        frame.pack(fill='x', padx=10, pady=5)
        
        status_label = tk.Label(frame, text=label_text, font=('Arial', 11), 
                               bg='#0f3460', fg='white')
        status_label.pack(side='left')
        
        indicator = tk.Label(frame, text="●", font=('Arial', 16, 'bold'), 
                            fg='red', bg='#0f3460')
        indicator.pack(side='right')
        
        self.config.status_labels[status_key] = indicator

    def init_cameras(self, update_status_func):
        """Khởi tạo cameras"""
        try:
            self.release_cameras()
            
            # Khởi tạo cameras
            self.config.vid_in = cv2.VideoCapture(self.config.config['camera_in'])
            self.config.vid_out = cv2.VideoCapture(self.config.config['camera_out'])
            
            # Cấu hình cameras
            for cam in [self.config.vid_in, self.config.vid_out]:
                if cam and cam.isOpened():
                    cam.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                    cam.set(cv2.CAP_PROP_FPS, 30)
                    cam.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
                    cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            
            self.config.is_running = True
            
            # Start camera threads
            self.config.camera_thread_in = threading.Thread(
                target=self._camera_reader_thread, 
                args=(self.config.vid_in, 'in', update_status_func), daemon=True
            )
            self.config.camera_thread_out = threading.Thread(
                target=self._camera_reader_thread, 
                args=(self.config.vid_out, 'out', update_status_func), daemon=True
            )
            
            self.config.camera_thread_in.start()
            self.config.camera_thread_out.start()
            
            # Start GUI updates
            self.config.root.after(30, self.update_camera_feeds)
            
            logger.info("Đã khởi tạo cameras")
            return True
            
        except Exception as e:
            logger.error(f"❌ Lỗi khởi tạo camera: {e}")
            return False

    def _camera_reader_thread(self, camera, camera_type, update_status_func):
        """Thread đọc frames từ camera"""
        while self.config.is_running and camera and camera.isOpened():
            try:
                ret, frame = camera.read()
                if ret:
                    # Resize cho hiển thị
                    frame = cv2.resize(frame, (400, 300))
                    
                    if camera_type == 'in':
                        with self.config.frame_lock_in:
                            self.config.latest_frame_in = frame.copy()
                        update_status_func('cam_in_status', True)
                    else:
                        with self.config.frame_lock_out:
                            self.config.latest_frame_out = frame.copy()
                        update_status_func('cam_out_status', True)
                else:
                    # Camera error
                    if camera_type == 'in':
                        update_status_func('cam_in_status', False)
                    else:
                        update_status_func('cam_out_status', False)
                        
                time.sleep(0.03)  # ~30 FPS
                        
            except Exception as e:
                logger.error(f"Lỗi thread camera ({camera_type}): {e}")
                time.sleep(1)

    def update_camera_feeds(self):
        """Cập nhật camera feeds trên GUI"""
        try:
            # Update camera IN
            if self.config.latest_frame_in is not None and self.config.cam_in_label:
                with self.config.frame_lock_in:
                    frame = self.config.latest_frame_in.copy()
                frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                image = Image.fromarray(frame_rgb)
                photo = ImageTk.PhotoImage(image)
                self.config.cam_in_label.configure(image=photo)
                self.config.cam_in_label.image = photo
            
            # Update camera OUT
            if self.config.latest_frame_out is not None and self.config.cam_out_label:
                with self.config.frame_lock_out:
                    frame = self.config.latest_frame_out.copy()
                frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                image = Image.fromarray(frame_rgb)
                photo = ImageTk.PhotoImage(image)
                self.config.cam_out_label.configure(image=photo)
                self.config.cam_out_label.image = photo
                
        except Exception as e:
            logger.error(f"❌ Lỗi cập nhật camera GUI: {e}")
        
        # Schedule next update
        if self.config.is_running and self.config.root:
            self.config.root.after(30, self.update_camera_feeds)
    
    def capture_frame(self, camera_type='in'):
        """Capture frame từ camera"""
        try:
            if camera_type == 'in':
                with self.config.frame_lock_in:
                    return self.config.latest_frame_in.copy() if self.config.latest_frame_in is not None else None
            else:
                with self.config.frame_lock_out:
                    return self.config.latest_frame_out.copy() if self.config.latest_frame_out is not None else None
        except:
            return None
    
    def release_cameras(self):
        """Giải phóng cameras"""
        self.config.is_running = False
        if self.config.vid_in:
            self.config.vid_in.release()
        if self.config.vid_out:
            self.config.vid_out.release()
        self.config.vid_in = None
        self.config.vid_out = None
        logger.info("Đã giải phóng cameras")

    def update_slot_status(self, slot_id, status):
        """Cập nhật trạng thái slot"""
        logger.info(f"Slot {slot_id}: {status}")
        
        if slot_id in self.config.slot_indicators and self.config.root:
            if status.lower() == "occupied":
                self.config.slot_indicators[slot_id].config(bg='red', fg='white')
            elif status.lower() in ["free", "empty"]:
                self.config.slot_indicators[slot_id].config(bg='#00ff41', fg='black')
        
        # Cập nhật thống kê
        occupied = sum(1 for slot in self.config.slot_indicators.values() 
                      if slot.cget('bg') == 'red')
        if self.config.stats_label:
            self.config.stats_label.config(text=f"Xe trong bãi: {occupied}/4")

    def update_status(self, key, is_active):
        """Cập nhật trạng thái indicator"""
        if self.config.root and key in self.config.status_labels:
            self.config.status_labels[key].config(fg='#00ff41' if is_active else 'red')

    def update_time(self):
        """Cập nhật hiển thị thời gian"""
        if self.config.time_label:
            current_time = datetime.now().strftime("%H:%M:%S")
            self.config.time_label.config(text=f"Thời gian: {current_time}")
            
        if self.config.root:
            self.config.root.after(1000, self.update_time)

    def update_plate_display(self, camera_type, license_plate):
        """Cập nhật hiển thị biển số"""
        if self.config.root:
            try:
                label = getattr(self.config, f'plate_{camera_type}_label')
                self.config.root.after(0, lambda: label.config(text=f"Biển số: {license_plate}"))
            except Exception as e:
                logger.warning(f"Lỗi cập nhật GUI: {e}")

    def update_emergency_status(self):
        """Cập nhật hiển thị trạng thái khẩn cấp"""
        if self.config.root and self.config.emergency_label:
            if self.config.emergency_mode:
                self.config.emergency_label.config(text="⚠️ PHÁT HIỆN CHÁY ⚠️", fg='red')
            else:
                self.config.emergency_label.config(text="HOẠT ĐỘNG BÌNH THƯỜNG", fg='#00ff41')