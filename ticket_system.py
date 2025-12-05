
import logging
from datetime import datetime, timedelta, timezone
from typing import Optional, Dict, Any
from enum import Enum

# VN Timezone (UTC+7)
VN_TZ = timezone(timedelta(hours=7))

logger = logging.getLogger('XParking.Ticket')


class TicketStatus(Enum):
    """Trạng thái vé"""
    ACTIVE = "ACTIVE"      # Vãng lai chưa thanh toán
    PAID = "PAID"          # Đã thanh toán
    USED = "USED"          # Đã sử dụng (xe đã ra)
    EXPIRED = "EXPIRED"    # Hết hạn (booking không vào đúng giờ)


class TicketType(Enum):
    """Loại vé"""
    WALK_IN = "walk_in"    # Xe vãng lai
    BOOKING = "booking"    # Xe đặt trước


class BaseTicket:
    """
    Base class cho tất cả loại vé
    Chứa logic chung cho cả Booking và Walk-in
    """
    
    def __init__(self, ticket_code: str, license_plate: str, time_in: datetime, 
                 db_api, qr_url: str = "", amount: int = 0):
        self.ticket_code = ticket_code
        self.license_plate = license_plate.upper().replace('-', '').replace(' ', '')
        self.time_in = time_in
        self.qr_url = qr_url
        self.amount = amount
        self.db = db_api
        
    def get_normalized_plate(self) -> str:
        return self.license_plate.replace('-', '').replace(' ', '').upper()
    
    def matches_plate(self, scanned_plate: str) -> bool:
        """Kiểm tra biển số có khớp không"""
        normalized_scanned = scanned_plate.replace('-', '').replace(' ', '').upper()
        return self.get_normalized_plate() == normalized_scanned
    
    def get_parking_duration(self) -> int:
        """Tính thời gian đỗ (phút) từ time_in đến hiện tại"""
        now = datetime.now(VN_TZ)
        delta = now - self.time_in
        return max(1, int(delta.total_seconds() / 60))
    
    def mark_as_used(self) -> bool:
        """Đánh dấu vé đã sử dụng"""
        try:
            result = self.db.use_ticket(self.ticket_code)
            if result and result.get('success'):
                logger.info(f"✅ Vé {self.ticket_code} đã được đánh dấu USED")
                return True
            return False
        except Exception as e:
            logger.error(f"❌ Lỗi mark_as_used: {e}")
            return False
    
    def verify_exit(self, scanned_plate: str) -> Dict[str, Any]:
        """
        Verify vé khi xe ra - Override trong subclass
        Return: dict với allow_exit, is_paid, error, amount_due, etc.
        """
        raise NotImplementedError("Subclass must implement verify_exit()")


class WalkInTicket(BaseTicket):
    """
    Vé vãng lai - Xe vào tự do, gen vé ngay, thanh toán khi ra
    """
    
    def __init__(self, ticket_code: str, license_plate: str, time_in: datetime, 
                 db_api, qr_url: str = "", amount: int = 0):
        super().__init__(ticket_code, license_plate, time_in, db_api, qr_url, amount)
        self.ticket_type = TicketType.WALK_IN
        
    def verify_exit(self, scanned_plate: str) -> Dict[str, Any]:
        """
        Verify vé vãng lai khi xe ra
        - Check BSX khớp
        - Check đã thanh toán chưa
        """
        logger.info(f"🎫 Verify Walk-in ticket: {self.ticket_code}")
        
        # 1. Check BSX
        if not self.matches_plate(scanned_plate):
            logger.warning(f"❌ BSX không khớp: Camera={scanned_plate} vs Vé={self.license_plate}")
            return {
                'success': False,
                'allow_exit': False,
                'plate_match': False,
                'is_paid': False,
                'error': f'BSX không khớp. Vé: {self.license_plate}',
                'expected_plate': self.license_plate,
                'scanned_plate': scanned_plate
            }
        
        # 2. Gọi API verify
        try:
            result = self.db.verify_ticket(self.ticket_code, scanned_plate)
            
            if not result:
                return {
                    'success': False,
                    'allow_exit': False,
                    'plate_match': True,
                    'is_paid': False,
                    'error': 'Không thể xác thực vé'
                }
            
            # 3. Check thanh toán
            if not result.get('is_paid', False):
                amount_due = result.get('amount_due', 0)
                logger.warning(f"⚠️ Chưa thanh toán: {amount_due:,}đ")
                return {
                    'success': False,
                    'allow_exit': False,
                    'plate_match': True,
                    'is_paid': False,
                    'error': f'Chưa thanh toán: {amount_due:,}đ',
                    'amount_due': amount_due,
                    'qr_url': result.get('qr_url', self.qr_url),
                    'license_plate': self.license_plate,
                    'ticket_code': self.ticket_code
                }
            
            # 4. OK - Cho phép ra
            logger.info(f"✅ Walk-in ticket verified: {self.ticket_code}")
            return {
                'success': True,
                'allow_exit': True,
                'plate_match': True,
                'is_paid': True,
                'paid_amount': result.get('paid_amount', 0),
                'license_plate': self.license_plate,
                'ticket_code': self.ticket_code
            }
            
        except Exception as e:
            logger.error(f"❌ Verify error: {e}")
            return {
                'success': False,
                'allow_exit': False,
                'plate_match': True,
                'is_paid': False,
                'error': f'Lỗi hệ thống: {str(e)}'
            }


