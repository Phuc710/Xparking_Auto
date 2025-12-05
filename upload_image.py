"""
UPLOAD IMAGE - Production ready
Sử dụng: from upload_image import upload_xe_vao, upload_xe_ra, upload_ticket
"""
import cv2
import logging
from image_uploader import ImageUploader

# Config production
SERVER_URL = "https://xparking.elementfx.com"
logger = logging.getLogger('XParking')

def upload_xe_vao(frame, ticket_code):
    """Upload ảnh xe vào"""
    try:
        with ImageUploader(SERVER_URL) as uploader:
            result = uploader.capture_and_upload(frame, ticket_code, 'entry')
            if result['success']:
                logger.info(f"Xe vào: {ticket_code}")
                return result['path']
            else:
                logger.error(f"Lỗi xe vào: {result['error']}")
                return None
    except Exception as e:
        logger.error(f"Lỗi hệ thống: {e}")
        return None

def upload_xe_ra(frame, ticket_code):
    """Upload ảnh xe ra"""
    try:
        with ImageUploader(SERVER_URL) as uploader:
            result = uploader.capture_and_upload(frame, ticket_code, 'exit')
            if result['success']:
                logger.info(f"Xe ra: {ticket_code}")
                return result['path']
            else:
                logger.error(f"Lỗi xe ra: {result['error']}")
                return None
    except Exception as e:
        logger.error(f"Lỗi hệ thống: {e}")
        return None

def upload_ticket(frame, ticket_code):
    """Upload ảnh vé"""
    try:
        with ImageUploader(SERVER_URL) as uploader:
            result = uploader.capture_and_upload(frame, ticket_code, 'ticket')
            if result['success']:
                logger.info(f"Vé: {ticket_code}")
                return result['path']
            else:
                logger.error(f"Lỗi vé: {result['error']}")
                return None
    except Exception as e:
        logger.error(f"Lỗi hệ thống: {e}")
        return None

# Ví dụ sử dụng
if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(message)s')
    
    # Chụp từ camera
    cap = cv2.VideoCapture(0)
    ret, frame = cap.read()
    cap.release()
    
    if ret:
        # Upload ảnh xe vào
        path = upload_xe_vao(frame, "VE12345678")
        if path:
            print(f"✅ Đã lưu: {path}")
        else:
            print("❌ Lỗi upload")
    else:
        print("❌ Không đọc được camera")
