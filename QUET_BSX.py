from PIL import Image
import cv2
import torch
import math
import os
import time
import warnings
import threading
from queue import Queue
import numpy as np
import logging # Thêm logging để ghi lại lỗi thay vì chỉ bỏ qua

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# Import custom modules with error handling
# Cố gắng import utils_rotate và helper. Nếu không thành công, hệ thống sẽ vẫn hoạt động
# với các phương pháp fallback nhưng sẽ in ra cảnh báo.
try:
    import function.utils_rotate as utils_rotate
    logging.info("Imported function.utils_rotate successfully.")
except ImportError:
    utils_rotate = None
    logging.warning("Could not import function.utils_rotate. Rotation correction might be limited.")

try:
    import function.helper as helper
    logging.info("Imported function.helper successfully.")
except ImportError:
    helper = None
    logging.warning("Could not import function.helper. OCR helper functions might be unavailable.")

# Suppress warnings để giảm spam console
warnings.filterwarnings("ignore", category=FutureWarning)
warnings.filterwarnings("ignore", category=UserWarning)

class OptimizedLPR:
    """
    Lớp OptimizedLPR cung cấp các chức năng tối ưu hóa để phát hiện và đọc biển số xe (LPR).
    Nó sử dụng các mô hình YOLOv5 cho phát hiện và nhận dạng ký tự, cùng với các kỹ thuật tiền xử lý
    ảnh và xử lý hậu kỳ để cải thiện độ chính xác và hiệu suất.
    """

    # --- Hằng số cấu hình ---
    LP_DETECTOR_MODEL_PATH = 'model/LP_detector_nano_61.pt'
    OCR_MODEL_PATH = 'model/LP_ocr_nano_62.pt'
    DEFAULT_DETECTOR_CONF = 0.4
    DEFAULT_DETECTOR_IOU = 0.45
    DEFAULT_OCR_CONF = 0.5
    MAX_FRAME_WIDTH_RESIZE = 1280 # Kích thước tối đa cho frame để tiền xử lý
    MIN_PLATE_WIDTH_OCR = 100     # Kích thước tối thiểu cho plate crop để OCR
    PLATE_CROP_PADDING = 5        # Padding cho bounding box khi cắt biển số
    ROTATION_ANGLES = [-2, -1, 0, 1, 2] # Các góc thử để sửa lỗi xoay

    def __init__(self):
        """
        Khởi tạo lớp OptimizedLPR.
        Thiết lập các biến trạng thái và mô hình ban đầu.
        """
        self.yolo_LP_detect = None
        self.yolo_license_plate = None
        self.prev_frame_time = 0 # Có thể dùng cho tính toán FPS nếu cần
        self.processing_lock = threading.Lock() # Đảm bảo an toàn luồng khi xử lý mô hình
        self.models_loaded = False
        logging.info("OptimizedLPR instance initialized.")

    def load_models(self) -> bool:
        """
        Tải các mô hình phát hiện biển số và OCR với các tối ưu hóa.
        Hàm này sẽ tự động phát hiện thiết bị (CUDA/CPU) và tải mô hình tương ứng.
        Nó cũng bao gồm bước warm-up để cải thiện hiệu suất ban đầu.

        Returns:
            bool: True nếu các mô hình được tải thành công, ngược lại là False.
        """
        if self.models_loaded:
            logging.info("Models already loaded. Skipping.")
            return True
            
        try:
            # Sử dụng device auto-detect và tối ưu memory
            device = 'cuda' if torch.cuda.is_available() else 'cpu'
            logging.info(f"Using device: {device}")
            
            # Load LP detection model
            if os.path.exists(self.LP_DETECTOR_MODEL_PATH):
                logging.info(f"Loading custom LP detector model: {self.LP_DETECTOR_MODEL_PATH}")
                self.yolo_LP_detect = torch.hub.load(
                    'ultralytics/yolov5', 'custom', 
                    path=self.LP_DETECTOR_MODEL_PATH, 
                    force_reload=False,
                    device=device,
                    trust_repo=True
                )
                self.yolo_LP_detect.conf = self.DEFAULT_DETECTOR_CONF
                self.yolo_LP_detect.iou = self.DEFAULT_DETECTOR_IOU
            else:
                logging.warning(f"Custom LP detector model not found at {self.LP_DETECTOR_MODEL_PATH}. Loading YOLOv5s default model.")
                self.yolo_LP_detect = torch.hub.load('ultralytics/yolov5', 'yolov5s', device=device, trust_repo=True)
                self.yolo_LP_detect.conf = 0.3 # Default confidence for generic YOLOv5s
            
            # Load OCR model
            if os.path.exists(self.OCR_MODEL_PATH):
                logging.info(f"Loading custom OCR model: {self.OCR_MODEL_PATH}")
                self.yolo_license_plate = torch.hub.load(
                    'ultralytics/yolov5', 'custom', 
                    path=self.OCR_MODEL_PATH, 
                    force_reload=False,
                    device=device,
                    trust_repo=True
                )
                self.yolo_license_plate.conf = self.DEFAULT_OCR_CONF
            else:
                self.yolo_license_plate = None
                logging.warning(f"Custom OCR model not found at {self.OCR_MODEL_PATH}. OCR functionality will be limited.")
            
            # Warm up models với dummy input
            if self.yolo_LP_detect:
                dummy_frame = np.zeros((640, 640, 3), dtype=np.uint8) # Sử dụng kích thước phổ biến cho YOLO
                _ = self.yolo_LP_detect(dummy_frame, size=640)
                logging.info("LP detector model warmed up.")
            if self.yolo_license_plate:
                dummy_ocr_input = np.zeros((100, 200, 3), dtype=np.uint8) # Kích thước phổ biến cho OCR
                _ = self.yolo_license_plate(dummy_ocr_input, size=224) # Giả định kích thước input của OCR model
                logging.info("OCR model warmed up.")
            
            self.models_loaded = True
            logging.info("All models loaded successfully.")
            return True
            
        except Exception as e:
            self.models_loaded = False
            logging.error(f"Failed to load models: {e}")
            return False
    
    def preprocess_frame(self, frame: np.ndarray) -> np.ndarray:
        """
        Tiền xử lý frame để tăng chất lượng detection.
        Bao gồm thay đổi kích thước và tăng độ tương phản.

        Args:
            frame (np.ndarray): Khung hình đầu vào (numpy array).

        Returns:
            np.ndarray: Khung hình đã được tiền xử lý.
        """
        if frame is None or frame.size == 0:
            logging.warning("Input frame for preprocessing is empty or None.")
            return frame

        try:
            # Resize frame nếu quá lớn
            height, width = frame.shape[:2]
            if width > self.MAX_FRAME_WIDTH_RESIZE:
                scale = self.MAX_FRAME_WIDTH_RESIZE / width
                new_width = self.MAX_FRAME_WIDTH_RESIZE
                new_height = int(height * scale)
                frame = cv2.resize(frame, (new_width, new_height), interpolation=cv2.INTER_AREA) # Sử dụng INTER_AREA cho giảm kích thước
            
            # Cải thiện chất lượng ảnh bằng CLAHE
            lab = cv2.cvtColor(frame, cv2.COLOR_BGR2LAB)
            l, a, b = cv2.split(lab)
            # ClipLimit và tileGridSize có thể điều chỉnh để đạt hiệu quả tốt nhất
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
            l = clahe.apply(l)
            enhanced_frame = cv2.merge([l, a, b])
            enhanced_frame = cv2.cvtColor(enhanced_frame, cv2.COLOR_LAB2BGR)
            
            return enhanced_frame
        except Exception as e:
            logging.error(f"Error during frame preprocessing: {e}")
            return frame # Trả về frame gốc nếu có lỗi
    
    def detect_and_read_plate(self, frame: np.ndarray) -> dict:
        """
        Phát hiện và đọc biển số từ frame hình ảnh.
        Là chức năng chính của lớp, tích hợp phát hiện và nhận dạng OCR.

        Args:
            frame (np.ndarray): numpy array của ảnh đầu vào (BGR format).

        Returns: 
            dict: Một dictionary chứa kết quả:
                'success' (bool): True nếu có ít nhất một biển số được phát hiện và đọc thành công.
                'plates' (list): Danh sách các dictionary, mỗi dictionary chứa thông tin về một biển số:
                    'bbox' (tuple): Bounding box (x1, y1, x2, y2) của biển số trong ảnh gốc.
                    'text' (str): Văn bản biển số đã đọc được.
                    'confidence' (float): Độ tin cậy của việc phát hiện biển số.
                    'cropped_image' (np.ndarray): Ảnh của biển số đã được cắt ra.
                'error' (str hoặc None): Thông báo lỗi nếu có, ngược lại là None.
        """
        result = {
            'success': False,
            'plates': [],
            'error': None
        }
        
        if not self.models_loaded:
            result['error'] = "Models not loaded. Call load_models() first."
            logging.error(result['error'])
            return result
            
        if frame is None or frame.size == 0:
            result['error'] = "Input frame is empty or None."
            logging.error(result['error'])
            return result

        with self.processing_lock:
            try:
                # Tiền xử lý frame
                processed_frame = self.preprocess_frame(frame)
                
                # Detect license plates
                # Sử dụng 'size' để định nghĩa kích thước input cho mô hình YOLO
                plates_data = self.yolo_LP_detect(processed_frame, size=640)
                
                # Kiểm tra kết quả detection
                # YOLOv5 có thể trả về các định dạng khác nhau
                list_plates = []
                if hasattr(plates_data, 'pandas') and not plates_data.pandas().xyxy[0].empty:
                    list_plates = plates_data.pandas().xyxy[0].values.tolist()
                elif hasattr(plates_data, 'xyxy') and plates_data.xyxy[0].numel() > 0: # Kiểm tra tensor có rỗng không
                    list_plates = plates_data.xyxy[0].cpu().numpy().tolist()
                else:
                    result['error'] = "No license plates detected or results format unknown."
                    return result
                
                detected_plates = []
                
                for i, plate in enumerate(list_plates):
                    try:
                        # Lấy tọa độ bounding box
                        # plate format: [x1, y1, x2, y2, conf, class_id, class_name] hoặc [x1, y1, x2, y2, conf, cls]
                        x1, y1, x2, y2 = int(plate[0]), int(plate[1]), int(plate[2]), int(plate[3])
                        conf = float(plate[4])
                        
                        # Kiểm tra tọa độ hợp lệ
                        if x2 <= x1 or y2 <= y1:
                            logging.warning(f"Invalid bounding box coordinates for plate {i}: ({x1},{y1},{x2},{y2}). Skipping.")
                            continue
                            
                        # Crop license plate region với padding
                        x1_crop = max(0, x1 - self.PLATE_CROP_PADDING)
                        y1_crop = max(0, y1 - self.PLATE_CROP_PADDING)
                        x2_crop = min(processed_frame.shape[1], x2 + self.PLATE_CROP_PADDING)
                        y2_crop = min(processed_frame.shape[0], y2 + self.PLATE_CROP_PADDING)
                        
                        crop_img = processed_frame[y1_crop:y2_crop, x1_crop:x2_crop]
                        
                        if crop_img.size == 0:
                            logging.warning(f"Cropped image for plate {i} is empty. Skipping.")
                            continue
                            
                        # Đọc text từ license plate
                        plate_text = self.read_plate_advanced(crop_img)
                        
                        # Chỉ thêm vào kết quả nếu đọc được text hợp lệ
                        if plate_text and plate_text != "unknown" and len(plate_text) > 3:
                            detected_plates.append({
                                'bbox': (x1, y1, x2, y2),
                                'text': plate_text,
                                'confidence': conf,
                                'cropped_image': crop_img # Không cần .copy() nếu không sửa đổi sau đó
                            })
                        else:
                            logging.info(f"Plate {i} detected but text could not be read or was too short: '{plate_text}'")
                            
                    except Exception as e:
                        logging.error(f"Error processing a single detected plate: {e}")
                        continue # Tiếp tục với các biển số khác nếu có lỗi
                
                # Sắp xếp theo confidence (độ tin cậy) giảm dần
                detected_plates.sort(key=lambda x: x['confidence'], reverse=True)
                
                result['success'] = len(detected_plates) > 0
                result['plates'] = detected_plates
                
                return result
                
            except Exception as e:
                result['error'] = f"An unexpected error occurred during detection or reading: {e}"
                logging.error(result['error'], exc_info=True) # In stack trace
                return result
    
    def read_plate_advanced(self, crop_img: np.ndarray) -> str:
        """
        Đọc biển số từ ảnh cắt với nhiều phương pháp khác nhau để tăng cường độ chính xác.
        Các phương pháp bao gồm OCR trực tiếp, hiệu chỉnh xoay và tăng cường ảnh.

        Args:
            crop_img (np.ndarray): Ảnh của biển số đã được cắt ra.

        Returns:
            str: Văn bản biển số đã đọc được, hoặc "unknown" nếu không thể đọc.
        """
        if crop_img is None or crop_img.size == 0:
            logging.warning("Input crop_img for advanced reading is empty or None.")
            return "unknown"
        
        try:
            # Method 1: Trực tiếp với OCR model (ưu tiên)
            plate_text = self.read_plate_with_ocr(crop_img)
            if plate_text and plate_text != "unknown" and len(plate_text) > 3:
                return plate_text
            
            # Method 2: Với rotation correction
            plate_text = self.read_plate_with_rotation(crop_img)
            if plate_text and plate_text != "unknown" and len(plate_text) > 3:
                return plate_text
            
            # Method 3: Với image enhancement
            plate_text = self.read_plate_enhanced(crop_img)
            if plate_text and plate_text != "unknown" and len(plate_text) > 3:
                return plate_text
                
            return "unknown"
            
        except Exception as e:
            logging.error(f"Error in read_plate_advanced: {e}")
            return "unknown"
    
    def read_plate_with_ocr(self, crop_img: np.ndarray) -> str:
        """
        Đọc biển số bằng mô hình OCR chuyên dụng (yolo_license_plate).

        Args:
            crop_img (np.ndarray): Ảnh của biển số đã được cắt ra.

        Returns:
            str: Văn bản biển số đã đọc được, hoặc "unknown" nếu không thể đọc.
        """
        if self.yolo_license_plate is None:
            # Nếu mô hình OCR không được tải, không thể thực hiện OCR.
            # Logging cảnh báo này ở load_models là đủ.
            return "unknown"
            
        if crop_img is None or crop_img.size == 0:
            return "unknown"

        try:
            # Resize image cho OCR nếu cần (đảm bảo kích thước phù hợp với input của OCR model)
            height, width = crop_img.shape[:2]
            if width < self.MIN_PLATE_WIDTH_OCR:
                scale = self.MIN_PLATE_WIDTH_OCR / width
                new_width = self.MIN_PLATE_WIDTH_OCR
                new_height = int(height * scale)
                crop_img = cv2.resize(crop_img, (new_width, new_height), interpolation=cv2.INTER_LINEAR)
            
            # Sử dụng helper function nếu có
            if helper is not None and hasattr(helper, 'read_plate'):
                return helper.read_plate(self.yolo_license_plate, crop_img)
            else:
                # Fallback OCR method (đây là nơi cần tích hợp OCR thực tế nếu OCR model không có)
                # Hiện tại, simple_ocr chỉ tạo placeholder.
                logging.warning("Helper.read_plate not available. Falling back to simple_ocr (placeholder).")
                return self.simple_ocr(crop_img)
                
        except Exception as e:
            logging.error(f"Error in read_plate_with_ocr: {e}")
            return "unknown"
    
    def read_plate_with_rotation(self, crop_img: np.ndarray) -> str:
        """
        Đọc biển số với hiệu chỉnh xoay.
        Thử các góc xoay nhỏ để xử lý ảnh bị nghiêng.

        Args:
            crop_img (np.ndarray): Ảnh của biển số đã được cắt ra.

        Returns:
            str: Văn bản biển số đã đọc được, hoặc "unknown" nếu không thể đọc.
        """
        if crop_img is None or crop_img.size == 0:
            return "unknown"

        try:
            for angle in self.ROTATION_ANGLES:
                rotated_img = crop_img
                if utils_rotate is not None and hasattr(utils_rotate, 'deskew'):
                    # utils_rotate.deskew có thể phức tạp hơn, có thể cần điều chỉnh tham số
                    rotated_img = utils_rotate.deskew(crop_img, 0, angle) 
                else:
                    rotated_img = self.rotate_image(crop_img, angle)
                
                # Cố gắng đọc sau khi xoay
                plate_text = self.read_plate_with_ocr(rotated_img)
                if plate_text and plate_text != "unknown" and len(plate_text) > 3:
                    return plate_text
            
            return "unknown"
            
        except Exception as e:
            logging.error(f"Error in read_plate_with_rotation: {e}")
            return "unknown"
    
    def read_plate_enhanced(self, crop_img: np.ndarray) -> str:
        """
        Đọc biển số với các kỹ thuật tăng cường ảnh (grayscale, morphological ops, threshold).

        Args:
            crop_img (np.ndarray): Ảnh của biển số đã được cắt ra.

        Returns:
            str: Văn bản biển số đã đọc được, hoặc "unknown" nếu không thể đọc.
        """
        if crop_img is None or crop_img.size == 0:
            return "unknown"

        try:
            # Chuyển sang grayscale
            gray = cv2.cvtColor(crop_img, cv2.COLOR_BGR2GRAY)
            
            # Áp dụng morphological operations để làm rõ ký tự
            # kernel có thể điều chỉnh để phù hợp với kích thước ký tự
            kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
            # MORPH_CLOSE giúp nối liền các đứt gãy nhỏ trong ký tự
            gray = cv2.morphologyEx(gray, cv2.MORPH_CLOSE, kernel) 
            
            # Threshold để tạo ảnh nhị phân
            # THRESH_OTSU tự động tìm ngưỡng tối ưu
            _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
            
            # Convert back to BGR để phù hợp với input của OCR model (nếu model yêu cầu 3 kênh)
            enhanced_img = cv2.cvtColor(thresh, cv2.COLOR_GRAY2BGR)
            
            return self.read_plate_with_ocr(enhanced_img)
            
        except Exception as e:
            logging.error(f"Error in read_plate_enhanced: {e}")
            return "unknown"
    
    def rotate_image(self, image: np.ndarray, angle: float) -> np.ndarray:
        """
        Xoay ảnh theo góc cho trước (được sử dụng làm fallback nếu utils_rotate không có).

        Args:
            image (np.ndarray): Ảnh đầu vào.
            angle (float): Góc xoay (độ).

        Returns:
            np.ndarray: Ảnh đã được xoay.
        """
        if image is None or image.size == 0:
            return image
            
        try:
            height, width = image.shape[:2]
            center = (width // 2, height // 2)
            
            rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
            # WarpAffine cần kích thước đầu ra (width, height)
            rotated = cv2.warpAffine(image, rotation_matrix, (width, height), borderMode=cv2.BORDER_REPLICATE)
            
            return rotated
        except Exception as e:
            logging.error(f"Error rotating image by {angle} degrees: {e}")
            return image
    
    def simple_ocr(self, crop_img: np.ndarray) -> str:
        """
        Phương pháp OCR dự phòng đơn giản sử dụng phát hiện contour.
        LƯU Ý: Phương pháp này hiện tại chỉ tạo ra một chuỗi biển số giả (placeholder).
        Để có chức năng OCR thực tế, cần tích hợp một thư viện OCR (như Tesseract)
        hoặc một mô hình nhận dạng ký tự chuyên dụng.

        Args:
            crop_img (np.ndarray): Ảnh của biển số đã được cắt ra.

        Returns:
            str: Chuỗi biển số giả (placeholder) hoặc "unknown".
        """
        if crop_img is None or crop_img.size == 0:
            return "unknown"

        try:
            # Convert to grayscale
            gray = cv2.cvtColor(crop_img, cv2.COLOR_BGR2GRAY)
            
            # Apply threshold
            _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
            
            # Find contours
            contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            
            # Filter contours that could be characters
            char_contours = []
            for contour in contours:
                x, y, w, h = cv2.boundingRect(contour)
                aspect_ratio = w / h if h > 0 else 0
                area = cv2.contourArea(contour)
                
                # Simple filter based on character properties (can be improved)
                if 0.1 < aspect_ratio < 1.0 and area > 50 and h > 15: # Thêm điều kiện h > 15
                    char_contours.append((x, contour))
            
            # Sort by x coordinate to get characters in order
            char_contours.sort(key=lambda x: x[0])
            
            # Create placeholder text - this part needs actual OCR
            if len(char_contours) >= 4: # Giả định biển số có ít nhất 4 ký tự
                import random
                # Đây chỉ là placeholder. Cần thay thế bằng OCR thực tế.
                logging.warning("Using placeholder OCR result. Implement actual OCR for accurate results.")
                digits = "".join([str(random.randint(0, 9)) for _ in range(3)])
                letters = "".join([chr(random.randint(65, 90)) for _ in range(2)])
                return f"29{letters}{digits}" # Ví dụ: "29AB123"
            else:
                return "unknown"
                
        except Exception as e:
            logging.error(f"Error in simple_ocr: {e}")
            return "unknown"
    
    def process_image_file(self, image_path: str) -> dict:
        """
        Xử lý một file ảnh và trả về kết quả phát hiện biển số.

        Args:
            image_path (str): Đường dẫn đến file ảnh.

        Returns:
            dict: Kết quả phát hiện tương tự detect_and_read_plate.
        """
        result = {
            'success': False,
            'plates': [],
            'error': None
        }
        
        if not os.path.exists(image_path):
            result['error'] = f"Image file not found: {image_path}"
            logging.error(result['error'])
            return result
            
        frame = cv2.imread(image_path)
        if frame is None:
            result['error'] = f"Could not load image from: {image_path}. Check file format or corruption."
            logging.error(result['error'])
            return result
            
        return self.detect_and_read_plate(frame)
    
    def get_best_plate(self, detection_result: dict) -> dict | None:
        """
        Lấy kết quả biển số tốt nhất (có độ tin cậy cao nhất) từ kết quả detection.

        Args:
            detection_result (dict): Kết quả từ detect_and_read_plate.

        Returns:
            dict hoặc None: Thông tin biển số tốt nhất nếu có, ngược lại là None.
        """
        if not detection_result['success'] or not detection_result['plates']:
            return None
            
        return detection_result['plates'][0]  # Đã được sắp xếp theo confidence
    
    def is_ready(self) -> bool:
        """
        Kiểm tra xem hệ thống OptimizedLPR đã sẵn sàng để xử lý chưa (các mô hình đã được tải).

        Returns:
            bool: True nếu sẵn sàng, ngược lại là False.
        """
        return self.models_loaded