class BookingTicket(BaseTicket):
    """
    Vé booking - Đặt trước, thanh toán trước, có time slot
    - Vào đúng giờ: OK
    - Vào lỗi giờ: Tính phí phát sinh
    - Không vào: Hết hạn
    """
    
    def __init__(self, ticket_code: str, license_plate: str, time_in: datetime, 
                 db_api, qr_url: str = "", amount: int = 0, booking_id: str = None,
                 start_time: datetime = None, end_time: datetime = None):
        super().__init__(ticket_code, license_plate, time_in, db_api, qr_url, amount)
        self.ticket_type = TicketType.BOOKING
        self.booking_id = booking_id
        self.start_time = start_time
        self.end_time = end_time
        
    def is_within_booking_time(self) -> bool:
        """Kiểm tra xe có vào trong khung giờ booking không"""
        if not self.start_time or not self.end_time:
            return False
        now = datetime.now(VN_TZ)
        return self.start_time <= now <= self.end_time
    
    def is_expired(self) -> bool:
        """Kiểm tra booking có hết hạn không (quá end_time mà xe chưa vào)"""
        if not self.end_time:
            return False
        now = datetime.now(VN_TZ)
        # Hết hạn nếu: đã quá end_time VÀ xe chưa vào bãi (không có time_in)
        return now > self.end_time and not self.time_in
    
    def get_overstay_minutes(self) -> int:
        """Tính số phút xe ở quá giờ booking"""
        if not self.end_time:
            return 0
        now = datetime.now(VN_TZ)
        if now <= self.end_time:
            return 0
        delta = now - self.end_time
        return int(delta.total_seconds() / 60)
    
    def verify_exit(self, scanned_plate: str) -> Dict[str, Any]:
        """
        Verify vé booking khi xe ra
        - Check BSX khớp
        - Check đã thanh toán chưa (luôn đã thanh toán trước)
        - Check quá giờ booking → Tính phí phát sinh
        """
        logger.info(f"🎫 Verify Booking ticket: {self.ticket_code}")
        
        # 1. Check BSX
        if not self.matches_plate(scanned_plate):
            logger.warning(f"❌ BSX không khớp: Camera={scanned_plate} vs Vé={self.license_plate}")
            return {
                'success': False,
                'allow_exit': False,
                'plate_match': False,
                'is_paid': True,  # Booking luôn đã thanh toán trước
                'is_booking': True,
                'error': f'BSX không khớp. Vé: {self.license_plate}',
                'expected_plate': self.license_plate,
                'scanned_plate': scanned_plate
            }
        
        # 2. Gọi API verify
        try:
            result = self.db.verify_ticket(self.ticket_code, scanned_plate)
            
            if not result:
                return {
                    'success': False,
                    'allow_exit': False,
                    'plate_match': True,
                    'is_paid': True,
                    'is_booking': True,
                    'error': 'Không thể xác thực vé'
                }
            
            # 3. Check phí phát sinh (overstay)
            if result.get('has_overstay', False):
                overstay_fee = result.get('overstay_fee', 0)
                overstay_mins = result.get('overstay_minutes', 0)
                logger.warning(f"⚠️ Booking quá giờ {overstay_mins}p - Phí: {overstay_fee:,}đ")
                return {
                    'success': False,
                    'allow_exit': False,
                    'plate_match': True,
                    'is_paid': True,
                    'is_booking': True,
                    'has_overstay': True,
                    'overstay_minutes': overstay_mins,
                    'overstay_fee': overstay_fee,
                    'error': f'Quá giờ {overstay_mins}p. Phí phát sinh: {overstay_fee:,}đ',
                    'amount_due': overstay_fee,
                    'qr_url': result.get('qr_url', self.qr_url),
                    'license_plate': self.license_plate,
                    'ticket_code': self.ticket_code
                }
            
            # 4. OK - Cho phép ra
            logger.info(f"✅ Booking ticket verified: {self.ticket_code}")
            return {
                'success': True,
                'allow_exit': True,
                'plate_match': True,
                'is_paid': True,
                'is_booking': True,
                'paid_amount': result.get('paid_amount', self.amount),
                'license_plate': self.license_plate,
                'ticket_code': self.ticket_code
            }
            
        except Exception as e:
            logger.error(f"❌ Verify error: {e}")
            return {
                'success': False,
                'allow_exit': False,
                'plate_match': True,
                'is_paid': True,
                'is_booking': True,
                'error': f'Lỗi hệ thống: {str(e)}'
            }


