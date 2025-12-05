"""
DB_API.PY - API Client cho XPARKING
Gọi PHP Gateway → MySQL Database
Tích hợp Auto Bypass InfinityFree Anti-Bot
"""
import requests
import logging
import re
from datetime import datetime, timezone, timedelta

# AES decrypt for bypass
try:
    from Crypto.Cipher import AES
    AES_AVAILABLE = True
except ImportError:
    AES_AVAILABLE = False

VN_TZ = timezone(timedelta(hours=7))
logger = logging.getLogger('XParking.API')


def bypass_infinityfree(session, url):
    """
    Tự động bypass AES Challenge của InfinityFree.
    Giải mã AES-CBC để lấy cookie __test.
    """
    if not AES_AVAILABLE:
        logger.warning("pycryptodome chưa cài - không thể auto bypass")
        return None
    
    try:
        r = session.get(url, timeout=15)
        
        # Không bị chặn
        if 'toNumbers' not in r.text:
            return "OK"
        
        # Tìm 3 chuỗi hex
        matches = re.findall(r'toNumbers\("([a-f0-9]{32})"\)', r.text)
        if len(matches) < 3:
            logger.error(f"Không tìm đủ 3 chuỗi hex (có {len(matches)})")
            return None
        
        # Giải mã AES
        key = bytes.fromhex(matches[0])
        iv = bytes.fromhex(matches[1])
        ciphertext = bytes.fromhex(matches[2])
        
        cipher = AES.new(key, AES.MODE_CBC, iv)
        cookie = cipher.decrypt(ciphertext).hex()
        
        logger.debug(f"Bypass OK")
        return cookie
        
    except Exception as e:
        logger.error(f"Lỗi bypass: {e}")
        return None

class DatabaseAPI:
    def __init__(self, config, auto_connect=True):
        self.config = config
        self.site_url = config['site_url']
        self.gateway_url = f"{self.site_url}/api/gateway.php"
        self.domain = self.site_url.split('//')[-1].split('/')[0]
        self.connected = False
        
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'application/json, text/html, */*',
        })
        
        if auto_connect:
            self.connect()
    
    def connect(self):
        """Kết nối và bypass anti-bot nếu cần"""
        cookie = bypass_infinityfree(self.session, self.site_url)
        
        if cookie == "OK":
            self.connected = True
            logger.info("✅ API: Kết nối OK")
            return True
        elif cookie:
            self.session.cookies.set('__test', cookie, domain=self.domain)
            try:
                r = self.session.get(self.site_url, timeout=10)
                if 'toNumbers' not in r.text:
                    self.connected = True
                    logger.info("✅ API: Kết nối OK (bypass)")
                    return True
            except:
                pass
        
        logger.error("❌ API: Kết nối thất bại")
        self.connected = False
        return False
    
    def _call(self, action, params=None):
        """Gọi API gateway"""
        try:
            data = {'action': action}
            if params:
                data.update(params)
            r = self.session.get(self.gateway_url, params=data, timeout=10)
            return r.json() if r.status_code == 200 else None
        except Exception as e:
            logger.error(f"API error: {e}")
            return None
    
    def now(self):
        return datetime.now(VN_TZ).strftime('%Y-%m-%d %H:%M:%S')

    # === TICKET ===
    def create_ticket(self, license_plate):
        """Tạo vé mới cho xe vào"""
        return self._call('create_ticket', {'license_plate': license_plate})
    
    def get_ticket(self, ticket_code):
        """Lấy thông tin vé"""
        return self._call('get_ticket', {'ticket_code': ticket_code})
    
    def verify_ticket(self, ticket_code, license_plate=''):
        """Xác thực vé khi xe ra"""
        return self._call('verify_ticket', {
            'ticket_code': ticket_code,
            'license_plate': license_plate
        })
    
    def use_ticket(self, ticket_code):
        """Đánh dấu vé đã dùng"""
        return self._call('use_ticket', {'ticket_code': ticket_code})

    # === BOOKING ===
    def check_booking(self, license_plate):
        """Kiểm tra có booking không"""
        r = self._call('check_booking', {'license_plate': license_plate})
        return r if r else {'has_booking': False}
    
    def get_booking(self, license_plate):
        """Lấy thông tin booking"""
        r = self._call('get_booking', {'license_plate': license_plate})
        return r.get('booking') if r and r.get('success') else None
    
    def update_booking(self, booking_id, status):
        """Cập nhật trạng thái booking"""
        return self._call('update_booking', {'booking_id': booking_id, 'status': status})
    
    def get_booking_by_id(self, booking_id):
        """Lấy thông tin booking theo ID"""
        r = self._call('get_booking_by_id', {'booking_id': booking_id})
        return r.get('booking') if r and r.get('success') else None

    # === VEHICLE ===
    def checkin(self, license_plate, slot_id, ticket_code):
        """Ghi nhận xe vào bãi"""
        return self._call('checkin', {
            'license_plate': license_plate,
            'slot_id': slot_id,
            'ticket_code': ticket_code
        })
    
    def checkout(self, ticket_code, license_plate=''):
        """Xử lý xe ra"""
        return self._call('checkout', {
            'ticket_code': ticket_code,
            'license_plate': license_plate
        })
    
    def get_vehicle_by_plate(self, license_plate):
        """Tìm xe theo biển số"""
        r = self._call('get_vehicle_by_plate', {'license_plate': license_plate})
        return r.get('vehicle') if r and r.get('success') else None
    
    def verify_exit_full(self, license_plate):
        """
        [OPTIMIZED] Lấy TOÀN BỘ data xe ra trong 1 API call.
        Returns: {found, ticket_code, status, amount, allow_exit, ...}
        """
        r = self._call('verify_exit_full', {'license_plate': license_plate})
        if r and r.get('success'):
            return r
        return {'found': False, 'error': 'API_ERROR'}

    # === SLOTS ===
    def get_slots(self):
        """Lấy trạng thái các slot"""
        r = self._call('get_slots')
        return r.get('slots', []) if r and r.get('success') else []
    
    def get_available_slots(self):
        """Lấy các slot trống"""
        slots = self.get_slots()
        return [s['id'] for s in slots if s.get('status') == 'empty']
    
    def update_slot(self, slot_id, status):
        """Cập nhật trạng thái slot"""
        return self._call('update_slot', {'slot_id': slot_id, 'status': status})
