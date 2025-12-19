import logging
from datetime import datetime

logger = logging.getLogger('XParking')

class WalkInTicket:
    def __init__(self, ticket_code, plate, qr_url=""):
        self.ticket_code = ticket_code
        self.plate = plate
        self.qr_url = qr_url
        self.created_at = datetime.now()

class BookingTicket:
    def __init__(self, ticket_code, plate, qr_url=""):
        self.ticket_code = ticket_code
        self.plate = plate
        self.qr_url = qr_url

class TicketManager:
    def __init__(self, db_api):
        self.db = db_api
    
    def get_booking_ticket(self, plate):
        """Kiểm tra xe có booking không"""
        try:
            result = self.db.check_booking(plate)
            if result and result.get('found'):
                return BookingTicket(
                    result.get('ticket_code'),
                    plate,
                    result.get('qr_url', '')
                )
        except:
            pass
        return None
    
    def create_walk_in_ticket(self, plate):
        """Tạo vé vãng lai"""
        try:
            result = self.db.create_ticket(plate)
            if result and result.get('success'):
                return WalkInTicket(
                    result.get('ticket_code'),
                    plate,
                    result.get('qr_url', '')
                )
        except Exception as e:
            logger.error(f"Create ticket error: {e}")
        return None
    
    def create_walkin_ticket(self, plate):
        """Alias"""
        return self.create_walk_in_ticket(plate)
