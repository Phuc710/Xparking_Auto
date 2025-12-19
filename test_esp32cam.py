import tkinter as tk
from tkinter import messagebox
import threading
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import cv2
import numpy as np
import os
from datetime import datetime
import time

# IP ESP32-CAM (Static IP)
ESP32_IP = "192.168.1.205"
SAVE_FOLDER = "test_esp32cam"

lpr = None
lpr_ready = False

# Connection pooling session
session = None

def create_session():
    """Create optimized requests session with connection pooling and retries"""
    global session
    session = requests.Session()
    
    # Connection pooling
    adapter = HTTPAdapter(
        pool_connections=10,
        pool_maxsize=10,
        max_retries=Retry(
            total=2,
            backoff_factor=0.1,
            status_forcelist=[500, 502, 503, 504],
            allowed_methods=["GET"]
        ),
        pool_block=False
    )
    
    session.mount('http://', adapter)
    session.mount('https://', adapter)
    
    # Optimized headers
    session.headers.update({
        'Connection': 'keep-alive',
        'Accept': 'image/jpeg',
        'User-Agent': 'ESP32CAM-Python-Client/2.0'
    })
    
    return session

def init_lpr():
    """Load YOLOv5 LPR models in background"""
    global lpr, lpr_ready
    try:
        print("Loading YOLOv5 LPR models...")
        from QUET_BSX import OptimizedLPR
        lpr = OptimizedLPR()
        lpr.load_models()
        lpr_ready = True
        print("✓ LPR models loaded!")
    except Exception as e:
        print(f"✗ LPR load error: {e}")
        lpr_ready = False

def recognize_plate(frame):
    """Detect and recognize license plate with multi-attempt logic"""
    global lpr, lpr_ready
    
    if not lpr_ready or not lpr:
        return None, 0
    
    try:
        start_time = time.time()
        result = lpr.detect_and_read_plate(frame)
        process_time = (time.time() - start_time) * 1000
        
        if result['success'] and result['plates']:
            plate_info = result['plates'][0]
            plate_text = plate_info['text'].upper().replace('-', '').replace(' ', '')
            confidence = plate_info.get('confidence', 0)
            
            if len(plate_text) >= 4:
                print(f"✓ BSX: {plate_text} | Conf: {confidence:.2f} | Time: {process_time:.0f}ms")
                return plate_text, confidence
            else:
                print(f"✗ Invalid plate: {plate_text} (too short)")
                return None, 0
        else:
            print(f"✗ No plate detected ({process_time:.0f}ms)")
            return None, 0
            
    except Exception as e:
        print(f"✗ LPR error: {e}")
        return None, 0

