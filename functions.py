
import time
import json
import threading
import logging
import os
import cv2
import requests
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
import paho.mqtt.client as mqtt
from image_uploader import ImageUploader
from ticket_system import TicketManager, WalkInTicket, BookingTicket

# Suppress OpenCV warnings
os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'

logger = logging.getLogger('XParking')

# ============================================================

# ============================================================
class ExitCacheManager:
    """Cache rieng cho Gate 1 va Gate 2"""
    CACHE_FILE_GATE1 = os.path.join(os.path.dirname(__file__), 'exit_gate1_cache.json')
    CACHE_FILE_GATE2 = os.path.join(os.path.dirname(__file__), 'exit_gate2_cache.json')
    CACHE_TIMEOUT = 300  # 5 phut
    
    @classmethod
    def get(cls, plate, gate=1):
        """Lay cache data cho BSX"""
        cache_file = cls.CACHE_FILE_GATE1 if gate == 1 else cls.CACHE_FILE_GATE2
        try:
            if not os.path.exists(cache_file):
                return None
            
            with open(cache_file, 'r', encoding='utf-8') as f:
                cache = json.load(f)
            
            if cache.get('plate') == plate:
                cached_time = cache.get('timestamp', 0)
                if time.time() - cached_time < cls.CACHE_TIMEOUT:
                    logger.info(f"[GATE{gate}]  Cache HIT: {plate}")
                    return cache.get('api_data')
            
            return None
        except Exception as e:
            logger.warning(f"[GATE{gate}] Cache read error: {e}")
            return None
    
    @classmethod
    def set(cls, plate, api_data, gate=1):
        """Luu data API vao cache"""
        cache_file = cls.CACHE_FILE_GATE1 if gate == 1 else cls.CACHE_FILE_GATE2
        try:
            cache = {
                'plate': plate,
                'timestamp': time.time(),
                'api_data': api_data
            }
            with open(cache_file, 'w', encoding='utf-8') as f:
                json.dump(cache, f, ensure_ascii=False, indent=2)
            logger.info(f"[GATE{gate}]  Cache SAVED: {plate}")
        except Exception as e:
            logger.warning(f"[GATE{gate}] Cache write error: {e}")
    
    @classmethod
    def clear(cls, gate=1):
        """Xoa cache"""
        cache_file = cls.CACHE_FILE_GATE1 if gate == 1 else cls.CACHE_FILE_GATE2
        try:
            if os.path.exists(cache_file):
                with open(cache_file, 'w', encoding='utf-8') as f:
                    json.dump({}, f)
                logger.info(f"[GATE{gate}]  Cache CLEARED")
        except Exception as e:
            logger.warning(f"[GATE{gate}] Cache clear error: {e}")

