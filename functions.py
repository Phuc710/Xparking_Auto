
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
                    logger.info(f"[GATE{gate}] 📦 Cache HIT: {plate}")
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
            logger.info(f"[GATE{gate}] 📦 Cache SAVED: {plate}")
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
                logger.info(f"[GATE{gate}] 📦 Cache CLEARED")
        except Exception as e:
            logger.warning(f"[GATE{gate}] Cache clear error: {e}")

class SystemFunctions:
    def __init__(self, config, gui, lpr, db_api, email_handler):
        self.config = config
        self.gui = gui
        self.lpr = lpr
        self.db = db_api
        self.email = email_handler
        
        # Dual gate MQTT handlers
        from mqtt_gate1 import MQTTGate1
        from mqtt_gate2 import MQTTGate2
        self.mqtt_gate1 = MQTTGate1(config, self)
        self.mqtt_gate2 = MQTTGate2(config, self)
        
        # Thread pool cho xu ly song song
        self.executor = ThreadPoolExecutor(max_workers=8)
        
        # Locks rieng cho tung gate
        self.gate1_entry_lock = threading.Lock()
        self.gate1_exit_lock = threading.Lock()
        self.gate2_entry_lock = threading.Lock()
        self.gate2_exit_lock = threading.Lock()
        
        # Image uploader
        self.img_uploader = ImageUploader(config.config['site_url'])
        
        # Ticket Manager
        self.ticket_manager = TicketManager(db_api)
        
        # Gate2 state
        self.config.waiting_for_qr_gate2 = False
        self.config.current_exit_plate_gate2 = None
        self.config.qr_scan_result_gate2 = None

    # === MQTT ===
    def init_mqtt(self):
        """Khoi tao MQTT cho ca 2 gates"""
        try:
            gate1_ok = self.mqtt_gate1.connect()
            gate2_ok = self.mqtt_gate2.connect()
            
            if gate1_ok and gate2_ok:
                logger.info("✅ Dual Gate MQTT connected")
                self.gui.update_status('mqtt_status', True)
                return True
            else:
                logger.error("❌ MQTT connection failed")
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
    
    def _trigger_camera(self, gate=1):
        """Trigger ESP32-CAM chup anh"""
        if gate == 1:
            self.mqtt_gate1.trigger_camera()
        else:
            self.mqtt_gate2.trigger_camera()

    # === ENTRY GATE 1 ===
    def handle_entry(self):
        """[GATE1] Flow xe vao"""
        if not self.gate1_entry_lock.acquire(blocking=False):
            logger.warning("[GATE1] Entry busy, skip")
            return
        try:
            logger.info("="*50)
            logger.info("🚗 XE VÀO - Bắt đầu xử lý")
            logger.info("="*50)
            self._display("in", "NHAN DIEN", "VUI LONG CHO")
            
            # 1. Chụp ảnh camera
            logger.info("[GATE1] 📷 Chụp ảnh camera IN...")
            frame = None
            for attempt in range(3):
                frame = self.gui.capture_frame('in', gate=1)
                if frame is not None:
                    logger.info("✅ Chụp ảnh thành công")
                    break
                logger.warning(f"⚠️ Chụp ảnh thất bại ({attempt + 1}/3)")
                time.sleep(0.5)
            
            if frame is None:
                logger.error("❌ Camera vào lỗi")
                self._entry_error("LOI CAMERA")
                return
            
            # 2. Nhận diện BSX
            logger.info("🔍 Đang nhận diện biển số...")
            plate = self._recognize_plate(frame)
            if not plate:
                logger.error("❌ Không nhận diện được BSX")
                self._entry_error("KHONG NHAN DIEN")
                return
            
            logger.info(f"✅ BSX: {plate}")
            
            # Lưu ảnh CHỈ KHI nhận diện thành công
            try:
                import os
                from datetime import datetime
                img_in_dir = os.path.join(os.path.dirname(__file__), 'img_in_gate1')
                os.makedirs(img_in_dir, exist_ok=True)
                timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                filename = f"{plate}_{timestamp}.jpg"
                cv2.imwrite(os.path.join(img_in_dir, filename), frame)
                logger.info(f"[GATE1] 💾 Lưu ảnh IN: {filename}")
            except Exception as e:
                logger.warning(f"⚠️ Lưu ảnh IN lỗi: {e}")
            
            self.gui.update_plate_display('in', plate)
            self._display("in", "DANG XU LY", "VUI LONG CHO...")
            
            # 3. Kiểm tra booking (OOP)
            logger.info("🔍 Kiểm tra booking...")
            booking_ticket = self.ticket_manager.get_booking_ticket(plate)
            
            # 4. Xử lý vé
            ticket = None
            ticket_code = None
            qr_url = ''
            is_booking = False
            
            if booking_ticket:
                # Xe có booking đã thanh toán
                ticket = booking_ticket
                ticket_code = booking_ticket.ticket_code
                qr_url = booking_ticket.qr_url
                is_booking = True
                logger.info(f"🎫 Xe booking: {plate} | Vé: {ticket_code}")
            else:
                # Xe vãng lai - Kiểm tra slot trống
                slots = self.db.get_available_slots()
                if not slots:
                    logger.warning(f"⛔ Bãi đầy")
                    self._display("in", "BAI XE DAY", "VUI LONG QUAY LAI")
                    time.sleep(3)
                    self._display("in", "X-PARKING", "Entrance")
                    return
                
                # Tạo vé vãng lai mới
                logger.info("🎫 Đang tạo vé ...")
                ticket = self.ticket_manager.create_walk_in_ticket(plate)
                if not ticket:
                    logger.error("❌ Lỗi tạo vé")
                    self._entry_error("LOI TAO VE")
                    return
                ticket_code = ticket.ticket_code
                qr_url = ticket.qr_url
                logger.info(f"✅ Vé vãng lai: {ticket_code}")
            
            # Lấy available slots
            slots = self.db.get_available_slots()
            available_slots = slots if slots else ['A01']
            logger.info(f"✅Slots_avaiable: {available_slots}")
            
            # 5. Lưu pending entry - chờ slot sensor xác nhận (kèm frame để upload sau)
            self.config.pending_entry = {
                'plate': plate,
                'ticket': ticket,  # Lưu ticket object (OOP)
                'ticket_code': ticket_code,
                'available_slots': available_slots,
                'is_booking': is_booking,
                'qr_url': qr_url,
                'frame': frame.copy(),  # Lưu frame để upload khi vào slot
                'timestamp': time.time()
            }
            
            # 6. In vé (chỉ xe vãng lai) và mở barrier
            if is_booking:
                # Xe booking đã có vé trên web → Không in, chỉ mở barrier
                logger.info(f"🎫 Xe booking: {plate} | Vé: {ticket_code} | VÀO")
                self._display("in", "MOI XE VAO", "DA XAC NHAN")
            else:
                # Xe vãng lai → In vé mới
                logger.info("🖨️ In vé...")
                self._print_ticket(ticket_code, plate, qr_url)
                self._display("in", "MOI XE VAO", ticket_code)
            
            logger.info("🚧 Mở barrier...")
            self._barrier("in", "open")
            
            # 7. Gửi lệnh giám sát slots cho ESP32_GATE1
            self._publish("xparking/gate1/command", json.dumps({
                "event": "MONITOR_SLOTS",
                "station": "IN",
                "slots": available_slots
            }))
            
            logger.info("⏳ Đợi xe vào slot...")
            time.sleep(5)
            self._display("in", "X-PARKING", "Entrance")
            
        except Exception as e:
            logger.error(f"❌ Entry error: {e}")
            import traceback
            traceback.print_exc()
            self._entry_error("LOI HE THONG")
        finally:
            self.gate1_entry_lock.release()
            logger.info("[GATE1] 🏁 Ket thuc xu ly xe vao\n")

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

    # === EXIT GATE 1 ===
    def handle_exit(self):
        """[GATE1] Flow xe ra voi xu ly song song"""
        if not self.gate1_exit_lock.acquire(blocking=False):
            logger.warning("[GATE1] Exit busy")
            return
        
        EXIT_TIMEOUT = 60
        start_flow = time.time()
        plate = None
        api_data = None
        qr_result = None
        
        try:
            logger.info("="*50)
            logger.info("🚀 XE RA - GATE 1")
            logger.info("="*50)
            self._display("out", "NHAN DIEN BSX", "VUI LONG CHO...")
            
            # ========== BƯỚC 1: Chụp ảnh + Nhận diện BSX (ESP32-CAM) ==========
            # Note: Exit sử dụng ESP32-CAM, không dùng webcam
            frame = None
            for attempt in range(3):
                frame = self.gui.capture_frame('out', gate=1)
                if frame is not None:
                    break
                time.sleep(0.3)
            
            if frame is None:
                self._exit_error("LOI CAMERA")
                return
            
            plate = self._recognize_plate(frame)
            if not plate:
                self._exit_error("KHONG NHAN DIEN BSX", "VUI LONG THU LAI")
                return
            
            logger.info(f"✅ BSX: {plate}")
            self.gui.update_plate_display('out', plate)
            self._display("out", "DA NHAN DIEN", "DANG XU LY...")
            
            # Lưu ảnh (async)
            self.executor.submit(self._save_exit_image, frame, plate)
            
            # ========== BƯỚC 2: Kiểm tra cache + SONG SONG ==========
            api_data = ExitCacheManager.get(plate, gate=1)
            
            logger.info("⚡ Bắt đầu xử lý SONG SONG...")
            
            # Chuẩn bị QR scan
            self.config.waiting_for_qr = True
            self.config.current_exit_plate = plate
            self.config.qr_scan_result = None
            
            # Tạo futures cho parallel execution
            futures = {}
            
            # Task 1: API call - CHỈ gọi nếu KHÔNG có cache
            if not api_data:
                futures['api'] = self.executor.submit(self._fetch_exit_data, plate)
                logger.info("📡 API: Đang lấy data...")
            else:
                logger.info(f"📦 Cache HIT: {plate} → Skip API call")
            
            # Task 2: QR scan - LUÔN scan để verify
            futures['qr'] = self.executor.submit(self._scan_qr_parallel)
            
            # Hiện thông báo scan
            self._display("out", "SCAN VE", "DUA VE VAO CAM")
            
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
            
            # ========== BƯỚC 3: Kiểm tra kết quả ==========
            
            # 3.1 Kiểm tra API data
            if api_data is None:
                logger.error("❌ Không lấy được data từ API")
                self._exit_error("LOI KET NOI", "THU LAI SAU")
                return
            
            if not api_data.get('found', False):
                error = api_data.get('error', 'UNKNOWN')
                logger.error(f"❌ BSX không tồn tại: {error}")
                ExitCacheManager.clear(gate=1)  # Clear cache lỗi
                
                if error == 'BSX_NOT_IN_PARKING':
                    self._exit_error("XE KHONG CO", "TRONG HE THONG")
                else:
                    self._exit_error("LOI DU LIEU", "VUI LONG THU LAI")
                return
            
            expected_ticket = api_data.get('ticket_code', '')
            logger.info(f"📦 API Data: Vé={expected_ticket}, Status={api_data.get('status')}")
            
            # 3.2 Kiểm tra QR
            if not qr_result:
                logger.error("❌ Không đọc được QR")
                # KHÔNG clear cache - để retry dùng lại
                self._exit_error("KHONG DOC DUOC QR", "VUI LONG THU LAI")
                return
            
            logger.info(f"🎫 QR: {qr_result}")
            
            # 3.3 So sánh mã vé
            if qr_result != expected_ticket:
                logger.warning(f"❌ Vé không khớp: QR={qr_result} vs DB={expected_ticket}")
                # KHÔNG clear cache - để retry
                self._exit_error("VE KHONG KHOP", "VUI LONG THU LAI")
                return
            
            logger.info("✅ Vé KHỚP!")
            
            # ========== BƯỚC 4: Kiểm tra thanh toán ==========
            status = api_data.get('status', '')
            
            if status == 'USED':
                logger.warning("⚠️ Vé đã sử dụng")
                ExitCacheManager.clear(gate=1)
                self._exit_error("VE DA SU DUNG", "VUI LONG THU LAI")
                return
            
            if status == 'PENDING':
                amount = api_data.get('amount', 0)
                logger.warning(f"⚠️ Chưa thanh toán: {amount:,}đ")
                self._exit_error("CHUA THANH TOAN", f"{amount:,}d" if amount else "")
                return
            
            # Kiểm tra overstay
            if api_data.get('has_overstay', False) and api_data.get('overstay_amount', 0) > 0:
                overstay_fee = api_data.get('overstay_amount', 0)
                overstay_mins = api_data.get('overstay_minutes', 0)
                logger.warning(f"⚠️ Quá giờ {overstay_mins}p - Phí: {overstay_fee:,}đ")
                self._display("out", f"QUA GIO {overstay_mins}P", f"PHI: {overstay_fee:,}d")
                time.sleep(2)
                self._display("out", "QUET QR", "DE THANH TOAN")
                time.sleep(5)
                self._display("out", "X-PARKING", "Exit")
                return
            
            # Kiểm tra allow_exit
            if not api_data.get('allow_exit', False):
                error_reason = api_data.get('error_reason', 'UNKNOWN')
                logger.error(f"❌ Không cho ra: {error_reason}")
                self._exit_error("KHONG THE RA", "VUI LONG THU LAI")
                return
            
            # ========== BƯỚC 5: CHECKOUT (Song song: DB + Clear cache) ==========
            logger.info("🔓 Đang checkout...")
            
            # Song song: checkout API + clear cache
            checkout_future = self.executor.submit(self.db.checkout, expected_ticket, plate)
            clear_future = self.executor.submit(ExitCacheManager.clear, 1)
            
            # Không chờ kết quả checkout - mở barrier trước
            paid = api_data.get('amount', 0)
            logger.info("="*50)
            logger.info(f"✅ CHECKOUT THÀNH CÔNG!")
            logger.info(f"   BSX: {plate} | Vé: {expected_ticket} | Phí: {paid:,}đ")
            logger.info("="*50)
            
            # ========== BƯỚC 6: Mở barrier ==========
            self._display("out", "TAM BIET", "HEN GAP LAI")
            self._barrier("out", "open")
            
        except Exception as e:
            logger.error(f"❌ Exit error: {e}")
            import traceback
            traceback.print_exc()
            self._exit_error("LOI HE THONG", "VUI LONG THU LAI")
        finally:
            self.config.waiting_for_qr = False
            self.config.current_exit_plate = None
            self.config.qr_scan_result = None
            self.gate1_exit_lock.release()
            elapsed = time.time() - start_flow
            logger.info(f"[GATE1] 🏁 Ket thuc xu ly xe ra ({elapsed:.1f}s)\n")
    
    def _fetch_exit_data(self, plate):
        """[PARALLEL] Gọi API lấy toàn bộ data xe ra"""
        try:
            logger.info(f"📡 API: Đang lấy data cho {plate}...")
            data = self.db.verify_exit_full(plate)
            
            if data and data.get('found', False):
                # Lưu vào cache
                ExitCacheManager.set(plate, data, gate=1)
            
            return data
        except Exception as e:
            logger.error(f"API error: {e}")
            return None
    
    def _scan_qr_parallel(self):
        """[PARALLEL] Scan QR qua MQTT"""
        SCAN_TIMEOUT = 25
        scan_start = time.time()
        
        for attempt in range(5):
            if time.time() - scan_start > SCAN_TIMEOUT:
                break
            
            if not self.config.waiting_for_qr:
                break
            
            self.config.qr_scan_result = None
            
            # Trigger ESP32-CAM qua MQTT
            self._trigger_camera(gate=1)
            
            # Cho MQTT response (toi da 5s)
            wait_start = time.time()
            while time.time() - wait_start < 5:
                if self.config.qr_scan_result:
                    return self.config.qr_scan_result
                time.sleep(0.1)
            
            if attempt < 4:
                logger.info(f"📸 Retry QR ({attempt + 2}/5)")
                self._display("out", "SCAN LAI", f"LAN {attempt + 2}/5")
                time.sleep(0.2)
        
        return None
    
    def _process_qr_from_bytes(self, jpeg_bytes):
        """Xu ly QR tu anh JPEG binary"""
        if not self.config.waiting_for_qr:
            return
        
        try:
            from qr_scanner import scan_qr_from_bytes, extract_ticket_code
            import cv2
            import numpy as np
            from datetime import datetime
            import os
            
            # Decode JPEG
            nparr = np.frombuffer(jpeg_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            if frame is None:
                logger.warning("Decode anh loi")
                return
            
            # Scan QR
            qr_content = scan_qr_from_bytes(jpeg_bytes)
            
            # Thu grayscale neu khong duoc
            if not qr_content:
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                from qr_scanner import scan_qr_from_frame
                qr_content = scan_qr_from_frame(gray)
            
            if not qr_content:
                logger.warning("⚠️ QR khong nhan dien duoc")
                return
            
            ticket_code = extract_ticket_code(qr_content)
            if ticket_code:
                logger.info(f"✅ QR: {ticket_code}")
                self.config.qr_scan_result = ticket_code
                
                # Luu anh ve
                try:
                    tickets_dir = os.path.join(os.path.dirname(__file__), 'tickets_out_gate1')
                    os.makedirs(tickets_dir, exist_ok=True)
                    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                    filename = f"{ticket_code}_{timestamp}.jpg"
                    cv2.imwrite(os.path.join(tickets_dir, filename), frame)
                    logger.info(f"💾 Luu anh ve: {filename}")
                except Exception as e:
                    logger.warning(f"Luu anh loi: {e}")
            else:
                logger.warning("QR khong hop le")
                
        except Exception as e:
            logger.error(f"Xu ly QR loi: {e}")
    
    def _save_exit_image(self, frame, plate):
        """[ASYNC] Lưu ảnh xe ra"""
        try:
            img_out_dir = os.path.join(os.path.dirname(__file__), 'img_out_gate1')
            os.makedirs(img_out_dir, exist_ok=True)
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            filename = f"{plate}_{timestamp}.jpg"
            cv2.imwrite(os.path.join(img_out_dir, filename), frame)
            logger.info(f"[GATE1] 💾 Lưu ảnh OUT: {filename}")
        except Exception as e:
            logger.warning(f"⚠️ Lưu ảnh OUT lỗi: {e}")
    
    def _upload_exit_image_safe(self, frame, ticket_code):
        """Upload ảnh xe ra với try-catch"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'exit')
            if result.get('success'):
                logger.info(f"✅ Upload ảnh OK ({result.get('size_kb')}KB)")
            else:
                logger.warning(f"⚠️ Upload ảnh thất bại: {result.get('error')}")
        except Exception as e:
            logger.warning(f"⚠️ Upload ảnh lỗi: {e}")

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
                logger.info(f"📷 Entry image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f"❌ Entry image upload failed: {result.get('error')}")
        except Exception as e:
            logger.error(f"Entry image upload error: {e}")
    
    def _upload_exit_image(self, frame, ticket_code):
        """Upload ảnh xe ra (chạy async)"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'exit')
            if result.get('success'):
                logger.info(f"📷 Exit image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f"❌ Exit image upload failed: {result.get('error')}")
        except Exception as e:
            logger.error(f"Exit image upload error: {e}")
    
    def _upload_ticket_image(self, frame, ticket_code):
        """Upload ảnh vé ra (chạy async)"""
        try:
            result = self.img_uploader.capture_and_upload(frame, ticket_code, 'ticket')
            if result.get('success'):
                logger.info(f"📷 Ticket image uploaded: {result.get('size_kb')}KB")
            else:
                logger.warning(f"❌ Ticket image upload failed: {result.get('error')}")
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
                self.gui.update_slot_status(slot_id, 'occupied')
                
                # === COMMIT PENDING ENTRY ===
                pending = getattr(self.config, 'pending_entry', None)
                if pending and slot_id in pending.get('available_slots', []):
                    plate = pending['plate']
                    ticket_code = pending['ticket_code']
                    is_booking = pending.get('is_booking', False)
                    ticket = pending.get('ticket')
                    entry_frame = pending.get('frame')
                    
                    logger.info(f"🅿️ Xe vào slot {slot_id}")
                    
                    # Upload ảnh xe vào trước - TẠM COMMENT
                    # if entry_frame is not None:
                    #     logger.info("📤 Upload ảnh xe vào...")
                    #     upload_result = self.img_uploader.capture_and_upload(entry_frame, ticket_code, 'entry')
                    #     if upload_result.get('success'):
                    #         logger.info(f"✅ Upload ảnh OK ({upload_result.get('size_kb')}KB)")
                    #     else:
                    #         logger.warning(f"⚠️ Upload ảnh thất bại: {upload_result.get('error')}")
                    
                    # Commit vào DB
                    logger.info("💾 Lưu dữ liệu vào DB...")
                    self.db.checkin(plate, slot_id, ticket_code)
                    
                    # Update booking nếu có
                    if is_booking and ticket and hasattr(ticket, 'booking_id') and ticket.booking_id:
                        logger.info(f"📝 Update booking status: in_parking")
                        self.db.update_booking(ticket.booking_id, 'in_parking')
                    
                    logger.info("="*50)
                    logger.info(f"✅ XE VÀO THÀNH CÔNG!")
                    logger.info(f"   BSX: {plate} | Slot: {slot_id} | Vé: {ticket_code}")
                    if is_booking:
                        logger.info(f"   Loại: BOOKING")
                    logger.info("="*50)
                    
                    # Clear pending
                    self.config.pending_entry = None
                else:
                    logger.info(f"🅿️ Slot {slot_id}: Có xe")
                    
            elif event == 'MONITOR_TIMEOUT':
                # Xe không vào slot - rollback
                pending = getattr(self.config, 'pending_entry', None)
                if pending:
                    logger.warning(f"⚠️ TIMEOUT: {pending['plate']} không vào slot - Hủy vé {pending['ticket_code']}")
                    # TODO: Có thể gọi API hủy vé ở đây
                    self.config.pending_entry = None
                    
            elif 'slot_status' in data:
                for slot in data['slot_status']:
                    slot_id = slot.get('id')
                    occupied = slot.get('occupied', False)
                    if slot_id:
                        self.gui.update_slot_status(slot_id, 'occupied' if occupied else 'empty')
                        
        except Exception as e:
            logger.error(f"Slot error: {e}")

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
                self.gui.update_emergency_status()
                if not self.config.gas_alert_sent:
                    gas_value = int(data.get('data', 0))
                    self.email.send_alert_email(gas_value, "Bãi đỗ xe XParking")
                    self.config.gas_alert_sent = True
                logger.warning(f"🚨 EMERGENCY: Smoke detected! Value: {data.get('data')}")
                    
            elif event == 'EMERGENCY_CLEAR':
                self.config.emergency_mode = False
                self.config.gas_alert_sent = False
                self.gui.update_emergency_status()
                self._display("in", "X-PARKING", "Entrance")
                self._display("out", "X-PARKING", "Exit")
                logger.info("✅ Emergency cleared")
        except Exception as e:
            logger.error(f"Alert handling error: {e}")

    # === ENTRY GATE 2 ===
    def handle_entry_gate2(self):
        """[GATE2] Flow xe vao"""
        if not self.gate2_entry_lock.acquire(blocking=False):
            logger.warning("[GATE2] Entry busy")
            return
        try:
            logger.info("="*50)
            logger.info("[GATE2] 🚗 XE VAO")
            logger.info("="*50)
            self._display("in", "NHAN DIEN", "VUI LONG CHO", gate=2)
            
            # Capture frame
            logger.info("[GATE2] 📷 Chup anh camera IN...")
            frame = None
            for attempt in range(3):
                frame = self.gui.capture_frame('in', gate=2)
                if frame is not None:
                    break
                time.sleep(0.3)
            
            if frame is None:
                self._display("in", "LOI CAMERA", "VUI LONG THU LAI", gate=2)
                return
            
            # Recognize plate
            plate = self._recognize_plate(frame)
            if not plate:
                self._display("in", "KHONG NHAN DIEN BSX", "VUI LONG THU LAI", gate=2)
                return
            
            logger.info(f"[GATE2] ✅ BSX: {plate}")
            self.gui.update_plate_display('in', plate)
            self._display("in", "DA NHAN DIEN", "DANG XU LY...", gate=2)
            
            # Get ticket
            ticket = self.ticket_manager.get_booking_ticket(plate)
            if not ticket:
                ticket = self.ticket_manager.create_walkin_ticket(plate)
            
            if not ticket:
                self._display("in", "BAI DAY", "VUI LONG THU LAI", gate=2)
                return
            
            logger.info(f"[GATE2] 🎫 Ve: {ticket.ticket_code}")
            
            # Open barrier
            self._barrier("in", "open", gate=2)
            self._display("in", "MOI VAO", "CHUC BAN AN TOAN", gate=2)
            
            # Wait for slot
            time.sleep(5)
            self._display("in", "X-PARKING", "GATE 2 IN", gate=2)
            
        except Exception as e:
            logger.error(f"[GATE2] Entry error: {e}")
            self._display("in", "LOI HE THONG", "VUI LONG THU LAI", gate=2)
        finally:
            self.gate2_entry_lock.release()
            logger.info("[GATE2] 🏁 Ket thuc xu ly xe vao\n")
    
    # === EXIT GATE 2 ===
    def handle_exit_gate2(self):
        """[GATE2] Flow xe ra voi xu ly song song"""
        if not self.gate2_exit_lock.acquire(blocking=False):
            logger.warning("[GATE2] Exit busy, skip")
            return
        
        start_flow = time.time()
        plate = None
        api_data = None
        qr_result = None
        
        try:
            logger.info("="*50)
            logger.info("[GATE2] 🚀 XE RA")
            logger.info("="*50)
            self._display("out", "NHAN DIEN BSX", "VUI LONG CHO...", gate=2)
            
            # Capture frame (ESP32-CAM)
            frame = None
            for attempt in range(3):
                frame = self.gui.capture_frame('out', gate=2)
                if frame is not None:
                    break
                time.sleep(0.3)
            
            if frame is None:
                self._display("out", "LOI CAMERA", "VUI LONG THU LAI", gate=2)
                return
            
            # Recognize plate
            plate = self._recognize_plate(frame)
            if not plate:
                self._display("out", "KHONG NHAN DIEN", "VUI LONG THU LAI", gate=2)
                return
            
            logger.info(f"[GATE2] ✅ BSX: {plate}")
            self.gui.update_plate_display('out', plate)
            self._display("out", "DA NHAN DIEN", "DANG XU LY...", gate=2)
            
            # Check cache
            api_data = ExitCacheManager.get(plate, gate=2)
            
            logger.info("[GATE2] ⚡ Bat dau xu ly SONG SONG...")
            
            # Setup QR scan
            self.config.waiting_for_qr_gate2 = True
            self.config.current_exit_plate_gate2 = plate
            self.config.qr_scan_result_gate2 = None
            
            futures = {}
            
            # API call if no cache
            if not api_data:
                futures['api'] = self.executor.submit(self._fetch_exit_data, plate)
                logger.info("[GATE2] 📡 API: Dang lay data...")
            else:
                logger.info(f"[GATE2] 📦 Cache HIT: {plate} → Skip API")
            
            # QR scan
            futures['qr'] = self.executor.submit(self._scan_qr_parallel_gate2)
            
            self._display("out", "SCAN VE", "DUA VE VAO CAM", gate=2)
            
            # Wait for both tasks
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
            
            # Verify data
            if not api_data or not api_data.get('found'):
                logger.error("[GATE2] Khong lay duoc data")
                self._display("out", "LOI DU LIEU", "VUI LONG THU LAI", gate=2)
                return
            
            if not qr_result:
                logger.error("[GATE2] Khong doc duoc QR")
                self._display("out", "KHONG DOC DUOC QR", "VUI LONG THU LAI", gate=2)
                return
            
            expected_ticket = api_data.get('ticket_code', '')
            if qr_result != expected_ticket:
                logger.warning(f"[GATE2] Ve khong khop: QR={qr_result} vs DB={expected_ticket}")
                self._display("out", "VE KHONG KHOP", "VUI LONG THU LAI", gate=2)
                return
            
            logger.info("[GATE2] ✅ Ve KHOP!")
            
            # Checkout
            self.db.checkout(plate, qr_result)
            ExitCacheManager.clear(gate=2)
            
            # Open barrier
            self._barrier("out", "open", gate=2)
            self._display("out", "TAM BIET", "HEN GAP LAI", gate=2)
            
            time.sleep(3)
            self._display("out", "X-PARKING", "GATE 2 OUT", gate=2)
            
        except Exception as e:
            logger.error(f"[GATE2] Exit error: {e}")
            self._display("out", "LOI HE THONG", "VUI LONG THU LAI", gate=2)
        finally:
            self.config.waiting_for_qr_gate2 = False
            self.config.current_exit_plate_gate2 = None
            self.config.qr_scan_result_gate2 = None
            self.gate2_exit_lock.release()
            elapsed = time.time() - start_flow
            logger.info(f"[GATE2] 🏁 Ket thuc xu ly xe ra ({elapsed:.1f}s)\n")
    
    def _scan_qr_parallel_gate2(self):
        """[GATE2] Scan QR qua MQTT"""
        SCAN_TIMEOUT = 25
        scan_start = time.time()
        
        for attempt in range(5):
            if time.time() - scan_start > SCAN_TIMEOUT:
                break
            
            if not self.config.waiting_for_qr_gate2:
                break
            
            self.config.qr_scan_result_gate2 = None
            
            # Trigger ESP32-CAM Gate2
            self._trigger_camera(gate=2)
            
            # Wait for response
            wait_start = time.time()
            while time.time() - wait_start < 5:
                if self.config.qr_scan_result_gate2:
                    return self.config.qr_scan_result_gate2
                time.sleep(0.1)
            
            if attempt < 4:
                logger.info(f"[GATE2] 📸 Retry QR ({attempt + 2}/5)")
                self._display("out", "SCAN LAI", f"LAN {attempt + 2}/5", gate=2)
                time.sleep(0.2)
        
        return None
    
    def _process_qr_from_bytes_gate2(self, jpeg_bytes):
        """[GATE2] Xu ly QR tu anh JPEG binary"""
        if not self.config.waiting_for_qr_gate2:
            return
        
        try:
            from qr_scanner import scan_qr_from_bytes, extract_ticket_code
            import cv2
            import numpy as np
            from datetime import datetime
            import os
            
            # Decode JPEG
            nparr = np.frombuffer(jpeg_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            if frame is None:
                logger.warning("[GATE2] Decode anh loi")
                return
            
            # Scan QR
            qr_content = scan_qr_from_bytes(jpeg_bytes)
            
            # Try grayscale
            if not qr_content:
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                from qr_scanner import scan_qr_from_frame
                qr_content = scan_qr_from_frame(gray)
            
            if not qr_content:
                logger.warning("[GATE2] ⚠️ QR khong nhan dien duoc")
                return
            
            ticket_code = extract_ticket_code(qr_content)
            if ticket_code:
                logger.info(f"[GATE2] ✅ QR: {ticket_code}")
                self.config.qr_scan_result_gate2 = ticket_code
                
                # Save image
                try:
                    tickets_dir = os.path.join(os.path.dirname(__file__), 'tickets_out_gate2')
                    os.makedirs(tickets_dir, exist_ok=True)
                    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                    filename = f"{ticket_code}_{timestamp}.jpg"
                    cv2.imwrite(os.path.join(tickets_dir, filename), frame)
                    logger.info(f"[GATE2] 💾 Luu anh ve: {filename}")
                except Exception as e:
                    logger.warning(f"[GATE2] Luu anh loi: {e}")
            else:
                logger.warning("[GATE2] QR khong hop le")
                
        except Exception as e:
            logger.error(f"[GATE2] Xu ly QR loi: {e}")

    def shutdown(self):
        logger.info("Shutting down...")
        self.mqtt_gate1.disconnect()
        self.mqtt_gate2.disconnect()
        self.executor.shutdown(wait=False)