class CameraApp:
    def __init__(self, root):
        self.root = root
        root.title("ESP32-CAM + LPR Test")
        root.geometry("550x550")
        root.configure(bg='#2c3e50')
        
        # Title
        tk.Label(
            root,
            text="ESP32-CAM License Plate",
            font=('Arial', 18, 'bold'),
            bg='#2c3e50',
            fg='white'
        ).pack(pady=15)
        
        # IP Input Frame
        ip_frame = tk.Frame(root, bg='#34495e')
        ip_frame.pack(pady=10, padx=20, fill='x')
        
        tk.Label(
            ip_frame,
            text="IP:",
            font=('Arial', 11),
            bg='#34495e',
            fg='white'
        ).pack(side='left', padx=10)
        
        self.ip_entry = tk.Entry(ip_frame, font=('Arial', 11), width=16)
        self.ip_entry.insert(0, ESP32_IP)
        self.ip_entry.pack(side='left', padx=5)
        
        # Check IP Button
        tk.Button(
            ip_frame,
            text="Kiểm tra",
            font=('Arial', 10, 'bold'),
            bg='#27ae60',
            fg='white',
            command=self.check_ip,
            width=8
        ).pack(side='left', padx=5)
        
        # IP Status
        self.ip_status = tk.Label(
            root,
            text="Chưa kiểm tra",
            font=('Arial', 10),
            bg='#2c3e50',
            fg='#95a5a6'
        )
        self.ip_status.pack(pady=5)
        
        # LPR Status
        self.lpr_status = tk.Label(
            root,
            text="Loading LPR...",
            font=('Arial', 10),
            bg='#2c3e50',
            fg='#f39c12'
        )
        self.lpr_status.pack(pady=3)
        
        # Capture Button
        self.btn = tk.Button(
            root,
            text="CHỤP + NHẬN DIỆN BSX",
            font=('Arial', 15, 'bold'),
            bg='#3498db',
            fg='white',
            command=self.capture,
            width=20,
            height=2
        )
        self.btn.pack(pady=20)
        
        # BSX Result (BIG)
        self.bsx_label = tk.Label(
            root,
            text="Chưa có",
            font=('Arial', 42, 'bold'),
            bg='#2c3e50',
            fg='#ecf0f1'
        )
        self.bsx_label.pack(pady=15)
        
        # Status
        self.status = tk.Label(
            root,
            text="Sẵn sàng",
            font=('Arial', 11),
            bg='#2c3e50',
            fg='#95a5a6'
        )
        self.status.pack(pady=8)
        
        # Detail
        self.detail = tk.Label(
            root,
            text="",
            font=('Arial', 10),
            bg='#2c3e50',
            fg='#7f8c8d'
        )
        self.detail.pack(pady=5)
        
        # Update LPR status periodically
        self.update_lpr_status()
    
    def update_lpr_status(self):
        if lpr_ready:
            self.lpr_status.config(text="✓ LPR Ready", fg='#2ecc71')
        else:
            self.lpr_status.config(text="⏳ Loading LPR...", fg='#f39c12')
        self.root.after(500, self.update_lpr_status)
    
    def check_ip(self):
        """Check ESP32-CAM connection status with detailed info"""
        ip = self.ip_entry.get().strip()
        
        def do_check():
            try:
                self.ip_status.config(text="Đang kiểm tra...", fg='#f39c12')
                
                global session
                if session is None:
                    create_session()
                
                start_time = time.time()
                response = session.get(f"http://{ip}/status", timeout=3)
                response_time = (time.time() - start_time) * 1000
                
                if response.status_code == 200:
                    data = response.json()
                    rssi = data.get('rssi', 0)
                    captures = data.get('captures', 0)
                    avg_time = data.get('avg_time', 0)
                    uptime = data.get('uptime', 0)
                    
                    self.ip_status.config(
                        text=f"✓ Connected | Signal: {rssi}dBm | Ping: {response_time:.0f}ms | Captures: {captures} | Avg: {avg_time}ms | Up: {uptime}s",
                        fg='#2ecc71'
                    )
                    print(f"✓ ESP32-CAM: {data}")
                else:
                    self.ip_status.config(text=f"✗ HTTP {response.status_code}", fg='#e74c3c')
                    
            except requests.ConnectionError:
                self.ip_status.config(text="✗ Không kết nối được - Kiểm tra IP/WiFi", fg='#e74c3c')
                print(f"✗ Cannot connect to {ip}")
            except requests.Timeout:
                self.ip_status.config(text="✗ Timeout - ESP32 không phản hồi", fg='#e74c3c')
            except Exception as e:
                self.ip_status.config(text=f"✗ Lỗi: {str(e)[:40]}", fg='#e74c3c')
                print(f"✗ Error: {e}")
        
        threading.Thread(target=do_check, daemon=True).start()
    
    def capture(self):
        if not lpr_ready:
            messagebox.showwarning("Cảnh báo", "LPR chưa sẵn sàng!\nVui lòng đợi...")
            return
        
        self.btn.config(state='disabled', bg='#95a5a6')
        self.status.config(text="Đang chụp...", fg='#f39c12')
        self.bsx_label.config(text="Đang xử lý...", fg='#f39c12')
        self.detail.config(text="")
        
        threading.Thread(target=self.do_capture, daemon=True).start()
    
    def do_capture(self):
        total_start = time.time()
        
        try:
            ip = self.ip_entry.get().strip()
            url = f"http://{ip}/capture"
            
            print(f"\n{'='*60}")
            print(f"Requesting: {url}")
            
            global session
            if session is None:
                create_session()
            
            # Capture image with timeout
            capture_start = time.time()
            response = session.get(url, timeout=8, stream=False)
            capture_time = (time.time() - capture_start) * 1000
            
            if response.status_code == 200:
                # Fast decode
                decode_start = time.time()
                nparr = np.frombuffer(response.content, np.uint8)
                img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
                decode_time = (time.time() - decode_start) * 1000
                
                if img is not None:
                    size = len(response.content) / 1024
                    print(f"✓ Image: {size:.1f}KB ({img.shape[1]}x{img.shape[0]}px) | Capture: {capture_time:.0f}ms | Decode: {decode_time:.0f}ms")
                    
                    # LPR Recognition
                    lpr_start = time.time()
                    print("Running LPR...")
                    plate, confidence = recognize_plate(img)
                    lpr_time = (time.time() - lpr_start) * 1000
                    
                    # Save image
                    os.makedirs(SAVE_FOLDER, exist_ok=True)
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    
                    if plate:
                        filename = f"{plate}_{timestamp}.jpg"
                    else:
                        filename = f"unknown_{timestamp}.jpg"
                    
                    filepath = os.path.join(SAVE_FOLDER, filename)
                    cv2.imwrite(filepath, img, [cv2.IMWRITE_JPEG_QUALITY, 95])
                    
                    total_time = (time.time() - total_start) * 1000
                    print(f"✓ Saved: {filename}")
                    print(f"✓ Total time: {total_time:.0f}ms (Capture: {capture_time:.0f}ms + Decode: {decode_time:.0f}ms + LPR: {lpr_time:.0f}ms)")
                    print('='*60 + '\n')
                    
                    if plate:
                        # SUCCESS!
                        self.bsx_label.config(text=plate, fg='#2ecc71')
                        self.status.config(text="✓ Nhận diện thành công!", fg='#2ecc71')
                        self.detail.config(
                            text=f"Conf: {confidence:.2f} | Time: {total_time:.0f}ms | File: {filename}",
                            fg='#7f8c8d'
                        )
                        
                        # Show image preview (smaller for faster display)
                        display_img = cv2.resize(img, (640, 480)) if img.shape[1] > 640 else img
                        cv2.imshow("Captured - " + plate, display_img)
                        cv2.waitKey(2500)
                        cv2.destroyAllWindows()
                    else:
                        # No plate detected
                        self.bsx_label.config(text="Không nhận diện được", fg='#e74c3c')
                        self.status.config(text="✗ Không tìm thấy BSX", fg='#e74c3c')
                        self.detail.config(text=f"Time: {total_time:.0f}ms | Saved: {filename}", fg='#7f8c8d')
                else:
                    raise Exception("Không decode được ảnh")
            else:
                raise Exception(f"HTTP {response.status_code}")
                
        except requests.ConnectionError:
            print("✗ Connection failed - ESP32 offline?")
            self.bsx_label.config(text="Lỗi kết nối", fg='#e74c3c')
            self.status.config(text="❌ ESP32-CAM không phản hồi!", fg='#e74c3c')
            self.detail.config(text="Kiểm tra: IP, WiFi, hoặc ESP32 đã bật chưa")
            
        except requests.Timeout:
            print("✗ Timeout - ESP32 quá chậm")
            self.bsx_label.config(text="Timeout", fg='#e74c3c')
            self.status.config(text="⏱️ ESP32 không phản hồi kịp!", fg='#e74c3c')
            self.detail.config(text="Thử lại hoặc kiểm tra tín hiệu WiFi")
            
        except Exception as e:
            print(f"✗ Error: {e}")
            self.bsx_label.config(text="Lỗi", fg='#e74c3c')
            self.status.config(text="❌ Có lỗi xảy ra!", fg='#e74c3c')
            self.detail.config(text=str(e)[:80])
        
        finally:
            self.btn.config(state='normal', bg='#3498db')

if __name__ == "__main__":
    print("\n" + "="*60)
    print("ESP32-CAM + YOLOv5 LPR Test - OPTIMIZED v2.0")
    print("="*60 + "\n")
    
    # Create HTTP session with connection pooling
    print("Creating optimized HTTP session...")
    create_session()
    print("✓ Session ready with connection pooling\n")
    
    # Load LPR in background
    print("Loading LPR models in background...")
    threading.Thread(target=init_lpr, daemon=True).start()
    
    # Start GUI
    root = tk.Tk()
    app = CameraApp(root)
    root.mainloop()