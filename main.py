import sys
import logging
import threading
import time

# Import các module đã clean
from config import SystemConfig, GUIManager
from email_handler import EmailHandler
from functions import SystemFunctions

# Modules bên ngoài
from QUET_BSX import OptimizedLPR
from db_api import DatabaseAPI

# Cấu hình logging - format ngắn gọn
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s | %(message)s',
    datefmt='%H:%M:%S',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger('XParking')

# Giảm noise từ các thư viện khác
logging.getLogger('urllib3').setLevel(logging.WARNING)
logging.getLogger('PIL').setLevel(logging.WARNING)

class XParkingSystem:
    def __init__(self):
        """Khởi tạo hệ thống XParking"""
        logger.info("Khởi động hệ thống XParking...")
        
        # Khởi tạo các thành phần cốt lõi
        self.config_manager = SystemConfig()
        self.gui_manager = GUIManager(self.config_manager)
        self.lpr_system = OptimizedLPR()
        self.db_api = DatabaseAPI(self.config_manager.config)
        self.email_handler = EmailHandler(self.config_manager)
        
        # Khởi tạo Functions chính (chứa toàn bộ logic)
        self.functions = SystemFunctions(
            self.config_manager, self.gui_manager, self.lpr_system, 
            self.db_api, self.email_handler
        )
        
        # GUI root reference
        self.root = None
        
        logger.info("Các module đã được khởi tạo")

    def run(self):
        """Chạy hệ thống chính"""
        try:
            # 1. Khởi tạo GUI
            logger.info("Đang khởi tạo giao diện...")
            self.root = self.gui_manager.init_gui(self)
            
            # 2. Khởi tạo delayed components
            self.root.after(100, self._delayed_init)
            
            logger.info("Hệ thống XParking đã sẵn sàng")
            
            # 3. Chạy GUI main loop
            self.root.mainloop()
            
        except KeyboardInterrupt:
            logger.info("Nhận lệnh ngắt từ bàn phím")
        except Exception as e:
            logger.error(f"Lỗi chạy hệ thống: {e}")
            import traceback
            traceback.print_exc()
        finally:
            self.shutdown()

    def _delayed_init(self):
        """Khởi tạo các thành phần sau khi GUI đã sẵn sàng"""
        try:
            # Kết nối MQTT
            logger.info("Đang kết nối MQTT...")
            mqtt_success = self.functions.init_mqtt()
            self.gui_manager.update_status('mqtt_status', mqtt_success)
            
            # Khởi tạo cameras
            logger.info("Đang khởi tạo cameras...")
            cam_success = self.gui_manager.init_cameras(self.gui_manager.update_status)
            
            # Load AI model trong background
            logger.info("Đang load AI model...")
            self.gui_manager.update_status('ai_status', False)
            threading.Thread(target=self._init_ai_model, daemon=True).start()
            
            # Gas sensor mặc định OK
            self.gui_manager.update_status('gas_status', True)
            
            logger.info("Khởi tạo delayed components hoàn tất")
            
        except Exception as e:
            logger.error(f"Lỗi khởi tạo delayed components: {e}")

    def _init_ai_model(self):
        """Khởi tạo AI model trong background thread"""
        try:
            logger.info("Đang tải AI model...")
            if self.lpr_system.load_models():
                if self.root:
                    self.root.after(0, lambda: self.gui_manager.update_status('ai_status', True))
                logger.info("AI model đã load thành công")
            else:
                if self.root:
                    self.root.after(0, lambda: self.gui_manager.update_status('ai_status', False))
                logger.error("Lỗi load AI model")
        except Exception as e:
            logger.error(f"Lỗi khởi tạo AI model: {e}")
            if self.root:
                self.root.after(0, lambda: self.gui_manager.update_status('ai_status', False))

    def shutdown(self):
        """Tắt hệ thống an toàn"""
        logger.info("Đang tắt hệ thống...")
        try:
            if hasattr(self, 'functions'):
                self.functions.shutdown()
        except Exception as e:
            logger.error(f"Lỗi khi tắt functions: {e}")
        
        logger.info("Hệ thống đã tắt hoàn toàn")

    # Các phương thức hỗ trợ cho PaymentManager và các modules khác
    def update_slot_status(self, slot_id, status):
        """Cập nhật trạng thái slot"""
        if hasattr(self, 'gui_manager'):
            self.gui_manager.update_slot_status(slot_id, status)

    def update_status(self, key, is_active):
        """Cập nhật status indicator"""
        if hasattr(self, 'gui_manager'):
            self.gui_manager.update_status(key, is_active)

# Entry point chính
if __name__ == "__main__":
    system = None
    try:
        system = XParkingSystem()
        system.run()
    except Exception as e:
        logger.error(f"Lỗi khởi động hệ thống: {e}")
        import traceback
        traceback.print_exc()
    finally:
        if system:
            system.shutdown()