class SystemFunctions:
    def __init__(self, config, camera, lpr, db_api, email_handler):
        self.config = config
        self.camera = camera
        self.lpr = lpr
        self.db = db_api
        self.email = email_handler
        
        from mqtt_gate1 import MQTTGate1
        from mqtt_gate2 import MQTTGate2
        self.mqtt_gate1 = MQTTGate1(config, self)
        self.mqtt_gate2 = MQTTGate2(config, self)
        
        self.executor = ThreadPoolExecutor(max_workers=8)
        
        self.gate1_entry_lock = threading.Lock()
        self.gate1_exit_lock = threading.Lock()
        self.gate2_entry_lock = threading.Lock()
        self.gate2_exit_lock = threading.Lock()
        
        self.img_uploader = ImageUploader(config.config['site_url'])
        self.ticket_manager = TicketManager(db_api)

    def init_mqtt(self):
        """Khởi tạo MQTT"""
        try:
            g1 = self.mqtt_gate1.connect()
            g2 = self.mqtt_gate2.connect()
            if g1 and g2:
                logger.info("MQTT: Gate1=OK, Gate2=OK")
                return True
            logger.error("MQTT connection failed")
            return False
        except Exception as e:
            logger.error(f"MQTT error: {e}")
            return False

    # === HELPER METHODS (delegate to MQTT handlers) ===
    def _display(self, station, line1, line2="", gate=1):
        """Hien thi message tren LCD"""
        if gate == 1:
            self.mqtt_gate1.display(station, line1, line2)
        else:
            self.mqtt_gate2.display(station, line1, line2)
    
    def _barrier(self, station, action, gate=1):
        """Dieu khien barrier"""
        if gate == 1:
            self.mqtt_gate1.barrier(station, action)
        else:
            self.mqtt_gate2.barrier(station, action)
    
    def _save_image(self, frame, plate, direction, gate):
        """Lưu ảnh xe local (legacy - không upload)"""
        try:
            dir_name = f"img_{direction}_gate{gate}"
            img_dir = os.path.join(os.path.dirname(__file__), dir_name)
            os.makedirs(img_dir, exist_ok=True)
            filename = f"{plate}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.jpg"
            cv2.imwrite(os.path.join(img_dir, filename), frame)
            return os.path.join(img_dir, filename)
        except:
            return None
    
    def _upload_image_async(self, frame, ticket_code, image_type, gate):
        """[ASYNC] Upload ảnh lên hosting - chạy background"""
        try:
            prefix = f"[G{gate}_{image_type.capitalize()}]"
            result = self.img_uploader.capture_and_upload(frame, ticket_code, image_type)
            if result.get('success'):
                logger.info(f"{prefix} Upload OK: {result.get('size_kb', 0):.1f}KB")
            else:
                logger.warning(f"{prefix} Upload fail: {result.get('error', 'Unknown')}")
            return result
        except Exception as e:
            logger.error(f"[G{gate}] Upload error: {e}")
            return {'success': False, 'error': str(e)}
    
    def _sync_entry_data(self, plate, ticket_code, frame, gate, is_booking=False):
        """[ASYNC] Gửi data xe VÀO lên hosting sau barrier close (với retry)"""
        MAX_RETRIES = 3
        prefix = f"[G{gate}_In]"
        
        for attempt in range(MAX_RETRIES):
            try:
                # Upload ảnh entry
                img_result = self.img_uploader.capture_and_upload(frame, ticket_code, 'entry')
                img_path = img_result.get('path', '') if img_result.get('success') else ''
                
                # Gọi API checkin (slot được tự động +1 bởi CAR_ENTERED event)
                checkin_result = self.db.checkin(plate, 'SLOT', ticket_code)
                
                if checkin_result and checkin_result.get('success'):
                    logger.info(f"{prefix} SYNC OK: {plate} | {ticket_code}")
                    return  # Success, exit
                else:
                    error = checkin_result.get('error', 'Unknown') if checkin_result else 'No response'
                    logger.warning(f"{prefix} SYNC API fail ({attempt+1}/{MAX_RETRIES}): {error}")
                    
            except Exception as e:
                logger.error(f"{prefix} Sync error ({attempt+1}/{MAX_RETRIES}): {e}")
            
            # Wait before retry
            if attempt < MAX_RETRIES - 1:
                time.sleep(1)
        
        logger.error(f"{prefix} SYNC FAILED after {MAX_RETRIES} retries")
    
    def _sync_exit_data(self, plate, ticket_code, frame, gate):
        """[ASYNC] Gửi data xe RA lên hosting sau barrier close"""
        try:
            prefix = f"[G{gate}_Out]"
            
            # Upload ảnh exit
            img_result = self.img_uploader.capture_and_upload(frame, ticket_code, 'exit')
            img_path = img_result.get('path', '') if img_result.get('success') else ''
            
            # Checkout được gọi trong handle_exit rồi
            logger.info(f"{prefix} SYNC OK: {plate} | {ticket_code}")
                
        except Exception as e:
            logger.error(f"[G{gate}_Out] Sync error: {e}")

    # === ENTRY GATE 1 (ESP32-CAM) ===
    def handle_entry(self):
        """[G1_In] Xử lý xe vào - Dùng ESP32-CAM"""
        if not self.gate1_entry_lock.acquire(blocking=False):
            logger.warning("[G1_In] Đang xử lý xe khác")
            return
        try:
            logger.info("[G1_In] ========== XE VÀO ==========")
            
            # Chụp ảnh từ ESP32-CAM và nhận diện BSX
            plate, frame = self._capture_plate_for_entry(gate=1)
            
            if not plate:
                logger.error("[G1_In] Không nhận diện được BSX")
                self._entry_error("KHONG NHAN DIEN")
                return
            
            logger.info(f"[G1_In] BSX: {plate}")
            self._save_image(frame, plate, 'in', 1)
            self._display("in", "DANG XU LY", plate)
            
            # Kiểm tra booking
            booking_ticket = self.ticket_manager.get_booking_ticket(plate)
            ticket = None
            ticket_code = None
            qr_url = ''
            is_booking = False
            
            if booking_ticket:
                ticket = booking_ticket
                ticket_code = booking_ticket.ticket_code
                qr_url = booking_ticket.qr_url
                is_booking = True
                logger.info(f"[G1_In] Booking: {ticket_code}")
            else:
                slots = self.db.get_available_slots()
                if not slots:
                    logger.warning("[G1_In] Bãi đầy")
                    self._display("in", "BAI XE DAY", "")
                    time.sleep(3)
                    self._display("in", "X-PARKING", "Entrance")
                    return
                
                ticket = self.ticket_manager.create_walk_in_ticket(plate)
                if not ticket:
                    logger.error("[G1_In] Lỗi tạo vé")
                    self._entry_error("LOI TAO VE")
                    return
                ticket_code = ticket.ticket_code
                qr_url = ticket.qr_url
                logger.info(f"[G1_In] Vé: {ticket_code}")
            
            slots = self.db.get_available_slots()
            available_slots = slots if slots else ['A01']
            
            self.config.pending_entry = {
                'plate': plate, 'ticket': ticket, 'ticket_code': ticket_code,
                'available_slots': available_slots, 'is_booking': is_booking,
                'qr_url': qr_url, 'frame': frame.copy(), 'timestamp': time.time()
            }
            
            if is_booking:
                self._display("in", "MOI XE VAO", "DA XAC NHAN")
            else:
                self._print_ticket(ticket_code, plate, qr_url)
                self._display("in", "MOI XE VAO", ticket_code)
            
            self._barrier("in", "open")
            logger.info("[G1_In] Mở barrier")
            time.sleep(5)
            self._display("in", "X-PARKING", "Entrance")
            
        except Exception as e:
            logger.error(f"[G1_In] Lỗi: {e}")
            self._entry_error("LOI HE THONG")
        finally:
            self.gate1_entry_lock.release()
            logger.info("[G1_In] ========== XONG ==========\n")
    
    def _capture_plate_for_entry(self, gate=1):
        """Chụp ảnh từ ESP32-CAM và nhận diện BSX cho ENTRY (3 lần retry)
        Cơ chế: Python GET http://ESP32-IP/capture → nhận JPEG → LPR
        """
        MAX_RETRIES = 3
        RETRY_DELAY = 1.0
        prefix = f"[G{gate}_In]"
        
        # Lấy IP ESP32-CAM
        if gate == 1:
            esp32_ip = self.config.config['esp32_cam_gate1']
        else:
            esp32_ip = self.config.config['esp32_cam_gate2']
        
        capture_url = f"http://{esp32_ip}/capture"
        
        self._display("in", "NHAN DIEN BSX", "VUI LONG CHO", gate=gate)
        logger.info(f"{prefix} Chụp ảnh từ ESP32-CAM ({esp32_ip})...")
        
        for attempt in range(MAX_RETRIES):
            try:
                logger.info(f"{prefix} Capture ({attempt + 1}/{MAX_RETRIES}): {capture_url}")
                
                # GET ảnh từ ESP32-CAM HTTP Server
                import numpy as np
                response = requests.get(capture_url, timeout=10)
                
                if response.status_code != 200:
                    logger.warning(f"{prefix} HTTP {response.status_code}")
                    time.sleep(RETRY_DELAY)
                    continue
                
                # Decode JPEG → OpenCV frame
                nparr = np.frombuffer(response.content, np.uint8)
                frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
                
                if frame is None:
                    logger.warning(f"{prefix} Decode ảnh thất bại")
                    time.sleep(RETRY_DELAY)
                    continue
                
                size_kb = len(response.content) / 1024
                logger.info(f"{prefix} Nhận ảnh: {size_kb:.1f}KB ({frame.shape[1]}x{frame.shape[0]})")
                
                # Nhận diện BSX
                plate = self._recognize_plate(frame)
                if plate:
                    logger.info(f"{prefix} BSX: {plate}")
                    return plate, frame
                else:
                    logger.warning(f"{prefix} Không nhận diện được BSX ({attempt + 1}/{MAX_RETRIES})")
                    time.sleep(RETRY_DELAY)
                    
            except requests.ConnectionError:
                logger.error(f"{prefix} Không kết nối được ESP32-CAM ({esp32_ip})")
                time.sleep(RETRY_DELAY)
            except requests.Timeout:
                logger.error(f"{prefix} Timeout ESP32-CAM")
                time.sleep(RETRY_DELAY)
            except Exception as e:
                logger.error(f"{prefix} Lỗi: {e}")
                time.sleep(RETRY_DELAY)
        
        logger.error(f"{prefix} THẤT BẠI sau {MAX_RETRIES} lần")
        return None, None

    def _entry_error(self, msg):
        self._display("in", msg, "VUI LONG THU LAI")
        time.sleep(3)
        self._display("in", "X-PARKING", "Entrance")

    def _print_ticket(self, ticket_code, plate, qr_url):
        try:
            from create_ticket import create_and_print_ticket
            from datetime import datetime
            now = datetime.now()
            create_and_print_ticket(
                license_plate=plate,
                token=ticket_code,
                qr_url=qr_url,
                time_in=now.strftime('%H:%M:%S'),
                date_in=now.strftime('%d/%m/%Y'),
                auto_open=True
            )
        except Exception as e:
            logger.error(f"Print ticket error: {e}")

    # === EXIT GATE 1 (Webcam) ===
    def handle_exit(self):
        """[G1_Out] Xử lý xe ra - Dùng Webcam"""
        if not self.gate1_exit_lock.acquire(blocking=False):
            logger.warning("[G1_Out] Đang xử lý xe khác")
            return
        
        start_flow = time.time()
        plate = None
        frame = None
        api_data = None
        qr_result = None
        
        try:
            logger.info("[G1_Out] ========== XE RA ==========")
            
            # ========== BƯỚC 1: Webcam chụp ảnh + Nhận diện BSX ==========
            plate, frame = self._capture_plate_for_exit(gate=1)
            
            if not plate:
                logger.error("[G1_Out] Không nhận diện được BSX")
                self._exit_error("KHONG NHAN DIEN BSX", "VUI LONG THU LAI")
                return
            
            logger.info(f"[G1_Out] BSX: {plate}")
            self._display("out", "DA NHAN DIEN", plate)
            self.executor.submit(self._save_exit_image, frame, plate)
            time.sleep(1.5)
            
            # Kiểm tra cache + xử lý song song
            api_data = ExitCacheManager.get(plate, gate=1)
            logger.info("[G1_Out] Xử lý song song (API + QR)...")
            
            # Chuẩn bị QR scan
            self.config.waiting_for_qr = True
            self.config.current_exit_plate = plate
            self.config.qr_scan_result = None
            
            # Tạo futures cho parallel execution
            futures = {}
            
            if not api_data:
                futures['api'] = self.executor.submit(self._fetch_exit_data, plate, 1)
                logger.info("[G1_Out] Gọi API...")
            else:
                logger.info(f"[G1_Out] Cache: {plate}")
            
            futures['qr'] = self.executor.submit(self._scan_qr_parallel, 1)
            
            # Chờ cả 2 task hoàn thành (timeout 30s)
            PARALLEL_TIMEOUT = 30
            wait_start = time.time()
            
            while time.time() - wait_start < PARALLEL_TIMEOUT:
                # Kiểm tra API (nếu đang chạy)
                if 'api' in futures and futures['api'].done():
                    api_data = futures['api'].result()
                
                # Kiểm tra QR
                if self.config.qr_scan_result:
                    qr_result = self.config.qr_scan_result
                
                # Đủ cả 2 → thoát
                if api_data is not None and qr_result is not None:
                    break
                
                time.sleep(0.1)
            
            self.config.waiting_for_qr = False
            
            if api_data is None:
                logger.error("[G1_Out] API thất bại")
                self._exit_error("LOI KET NOI", "THU LAI SAU")
                return
            
            if not api_data.get('found', False):
                error = api_data.get('error', 'UNKNOWN')
                logger.error(f"[G1_Out] Xe không có: {error}")
                ExitCacheManager.clear(gate=1)
                
                if error == 'BSX_NOT_IN_PARKING':
                    self._exit_error("XE KHONG CO", "TRONG HE THONG")
                else:
                    self._exit_error("LOI DU LIEU", "VUI LONG THU LAI")
                return
            
            expected_ticket = api_data.get('ticket_code', '')
            status = api_data.get('status', '')
            logger.info(f"[G1_Out] Vé: {expected_ticket} | Status: {status}")
            
            if not qr_result:
                logger.error("[G1_Out] Không đọc được QR")
                self._exit_error("KHONG DOC DUOC QR", "VUI LONG THU LAI")
                return
            
            logger.info(f"[G1_Out] QR: {qr_result}")
            
            if qr_result != expected_ticket:
                logger.warning(f"[G1_Out] Vé không khớp: {qr_result} vs {expected_ticket}")
                self._exit_error("VE KHONG KHOP", "VUI LONG THU LAI")
                return
            
            logger.info("[G1_Out] Xác thực OK")
            
            # ========== BƯỚC 4: Kiểm tra thanh toán ==========
            status = api_data.get('status', '')
            
            if status == 'USED':
                logger.warning("[G1_Out] Vé đã dùng")
                ExitCacheManager.clear(gate=1)
                self._exit_error("VE DA SU DUNG", "VUI LONG THU LAI")
                return
            
            if status == 'PENDING':
                amount = api_data.get('amount', 0)
                logger.warning(f"[G1_Out] Chưa thanh toán: {amount:,}đ")
                self._exit_error("CHUA THANH TOAN", f"{amount:,}d" if amount else "")
                return
            
            if api_data.get('has_overstay', False) and api_data.get('overstay_amount', 0) > 0:
                overstay_fee = api_data.get('overstay_amount', 0)
                overstay_mins = api_data.get('overstay_minutes', 0)
                logger.warning(f"[G1_Out] Quá giờ {overstay_mins}p - {overstay_fee:,}đ")
                self._display("out", f"QUA GIO {overstay_mins}P", f"PHI: {overstay_fee:,}d")
                time.sleep(2)
                self._display("out", "QUET QR", "DE THANH TOAN")
                time.sleep(5)
                self._display("out", "X-PARKING", "Exit")
                return
            
            if not api_data.get('allow_exit', False):
                error_reason = api_data.get('error_reason', 'UNKNOWN')
                logger.error(f"[G1_Out] Không cho ra: {error_reason}")
                self._exit_error("KHONG THE RA", "VUI LONG THU LAI")
                return
            
            # Checkout + Lưu pending exit để sync sau barrier close
            self.executor.submit(self.db.checkout, expected_ticket, plate)
            self.executor.submit(ExitCacheManager.clear, 1)
            
            # Lưu pending exit để upload ảnh sau barrier close
            self.config.pending_exit = {
                'plate': plate, 'ticket_code': expected_ticket,
                'frame': frame.copy() if frame is not None else None,
                'timestamp': time.time()
            }
            
            paid = api_data.get('amount', 0)
            logger.info(f"[G1_Out] CHECKOUT OK | {plate} | {expected_ticket} | {paid:,}đ")
            
            self._display("out", "TAM BIET", "HEN GAP LAI")
            self._barrier("out", "open")
            
        except Exception as e:
            logger.error(f"[G1_Out] Lỗi: {e}")
            self._exit_error("LOI HE THONG", "VUI LONG THU LAI")
        finally:
            self.config.waiting_for_qr = False
            self.config.current_exit_plate = None
            self.config.qr_scan_result = None
            self.gate1_exit_lock.release()
            elapsed = time.time() - start_flow
            logger.info(f"[G1_Out] ========== XONG ({elapsed:.1f}s) ==========\n")
    
    def _fetch_exit_data(self, plate, gate=1):
        """[PARALLEL] Gọi API lấy data xe ra - HỖ TRỢ CẢ 2 GATE"""
        prefix = f"[G{gate}_Out]"
        try:
            logger.info(f"{prefix} API: Lấy data {plate}...")
            data = self.db.verify_exit_full(plate)
            
            if data and data.get('found', False):
                ExitCacheManager.set(plate, data, gate=gate)
            
            return data
        except Exception as e:
            logger.error(f"{prefix} API error: {e}")
            return None
    
    def _capture_plate_for_exit(self, gate=1):
        """Chụp ảnh từ Webcam và nhận diện BSX cho EXIT (3 lần retry)"""
        MAX_RETRIES = 3
        prefix = f"[G{gate}_Out]"
        
        self._display("out", "NHAN DIEN BSX", "VUI LONG CHO", gate=gate)
        logger.info(f"{prefix} Nhận diện BSX từ Webcam...")
        
        for attempt in range(MAX_RETRIES):
            frame = self.camera.capture_frame('out', gate=gate)
            
            if frame is None:
                logger.warning(f"{prefix} Không nhận được ảnh ({attempt + 1}/{MAX_RETRIES})")
                time.sleep(0.3)
                continue
            
            plate = self._recognize_plate(frame)
            if plate:
                logger.info(f"{prefix} BSX: {plate}")
                return plate, frame
            else:
                logger.warning(f"{prefix} Không nhận diện được BSX ({attempt + 1}/{MAX_RETRIES})")
                time.sleep(0.5)
        
        logger.error(f"{prefix} THẤT BẠI sau {MAX_RETRIES} lần")
        return None, None
    
    def _scan_qr_parallel(self, gate=1):
        """[PARALLEL] Scan QR từ Webcam - HỖ TRỢ CẢ 2 GATE"""
        MAX_RETRIES = 3
        RETRY_DELAY = 2.5
        prefix = f"[G{gate}_Out]"
        
        logger.info(f"{prefix} Scan QR từ Webcam...")
        
        for attempt in range(MAX_RETRIES):
            # Kiểm tra biến riêng cho mỗi gate
            if gate == 1:
                if not self.config.waiting_for_qr:
                    break
            else:
                if not self.config.waiting_for_qr_gate2:
                    break
            
            # Hiển thị SCAN VE trong thời gian chờ delay 2.5s
            self._display("out", "SCAN VE", "DUA VE VAO", gate=gate)
            time.sleep(RETRY_DELAY)
            
            # Sau delay, chụp ảnh từ Webcam và scan QR
            self._display("out", "NHAN DIEN VE", "VUI LONG CHO", gate=gate)
            logger.info(f"{prefix} QR ({attempt + 1}/{MAX_RETRIES})")
            
            # Chụp từ Webcam (camera_type='out')
            frame = self.camera.capture_frame('out', gate=gate)
            if frame is None:
                logger.warning(f"{prefix} Không nhận được ảnh từ Webcam")
                continue
            
            # Scan QR từ frame
            ticket_code = self._scan_qr_from_webcam_frame(frame, gate)
            if ticket_code:
                logger.info(f"{prefix} QR: {ticket_code}")
                # Lưu kết quả
                if gate == 1:
                    self.config.qr_scan_result = ticket_code
                else:
                    self.config.qr_scan_result_gate2 = ticket_code
                return ticket_code
            
            logger.warning(f"{prefix} QR không nhận diện được")
        
        logger.error(f"{prefix} QR thất bại sau {MAX_RETRIES} lần")
        return None
    
    def _scan_qr_from_webcam_frame(self, frame, gate=1):
        """Scan QR từ frame Webcam và trả về ticket_code"""
        try:
            from qr_scanner import scan_qr_from_frame, extract_ticket_code
            
            # Scan QR từ frame gốc
            qr_content = scan_qr_from_frame(frame)
            
            # Thử grayscale nếu không được
            if not qr_content:
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                qr_content = scan_qr_from_frame(gray)
            
            if not qr_content:
                return None
            
            ticket_code = extract_ticket_code(qr_content)
            if ticket_code:
                # Lưu ảnh vé
                try:
                    tickets_dir = os.path.join(os.path.dirname(__file__), f'tickets_out_gate{gate}')
                    os.makedirs(tickets_dir, exist_ok=True)
                    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                    filename = f"{ticket_code}_{timestamp}.jpg"
                    cv2.imwrite(os.path.join(tickets_dir, filename), frame)
                    logger.info(f"[G{gate}] Lưu ảnh vé: {filename}")
                except Exception as e:
                    logger.warning(f"[G{gate}] Lưu ảnh vé lỗi: {e}")
                return ticket_code
            
            return None
        except Exception as e:
            logger.error(f"[G{gate}] Scan QR lỗi: {e}")
            return None
    
    def _save_exit_image(self, frame, plate, gate=1):
        """[ASYNC] Lưu ảnh xe ra - dùng chung cho cả 2 gate"""
        try:
            img_out_dir = os.path.join(os.path.dirname(__file__), f'img_out_gate{gate}')
            os.makedirs(img_out_dir, exist_ok=True)
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            filename = f"{plate}_{timestamp}.jpg"
            cv2.imwrite(os.path.join(img_out_dir, filename), frame)
            logger.info(f"[G{gate}_Out] Lưu ảnh: {filename}")
        except Exception as e:
            logger.warning(f"[G{gate}_Out] Lưu ảnh lỗi: {e}")

    def _exit_error(self, line1, line2="VUI LONG THU LAI"):
        self._display("out", line1, line2)
        time.sleep(3)
        self._display("out", "X-PARKING", "Exit")
    
    # === HELPERS ===
    def _recognize_plate(self, frame):
        """Nhận diện biển số - trả về plate string hoặc None"""
        try:
            if not self.lpr.is_ready():
                self.lpr.load_models()
            
            result = self.lpr.detect_and_read_plate(frame)
            
            if result['success'] and result['plates']:
                plate_info = result['plates'][0]
                plate = plate_info['text'].upper().strip()
                # Bỏ dấu - khỏi biển số (98K1-02897 -> 98K102897)
                plate = plate.replace('-', '').replace(' ', '')
                conf = plate_info.get('confidence', 0)
                if len(plate) >= 4:
                    logger.debug(f"LPR: {plate} (conf: {conf:.2f})")
                    return plate
            return None
        except Exception as e:
            logger.error(f"LPR error: {e}")
            return None

    # === IMAGE UPLOAD HELPERS ===
    def _upload_entry_image(self, frame, ticket_code):
        """Upload ảnh xe vào (chạy async)"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'entry')
            if result.get('success'):
                logger.info(f" Entry image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f" Entry image upload failed: {result.get('error')}")
        except Exception as e:
            logger.error(f"Entry image upload error: {e}")
    
    def _upload_exit_image(self, frame, ticket_code):
        """Upload ảnh xe ra (chạy async)"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'exit')
            if result.get('success'):
                logger.info(f" Exit image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f" Exit image upload failed: {result.get('error')}")
        except Exception as e:
            logger.error(f"Exit image upload error: {e}")
    
    def _upload_ticket_image(self, frame, ticket_code):
        """Upload ảnh vé ra (chạy async)"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'ticket')
            if result.get('success'):
                logger.info(f" Ticket image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f" Ticket image upload failed: {result.get('error')}")
        except Exception as e:
            logger.error(f"Ticket image upload error: {e}")

    def _handle_slot_update(self, payload):
        """Xử lý slot update từ ESP32 - COMMIT entry khi xe vào slot"""
        try:
            data = json.loads(payload)
            event = data.get('event', '')
            
            if event == 'CAR_ENTERED_SLOT':
                slot_id = data.get('data', '')
                if not slot_id:
                    return
                
                # Cập nhật GUI
                self.camera.update_slot_status(slot_id, 'occupied')
                
                # === COMMIT PENDING ENTRY ===
                pending = getattr(self.config, 'pending_entry', None)
                if pending and slot_id in pending.get('available_slots', []):
                    plate = pending['plate']
                    ticket_code = pending['ticket_code']
                    is_booking = pending.get('is_booking', False)
                    ticket = pending.get('ticket')
                    entry_frame = pending.get('frame')
                    
                    logger.info(f"🅿 Xe vào slot {slot_id}")
                    
                    # Upload ảnh xe vào trước - TẠM COMMENT
                    # if entry_frame is not None:
                    #     logger.info(" Upload ảnh xe vào...")
                    #     upload_result = self.img_uploader.capture_and_upload(entry_frame, ticket_code, 'entry')
                    #     if upload_result.get('success'):
                    #         logger.info(f" Upload ảnh OK ({upload_result.get('size_kb')}KB)")
                    #     else:
                    #         logger.warning(f" Upload ảnh thất bại: {upload_result.get('error')}")
                    
                    # Commit vào DB
                    logger.info(" Lưu dữ liệu vào DB...")
                    self.db.checkin(plate, slot_id, ticket_code)
                    
                    # Update booking nếu có
                    if is_booking and ticket and hasattr(ticket, 'booking_id') and ticket.booking_id:
                        logger.info(f" Update booking status: in_parking")
                        self.db.update_booking(ticket.booking_id, 'in_parking')
                    
                    logger.info("="*50)
                    logger.info(f" XE VÀO THÀNH CÔNG!")
                    logger.info(f"   BSX: {plate} | Slot: {slot_id} | Vé: {ticket_code}")
                    if is_booking:
                        logger.info(f"   Loại: BOOKING")
                    logger.info("="*50)
                    
                    # Clear pending
                    self.config.pending_entry = None
                else:
                    logger.info(f"🅿 Slot {slot_id}: Có xe")
                    
            elif event == 'MONITOR_TIMEOUT':
                # Xe không vào slot - rollback
                pending = getattr(self.config, 'pending_entry', None)
                if pending:
                    logger.warning(f" TIMEOUT: {pending['plate']} không vào slot")
                    self.config.pending_entry = None
                        
        except Exception as e:
            logger.error(f"Slot error: {e}")
        
        # Slot status từ ESP32 không dùng nữa - dùng global count từ DB

    def _handle_alert(self, payload):
        """Xử lý cảnh báo từ ESP32_IN
        ESP32 gửi: {"event": "EMERGENCY_SMOKE", "data": "4500"} hoặc {"event": "EMERGENCY_CLEAR"}
        """
        try:
            data = json.loads(payload)
            event = data.get('event', '')
            
            if event == 'EMERGENCY_SMOKE':
                self.config.emergency_mode = True
                self._barrier("in", "open")
                self._barrier("out", "open")
                self._display("in", "KHAN CAP", "DI CHAN NGAY")
                self._display("out", "KHAN CAP", "DI CHAN NGAY")
                if not self.config.gas_alert_sent:
                    gas_value = int(data.get('data', 0))
                    self.email.send_alert_email(gas_value, "Bãi đỗ xe XParking")
                    self.config.gas_alert_sent = True
                logger.warning(f" EMERGENCY: Smoke detected! Value: {data.get('data')}")
                    
            elif event == 'EMERGENCY_CLEAR':
                self.config.emergency_mode = False
                self.config.gas_alert_sent = False
                self._display("in", "X-PARKING", "Entrance")
                self._display("out", "X-PARKING", "Exit")
                logger.info(" Emergency cleared")
        except Exception as e:
            logger.error(f"Alert handling error: {e}")

    # === SLOT MANAGEMENT (SIMPLIFIED) ===
    def _on_car_entered(self, gate=1):
        """Xe đã vào bãi (barrier closed) → +1 slot, sync data + ảnh"""
        try:
            prefix = f"[G{gate}_In]"
            
            # 1. Increment slot count
            slot_result = self.db.increment_slot()
            if slot_result and slot_result.get('success'):
                count = self.db.get_slot_count()
                logger.info(f"{prefix} SLOT +1 → {count['occupied']}/{count['total']}")
            
            # 2. Sync data + ảnh lên hosting (từ pending_entry)
            pending_attr = 'pending_entry' if gate == 1 else 'pending_entry_gate2'
            pending = getattr(self.config, pending_attr, None)
            if pending and pending.get('frame') is not None:
                plate = pending.get('plate', '')
                ticket_code = pending.get('ticket_code', '')
                frame = pending.get('frame')
                is_booking = pending.get('is_booking', False)
                
                # Upload ảnh + checkin (async trong thread hiện tại)
                self._sync_entry_data(plate, ticket_code, frame, gate, is_booking)
                
                # Clear pending
                setattr(self.config, pending_attr, None)
                
        except Exception as e:
            logger.error(f"[G{gate}_In] _on_car_entered error: {e}")
    
    def _on_car_exited(self, gate=1):
        """Xe đã ra bãi (barrier closed) → -1 slot, sync data + ảnh"""
        try:
            prefix = f"[G{gate}_Out]"
            
            # 1. Decrement slot count
            slot_result = self.db.decrement_slot()
            if slot_result and slot_result.get('success'):
                count = self.db.get_slot_count()
                logger.info(f"{prefix} SLOT -1 → {count['occupied']}/{count['total']}")
            
            # 2. Upload ảnh exit (từ pending_exit)
            pending_attr = 'pending_exit' if gate == 1 else 'pending_exit_gate2'
            pending = getattr(self.config, pending_attr, None)
            if pending and pending.get('frame') is not None:
                plate = pending.get('plate', '')
                ticket_code = pending.get('ticket_code', '')
                frame = pending.get('frame')
                
                # Upload ảnh exit
                self._sync_exit_data(plate, ticket_code, frame, gate)
                
                # Clear pending
                setattr(self.config, pending_attr, None)
                
        except Exception as e:
            logger.error(f"[G{gate}_Out] _on_car_exited error: {e}")

    # === ENTRY GATE 2 (ESP32-CAM) ===
    def handle_entry_gate2(self):
        """[G2_In] Xử lý xe vào - Dùng ESP32-CAM"""
        if not self.gate2_entry_lock.acquire(blocking=False):
            logger.warning("[G2_In] Đang xử lý xe khác - bỏ qua")
            return
        try:
            logger.info("[G2_In] ========== XE VÀO ==========")
            
            # Chụp ảnh từ ESP32-CAM và nhận diện BSX
            plate, frame = self._capture_plate_for_entry(gate=2)
            
            if not plate:
                logger.error("[G2_In] Không nhận diện được BSX")
                self._display("in", "KHONG NHAN DIEN", "VUI LONG THU LAI", gate=2)
                time.sleep(3)
                self._display("in", "X-PARKING", "Entrance", gate=2)
                return
            
            logger.info(f"[G2_In] BSX: {plate}")
            self._save_image(frame, plate, 'in', 2)
            self._display("in", "DANG XU LY", plate, gate=2)
            
            # Kiểm tra booking
            booking_ticket = self.ticket_manager.get_booking_ticket(plate)
            ticket = None
            ticket_code = None
            qr_url = ''
            is_booking = False
            
            if booking_ticket:
                ticket = booking_ticket
                ticket_code = booking_ticket.ticket_code
                qr_url = booking_ticket.qr_url
                is_booking = True
                logger.info(f"[G2_In] Booking: {ticket_code}")
            else:
                slots = self.db.get_available_slots()
                if not slots:
                    logger.warning("[G2_In] Bãi đầy")
                    self._display("in", "BAI XE DAY", "", gate=2)
                    time.sleep(3)
                    self._display("in", "X-PARKING", "Entrance", gate=2)
                    return
                
                ticket = self.ticket_manager.create_walk_in_ticket(plate)
                if not ticket:
                    logger.error("[G2_In] Lỗi tạo vé")
                    self._display("in", "LOI TAO VE", "VUI LONG THU LAI", gate=2)
                    time.sleep(3)
                    self._display("in", "X-PARKING", "Entrance", gate=2)
                    return
                ticket_code = ticket.ticket_code
                qr_url = ticket.qr_url
                logger.info(f"[G2_In] Vé: {ticket_code}")
            
            # Lưu pending entry cho Gate 2
            self.config.pending_entry_gate2 = {
                'plate': plate, 'ticket': ticket, 'ticket_code': ticket_code,
                'is_booking': is_booking, 'qr_url': qr_url, 
                'frame': frame.copy(), 'timestamp': time.time()
            }
            
            if is_booking:
                self._display("in", "MOI XE VAO", "DA XAC NHAN", gate=2)
            else:
                self._print_ticket(ticket_code, plate, qr_url)
                self._display("in", "MOI XE VAO", ticket_code, gate=2)
            
            self._barrier("in", "open", gate=2)
            logger.info("[G2_In] Mở barrier")
            time.sleep(5)
            self._display("in", "X-PARKING", "Entrance", gate=2)
            
        except Exception as e:
            logger.error(f"[G2_In] Lỗi: {e}")
            self._display("in", "LOI HE THONG", "VUI LONG THU LAI", gate=2)
        finally:
            self.gate2_entry_lock.release()
            logger.info("[G2_In] ========== XONG ==========\n")
    
    # === EXIT GATE 2 (Webcam) ===
    def handle_exit_gate2(self):
        """[G2_Out] Xử lý xe ra - Dùng Webcam"""
        if not self.gate2_exit_lock.acquire(blocking=False):
            logger.warning("[G2_Out] Đang xử lý xe khác - bỏ qua")
            return
        
        start_flow = time.time()
        plate = None
        frame = None
        api_data = None
        qr_result = None
        
        try:
            logger.info("[G2_Out] ========== XE RA ==========")
            
            # Webcam chụp ảnh + Nhận diện BSX
            plate, frame = self._capture_plate_for_exit(gate=2)
            
            if not plate:
                logger.error("[G2_Out] Không nhận diện được BSX")
                self._exit_error_gate2("KHONG NHAN DIEN BSX", "VUI LONG THU LAI")
                return
            
            logger.info(f"[G2_Out] BSX: {plate}")
            self._display("out", "DA NHAN DIEN", plate, gate=2)
            self.executor.submit(self._save_exit_image, frame, plate, 2)
            time.sleep(1.5)
            
            # Kiểm tra cache + xử lý song song
            api_data = ExitCacheManager.get(plate, gate=2)
            logger.info("[G2_Out] Xử lý song song (API + QR)...")
            
            self.config.waiting_for_qr_gate2 = True
            self.config.current_exit_plate_gate2 = plate
            self.config.qr_scan_result_gate2 = None
            
            futures = {}
            
            if not api_data:
                futures['api'] = self.executor.submit(self._fetch_exit_data, plate, 2)
                logger.info("[G2_Out] Gọi API...")
            else:
                logger.info(f"[G2_Out] Cache: {plate}")
            
            futures['qr'] = self.executor.submit(self._scan_qr_parallel, 2)
            
            PARALLEL_TIMEOUT = 30
            wait_start = time.time()
            
            while time.time() - wait_start < PARALLEL_TIMEOUT:
                if 'api' in futures and futures['api'].done():
                    api_data = futures['api'].result()
                
                if self.config.qr_scan_result_gate2:
                    qr_result = self.config.qr_scan_result_gate2
                
                if api_data is not None and qr_result is not None:
                    break
                
                time.sleep(0.1)
            
            self.config.waiting_for_qr_gate2 = False
            
            if api_data is None:
                logger.error("[G2_Out] API thất bại")
                self._exit_error_gate2("LOI KET NOI", "THU LAI SAU")
                return
            
            if not api_data.get('found', False):
                error = api_data.get('error', 'UNKNOWN')
                logger.error(f"[G2_Out] Xe không có: {error}")
                ExitCacheManager.clear(gate=2)
                if error == 'BSX_NOT_IN_PARKING':
                    self._exit_error_gate2("XE KHONG CO", "TRONG HE THONG")
                else:
                    self._exit_error_gate2("LOI DU LIEU", "VUI LONG THU LAI")
                return
            
            expected_ticket = api_data.get('ticket_code', '')
            status = api_data.get('status', '')
            logger.info(f"[G2_Out] Vé: {expected_ticket} | Status: {status}")
            
            if not qr_result:
                logger.error("[G2_Out] Không đọc được QR")
                self._exit_error_gate2("KHONG DOC DUOC QR", "VUI LONG THU LAI")
                return
            
            logger.info(f"[G2_Out] QR: {qr_result}")
            
            if qr_result != expected_ticket:
                logger.warning(f"[G2_Out] Vé không khớp: {qr_result} vs {expected_ticket}")
                self._exit_error_gate2("VE KHONG KHOP", "VUI LONG THU LAI")
                return
            
            logger.info("[G2_Out] Xác thực OK")
            
            if status == 'USED':
                logger.warning("[G2_Out] Vé đã dùng")
                ExitCacheManager.clear(gate=2)
                self._exit_error_gate2("VE DA SU DUNG", "VUI LONG THU LAI")
                return
            
            if status == 'PENDING':
                amount = api_data.get('amount', 0)
                logger.warning(f"[G2_Out] Chưa thanh toán: {amount:,}đ")
                self._exit_error_gate2("CHUA THANH TOAN", f"{amount:,}d" if amount else "")
                return
            
            # Checkout + Lưu pending exit để sync sau barrier close
            self.executor.submit(self.db.checkout, expected_ticket, plate)
            self.executor.submit(ExitCacheManager.clear, 2)
            
            # Lưu pending exit Gate 2
            self.config.pending_exit_gate2 = {
                'plate': plate, 'ticket_code': expected_ticket,
                'frame': frame.copy() if frame is not None else None,
                'timestamp': time.time()
            }
            
            paid = api_data.get('amount', 0)
            logger.info(f"[G2_Out] CHECKOUT OK | {plate} | {expected_ticket} | {paid:,}đ")
            
            self._display("out", "TAM BIET", "HEN GAP LAI", gate=2)
            self._barrier("out", "open", gate=2)
            
        except Exception as e:
            logger.error(f"[G2_Out] Lỗi: {e}")
            self._exit_error_gate2("LOI HE THONG", "VUI LONG THU LAI")
        finally:
            self.config.waiting_for_qr_gate2 = False
            self.config.current_exit_plate_gate2 = None
            self.config.qr_scan_result_gate2 = None
            self.gate2_exit_lock.release()
            elapsed = time.time() - start_flow
            logger.info(f"[G2_Out] ========== XONG ({elapsed:.1f}s) ==========\n")
    
    def _exit_error_gate2(self, line1, line2="VUI LONG THU LAI"):
        self._display("out", line1, line2, gate=2)
        time.sleep(3)
        self._display("out", "X-PARKING", "Exit", gate=2)
    
    def shutdown(self):
        logger.info("Shutting down...")
        self.mqtt_gate1.disconnect()
        self.mqtt_gate2.disconnect()
        self.executor.shutdown(wait=False)
