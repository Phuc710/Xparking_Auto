import tkinter as tk
from tkinter import messagebox
import threading
import requests
import cv2
import numpy as np

# IP ESP32-CAM
ESP32_IP = "192.168.1.136"

class CameraApp:
    def __init__(self, root):
        self.root = root
        root.title("ESP32-CAM Test")
        root.geometry("500x400")
        root.configure(bg='#2c3e50')
        
        # Title
        tk.Label(
            root,
            text="ESP32-CAM Capture",
            font=('Arial', 20, 'bold'),
            bg='#2c3e50',
            fg='white'
        ).pack(pady=20)
        
        # IP Input
        frame = tk.Frame(root, bg='#34495e')
        frame.pack(pady=10, padx=20, fill='x')
        
        tk.Label(
            frame,
            text="IP:",
            font=('Arial', 12),
            bg='#34495e',
            fg='white'
        ).pack(side='left', padx=10)
        
        self.ip_entry = tk.Entry(frame, font=('Arial', 12), width=20)
        self.ip_entry.insert(0, ESP32_IP)
        self.ip_entry.pack(side='left', padx=5)
        
        # Capture Button
        self.btn = tk.Button(
            root,
            text="CHỤP ẢNH",
            font=('Arial', 16, 'bold'),
            bg='#3498db',
            fg='white',
            command=self.capture,
            width=15,
            height=2
        )
        self.btn.pack(pady=30)
        
        # Status
        self.status = tk.Label(
            root,
            text="Sẵn sàng",
            font=('Arial', 12),
            bg='#2c3e50',
            fg='#95a5a6'
        )
        self.status.pack(pady=10)
        
        # Result
        self.result = tk.Label(
            root,
            text="",
            font=('Arial', 14, 'bold'),
            bg='#2c3e50',
            fg='#2ecc71'
        )
        self.result.pack(pady=10)
    
    def capture(self):
        self.btn.config(state='disabled', bg='#95a5a6')
        self.status.config(text="Đang chụp...", fg='#f39c12')
        self.result.config(text="")
        
        threading.Thread(target=self.do_capture, daemon=True).start()
    
    def do_capture(self):
        try:
            ip = self.ip_entry.get().strip()
            url = f"http://{ip}/capture"
            
            print(f"Requesting: {url}")
            response = requests.get(url, timeout=10)
            
            if response.status_code == 200:
                # Convert to image
                nparr = np.frombuffer(response.content, np.uint8)
                img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
                
                if img is not None:
                    size = len(response.content) / 1024
                    print(f"Received: {size:.1f} KB")
                    print(f"Image size: {img.shape}")
                    
                    # Save image
                    cv2.imwrite("captured.jpg", img)
                    
                    # Show image
                    cv2.imshow("Captured Image", img)
                    cv2.waitKey(3000)
                    cv2.destroyAllWindows()
                    
                    self.status.config(text="Thành công!", fg='#2ecc71')
                    self.result.config(text=f"✓ {size:.1f} KB - {img.shape[1]}x{img.shape[0]}")
                else:
                    raise Exception("Không decode được ảnh")
            else:
                raise Exception(f"HTTP {response.status_code}")
                
        except requests.ConnectionError:
            print("Connection failed")
            self.status.config(text="Không kết nối được!", fg='#e74c3c')
            self.result.config(text="✗ Kiểm tra IP hoặc WiFi", fg='#e74c3c')
            
        except Exception as e:
            print(f"Error: {e}")
            self.status.config(text="Lỗi!", fg='#e74c3c')
            self.result.config(text=f"✗ {str(e)}", fg='#e74c3c')
        
        finally:
            self.btn.config(state='normal', bg='#3498db')

if __name__ == "__main__":
    print("ESP32-CAM Test App")
    print("=" * 40)
    
    root = tk.Tk()
    app = CameraApp(root)
    root.mainloop()