class TicketManager:
    """
    Manager class để xử lý tạo vé, verify vé cho cả Booking và Walk-in
    """
    
    def __init__(self, db_api):
        self.db = db_api
        
    def create_walk_in_ticket(self, license_plate: str) -> Optional[WalkInTicket]:
        """
        Tạo vé vãng lai mới
        """
        try:
            logger.info(f"📝 Tạo vé vãng lai cho: {license_plate}")
            result = self.db.create_ticket(license_plate)
            
            if not result or not result.get('success'):
                logger.error(f"❌ Lỗi tạo vé: {result}")
                return None
            
            ticket = WalkInTicket(
                ticket_code=result['ticket_code'],
                license_plate=license_plate,
                time_in=datetime.now(VN_TZ),
                db_api=self.db,
                qr_url=result.get('qr_url', ''),
                amount=0
            )
            
            logger.info(f"✅ Vé vãng lai: {ticket.ticket_code}")
            return ticket
            
        except Exception as e:
            logger.error(f"❌ Lỗi tạo vé vãng lai: {e}")
            return None
    
    def get_booking_ticket(self, license_plate: str) -> Optional[BookingTicket]:
        """
        Lấy vé booking có sẵn cho biển số này
        """
        try:
            logger.info(f"🔍 Tìm booking cho: {license_plate}")
            booking = self.db.check_booking(license_plate)
            
            if not booking.get('has_booking'):
                logger.info("ℹ️ Không có booking")
                return None
            
            if not booking.get('ticket_code'):
                logger.warning("⚠️ Booking chưa có vé")
                return None
            
            # Parse time
            start_time = None
            end_time = None
            if booking.get('start_time'):
                start_time = datetime.fromisoformat(booking['start_time'].replace('Z', '+00:00'))
            if booking.get('end_time'):
                end_time = datetime.fromisoformat(booking['end_time'].replace('Z', '+00:00'))
            
            ticket = BookingTicket(
                ticket_code=booking['ticket_code'],
                license_plate=license_plate,
                time_in=datetime.now(VN_TZ),  # Sẽ update khi xe vào
                db_api=self.db,
                qr_url=booking.get('qr_url', ''),
                amount=0,  # Đã thanh toán trước
                booking_id=booking.get('booking_id'),
                start_time=start_time,
                end_time=end_time
            )
            
            logger.info(f"✅ Booking ticket: {ticket.ticket_code}")
            return ticket
            
        except Exception as e:
            logger.error(f"❌ Lỗi lấy booking ticket: {e}")
            return None
    
    def get_ticket_for_exit(self, ticket_code: str) -> Optional[BaseTicket]:
        """
        Lấy thông tin vé khi xe ra (có thể là booking hoặc walk-in)
        """
        try:
            logger.info(f"🔍 Lấy thông tin vé: {ticket_code}")
            result = self.db.get_ticket(ticket_code)
            
            if not result or not result.get('success'):
                logger.error(f"❌ Không tìm thấy vé: {ticket_code}")
                return None
            
            ticket_data = result.get('ticket', {})
            
            # Parse time_in
            time_in_str = ticket_data.get('time_in')
            time_in = datetime.now(VN_TZ)
            if time_in_str:
                try:
                    time_in = datetime.fromisoformat(time_in_str.replace('Z', '+00:00'))
                    # Convert to VN timezone if needed
                    if time_in.tzinfo is None:
                        time_in = time_in.replace(tzinfo=VN_TZ)
                except:
                    pass
            
            # Kiểm tra có booking_id không
            booking_id = ticket_data.get('booking_id')
            
            if booking_id:
                # Vé booking - Cần lấy thêm thông tin booking
                booking_info = self.db.get_booking_by_id(booking_id) if hasattr(self.db, 'get_booking_by_id') else {}
                
                start_time = None
                end_time = None
                if booking_info:
                    if booking_info.get('start_time'):
                        start_time = datetime.fromisoformat(booking_info['start_time'].replace('Z', '+00:00'))
                    if booking_info.get('end_time'):
                        end_time = datetime.fromisoformat(booking_info['end_time'].replace('Z', '+00:00'))
                
                ticket = BookingTicket(
                    ticket_code=ticket_code,
                    license_plate=ticket_data.get('license_plate', ''),
                    time_in=time_in,
                    db_api=self.db,
                    qr_url=ticket_data.get('qr_url', ''),
                    amount=ticket_data.get('amount', 0),
                    booking_id=booking_id,
                    start_time=start_time,
                    end_time=end_time
                )
                logger.info(f"✅ Booking ticket loaded: {ticket_code}")
                return ticket
            else:
                # Vé vãng lai
                ticket = WalkInTicket(
                    ticket_code=ticket_code,
                    license_plate=ticket_data.get('license_plate', ''),
                    time_in=time_in,
                    db_api=self.db,
                    qr_url=ticket_data.get('qr_url', ''),
                    amount=ticket_data.get('amount', 0)
                )
                logger.info(f"✅ Walk-in ticket loaded: {ticket_code}")
                return ticket
                
        except Exception as e:
            logger.error(f"❌ Lỗi lấy vé: {e}")
            import traceback
            traceback.print_exc()
            return None

