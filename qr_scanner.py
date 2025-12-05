"""
QR_SCANNER.PY - Scan QR code từ ảnh ESP32-CAM
"""
import re
import base64
import logging
from typing import Optional
from io import BytesIO

logger = logging.getLogger(__name__)

# Check dependencies
try:
    from PIL import Image
    from pyzbar import pyzbar
    PYZBAR_OK = True
except ImportError:
    PYZBAR_OK = False
    logger.warning("pyzbar not installed: pip install pyzbar Pillow")

try:
    import cv2
    import numpy as np
    CV2_OK = True
except ImportError:
    CV2_OK = False


def scan_qr_from_file(image_path: str) -> Optional[str]:
    """Scan QR từ file ảnh"""
    if not PYZBAR_OK:
        return None
    try:
        img = Image.open(image_path)
        decoded = pyzbar.decode(img)
        return decoded[0].data.decode('utf-8') if decoded else None
    except Exception as e:
        logger.error(f"scan_qr_from_file error: {e}")
        return None


def scan_qr_from_base64(base64_data: str) -> Optional[str]:
    """Scan QR từ base64 image (từ ESP32-CAM)"""
    if not PYZBAR_OK:
        return None
    try:
        # Remove header if present
        if ',' in base64_data:
            base64_data = base64_data.split(',')[1]
        
        img_bytes = base64.b64decode(base64_data)
        img = Image.open(BytesIO(img_bytes))
        
        decoded = pyzbar.decode(img)
        return decoded[0].data.decode('utf-8') if decoded else None
    except Exception as e:
        logger.error(f"scan_qr_from_base64 error: {e}")
        return None


def scan_qr_from_frame(frame) -> Optional[str]:
    """Scan QR từ OpenCV frame (numpy array)"""
    if not PYZBAR_OK:
        return None
    try:
        decoded = pyzbar.decode(frame)
        return decoded[0].data.decode('utf-8') if decoded else None
    except Exception as e:
        logger.error(f"scan_qr_from_frame error: {e}")
        return None


def scan_qr_from_bytes(jpeg_bytes: bytes) -> Optional[str]:
    """Scan QR từ raw JPEG bytes (từ ESP32-CAM MQTT)"""
    if not PYZBAR_OK:
        return None
    try:
        img = Image.open(BytesIO(jpeg_bytes))
        decoded = pyzbar.decode(img)
        return decoded[0].data.decode('utf-8') if decoded else None
    except Exception as e:
        logger.error(f"scan_qr_from_bytes error: {e}")
        return None


def extract_ticket_code(qr_content: str) -> Optional[str]:
    """
    Trích xuất mã vé từ nội dung QR
    
    Hỗ trợ:
    - URL: https://xxx/payment.php?ticket=VE1A2B3C4D
    - Raw: VE1A2B3C4D
    - Booking: BOOKS176474069314
    - Overstay: OVERSTAY1234567890123
    
    """
    if not qr_content:
        return None
    
    # Tìm trong URL parameter
    match = re.search(r'ticket=([A-Z0-9]+)', qr_content, re.IGNORECASE)
    if match:
        return match.group(1).upper()
    
    # Tìm pattern VE + 8 hex (ticket code)
    match = re.search(r'(VE[A-F0-9]{8})', qr_content, re.IGNORECASE)
    if match:
        return match.group(1).upper()
    
    # Tìm pattern BOOKS + số (booking payment code)
    match = re.search(r'(BOOKS\d{12,})', qr_content, re.IGNORECASE)
    if match:
        return match.group(1).upper()
    
    # Tìm pattern OVERSTAY + số (overstay payment code) - KHÔNG dấu -
    match = re.search(r'(OVERSTAY\d{10,})', qr_content, re.IGNORECASE)
    if match:
        return match.group(1).upper()
    
    return None


def save_base64_image(base64_data: str, output_path: str) -> bool:
    """Lưu ảnh base64 ra file"""
    try:
        if ',' in base64_data:
            base64_data = base64_data.split(',')[1]
        
        img_bytes = base64.b64decode(base64_data)
        with open(output_path, 'wb') as f:
            f.write(img_bytes)
        return True
    except Exception as e:
        logger.error(f"save_base64_image error: {e}")
        return False


# === TEST ===
if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 1:
        file_path = sys.argv[1]
        print(f"Scanning: {file_path}")
        
        content = scan_qr_from_file(file_path)
        print(f"QR Content: {content}")
        
        if content:
            ticket = extract_ticket_code(content)
            print(f"Ticket Code: {ticket}")
    else:
        print("\nFunctions available:")
