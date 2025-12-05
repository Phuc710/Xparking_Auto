"""
IMAGE_UPLOADER.PY - Tối ưu chụp, nén và upload ảnh lên server
Sử dụng cho: Ảnh xe vào, ảnh xe ra, ảnh vé
"""
import cv2
import base64
import requests
import logging
import numpy as np
from datetime import datetime

logger = logging.getLogger('XParking.ImageUploader')

class ImageUploader:
    def __init__(self, site_url, max_width=640, quality=60):
        """
        Khởi tạo ImageUploader với tối ưu hóa
        
        Args:
            site_url: URL của server
            max_width: Chiều rộng tối đa (default 640px - tối ưu cho tốc độ)
            quality: Chất lượng JPEG 1-100 (default 60 - tối ưu size/chất lượng)
        """
        self.upload_url = f"{site_url}/api/upload_image.php"
        self.max_width = max_width
        self.quality = quality
        
        # Tối ưu session với connection pooling
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'User-Agent': 'XParking-ImageUploader/2.0',
            'Accept': 'application/json',
            'Connection': 'keep-alive'
        })
        
        # Connection pool để tái sử dụng
        adapter = requests.adapters.HTTPAdapter(
            pool_connections=2,
            pool_maxsize=5,
            max_retries=2
        )
        self.session.mount('http://', adapter)
        self.session.mount('https://', adapter)
        
    def _optimize_frame(self, frame):
        """
        Tối ưu frame trước khi upload:
        - Resize thông minh
        - Giảm noise
        - Tối ưu màu sắc
        """
        try:
            if frame is None:
                return None
                
            height, width = frame.shape[:2]
            
            # Resize nếu cần thiết
            if width > self.max_width:
                ratio = self.max_width / width
                new_height = int(height * ratio)
                frame = cv2.resize(frame, (self.max_width, new_height), interpolation=cv2.INTER_AREA)
            
            # Giảm noise nhẹ để compress tốt hơn
            frame = cv2.bilateralFilter(frame, 5, 50, 50)
            
            # Điều chỉnh độ sáng/tương phản cho ảnh xe
            lab = cv2.cvtColor(frame, cv2.COLOR_BGR2LAB)
            l, a, b = cv2.split(lab)
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(4,4))
            l = clahe.apply(l)
            frame = cv2.merge([l, a, b])
            frame = cv2.cvtColor(frame, cv2.COLOR_LAB2BGR)
            
            return frame
            
        except Exception as e:
            logger.warning(f"Frame optimization failed: {e}")
            return frame  # Return original nếu tối ưu thất bại
    
    def capture_and_upload(self, frame, ticket_code, image_type='entry'):
        """
        Chụp và upload ảnh với tối ưu hóa tối đa
        
        Args:
            frame: OpenCV frame
            ticket_code: Mã vé (VE12345678)
            image_type: entry|exit|ticket
            
        Returns:
            dict: {success: bool, path: str, size_kb: float, error: str}
        """
        start_time = datetime.now()
        
        try:
            if frame is None:
                logger.error("Frame is None")
                return {'success': False, 'error': 'No frame provided'}
            
            # Validate ticket_code format
            ticket_code = ticket_code.upper().strip()
            if not ticket_code.startswith('VE') or len(ticket_code) != 10:
                return {'success': False, 'error': 'Invalid ticket code format'}
            
            # Validate image_type
            if image_type not in ['entry', 'exit', 'ticket']:
                return {'success': False, 'error': 'Invalid image type'}
            
            # Tối ưu frame trước khi encode
            optimized_frame = self._optimize_frame(frame)
            if optimized_frame is None:
                return {'success': False, 'error': 'Frame optimization failed'}
            
            # Encode với tham số tối ưu
            encode_params = [
                cv2.IMWRITE_JPEG_QUALITY, self.quality,
                cv2.IMWRITE_JPEG_PROGRESSIVE, 1,  # Progressive JPEG
                cv2.IMWRITE_JPEG_OPTIMIZE, 1      # Tối ưu thêm
            ]
            
            success, buffer = cv2.imencode('.jpg', optimized_frame, encode_params)
            
            if not success:
                logger.error("Failed to encode optimized frame")
                return {'success': False, 'error': 'Image encoding failed'}
            
            # Kiểm tra kích thước sau khi nén
            buffer_size = len(buffer)
            if buffer_size > 500 * 1024:  # > 500KB
                logger.warning(f"Large image size: {buffer_size/1024:.1f}KB")
            
            # Convert to base64 (tối ưu memory)
            image_b64 = base64.b64encode(buffer.tobytes()).decode('utf-8')
            
            # Payload tối ưu
            payload = {
                'type': image_type,
                'ticket_code': ticket_code,
                'image': image_b64
            }
            
            # Upload với timeout ngắn hơn cho responsive
            response = self.session.post(
                self.upload_url,
                json=payload,
                timeout=8,  # Giảm từ 10s xuống 8s
                stream=False
            )
            
            # Xử lý response
            if response.status_code == 200:
                try:
                    result = response.json()
                    if result.get('success'):
                        elapsed = (datetime.now() - start_time).total_seconds()
                        size_kb = result['data']['size_kb']
                        
                        logger.info(f"Gửi {image_type}: {size_kb}KB")
                        
                        return {
                            'success': True,
                            'path': result['data']['path'],
                            'size_kb': size_kb,
                            'upload_time': elapsed,
                            'original_size': buffer_size / 1024
                        }
                    else:
                        error_msg = result.get('error', 'Unknown server error')
                        logger.error(f"Server error: {error_msg}")
                        return {'success': False, 'error': error_msg}
                        
                except ValueError as e:
                    logger.error(f"JSON decode error: {e}")
                    return {'success': False, 'error': 'Invalid server response'}
                    
            else:
                logger.error(f"HTTP {response.status_code}: {response.text[:100]}")
                return {'success': False, 'error': f'Server returned {response.status_code}'}
                
        except requests.exceptions.Timeout:
            logger.error("Upload timeout (8s)")
            return {'success': False, 'error': 'Upload timeout - check network'}
            
        except requests.exceptions.ConnectionError:
            logger.error("Connection error")
            return {'success': False, 'error': 'Cannot connect to server'}
            
        except Exception as e:
            logger.error(f"Unexpected upload error: {e}")
            return {'success': False, 'error': f'Upload failed: {str(e)}'}
    
    def upload_from_file(self, filepath, ticket_code, image_type='entry'):
        """
        Upload ảnh từ file với validation
        
        Args:
            filepath: Đường dẫn file ảnh
            ticket_code: Mã vé
            image_type: Loại ảnh
            
        Returns:
            dict: Kết quả upload
        """
        try:
            # Đọc file với error handling
            frame = cv2.imread(filepath, cv2.IMREAD_COLOR)
            if frame is None:
                return {'success': False, 'error': f'Cannot read file: {filepath}'}
                
            logger.info(f"Đọc file: {filepath}")
            return self.capture_and_upload(frame, ticket_code, image_type)
            
        except Exception as e:
            logger.error(f"File upload error: {e}")
            return {'success': False, 'error': f'File error: {str(e)}'}
    
    def test_connection(self):
        """Test kết nối đến server"""
        start_time = datetime.now()
        try:
            # Test trực tiếp với OPTIONS request (tránh 403)
            response = self.session.options(self.upload_url, timeout=5)
            elapsed = (datetime.now() - start_time).total_seconds()
            
            if response.status_code in [200, 204]:
                return {'success': True, 'response_time': elapsed}
            else:
                return {'success': False, 'error': f'Server {response.status_code}'}
                
        except Exception as e:
            elapsed = (datetime.now() - start_time).total_seconds()
            return {'success': False, 'error': str(e), 'response_time': elapsed}
    
    def close(self):
        """Đóng session để giải phóng tài nguyên"""
        if hasattr(self, 'session'):
            self.session.close()
            logger.debug("Session closed")
    
    def __enter__(self):
        return self
        
    def __exit__(self, exc_type, exc_val, exc_tb):
        self.close()
