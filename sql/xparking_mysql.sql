-- ============================================================
-- XPARKING DATABASE - MySQL Version
-- Timezone: Asia/Ho_Chi_Minh (UTC+7)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- Disable foreign key checks để xóa bảng không bị lỗi
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================
-- BƯỚC 1: XÓA BẢNG CŨ (nếu có)
-- ===================================================
DROP TABLE IF EXISTS webhook_payments;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS system_logs;
-- parking_slots removed - using settings table for slot count
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

-- Enable lại foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================
-- BƯỚC 2: TẠO CÁC BẢNG
-- ===================================================

-- Bảng users
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng settings
CREATE TABLE settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- parking_slots removed - slot count managed in settings table

-- Bảng vehicles (slot_id removed - using global count)
CREATE TABLE vehicles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL,
    user_id BIGINT DEFAULT NULL,
    booking_id BIGINT DEFAULT NULL,
    entry_time DATETIME DEFAULT NULL,
    exit_time DATETIME DEFAULT NULL,
    ticket_code VARCHAR(20) DEFAULT NULL,
    status ENUM('in_parking', 'exited', 'pending') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng bookings (slot_id removed - using global count)
CREATE TABLE bookings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    license_plate VARCHAR(20) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    ticket_code VARCHAR(20) DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    payment_ref VARCHAR(100) DEFAULT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'expired', 'cancelled') DEFAULT 'pending',
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'in_parking') DEFAULT 'pending',
    checked_in TINYINT(1) DEFAULT 0,
    actual_entry_time DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng tickets
CREATE TABLE tickets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(20) UNIQUE NOT NULL,
    license_plate VARCHAR(20) NOT NULL,
    booking_id BIGINT DEFAULT NULL,
    
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    
    amount INT DEFAULT 0,
    status ENUM('PENDING', 'ACTIVE', 'PAID', 'USED', 'EXPIRED', 'CANCELLED') DEFAULT 'PENDING',
    
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,
    
    payment_ref VARCHAR(100) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    
    qr_url VARCHAR(500) DEFAULT NULL,
    payment_method ENUM('webhook', 'api') DEFAULT 'webhook',
    
    entry_image TEXT DEFAULT NULL,
    exit_image TEXT DEFAULT NULL,
    ticket_image TEXT DEFAULT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng payments
CREATE TABLE payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT DEFAULT NULL,
    vehicle_id BIGINT DEFAULT NULL,
    booking_id BIGINT DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_ref VARCHAR(100) UNIQUE,
    sepay_ref VARCHAR(100) DEFAULT NULL,
    qr_code VARCHAR(255) DEFAULT NULL,
    payment_time DATETIME DEFAULT NULL,
    status ENUM('pending', 'completed', 'failed', 'expired', 'cancelled') DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng webhook_payments
CREATE TABLE webhook_payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sepay_transaction_id VARCHAR(100) UNIQUE,
    reference TEXT,
    ticket_code VARCHAR(20) DEFAULT NULL,
    payment_id BIGINT DEFAULT NULL,
    amount INT NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payload TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng system_logs
CREATE TABLE system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    user_id BIGINT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng notifications
CREATE TABLE notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT DEFAULT NULL,
    target_user_id BIGINT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- BƯỚC 3: TẠO INDEXES TỐI ƯU (PERFORMANCE CRITICAL)
-- ===================================================

-- CRITICAL INDEXES - API performance (sử dụng nhiều nhất)
CREATE INDEX idx_vehicles_plate ON vehicles(license_plate);
CREATE INDEX idx_vehicles_ticket ON vehicles(ticket_code);
CREATE INDEX idx_vehicles_status ON vehicles(status);
CREATE INDEX idx_vehicles_booking ON vehicles(booking_id);

-- TICKETS - Quan trọng nhất cho verify/payment
CREATE INDEX idx_tickets_code ON tickets(ticket_code);
CREATE INDEX idx_tickets_plate ON tickets(license_plate);
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_created ON tickets(created_at);
CREATE INDEX idx_tickets_code_status ON tickets(ticket_code, status);

-- BOOKINGS - Entry gate processing
CREATE INDEX idx_bookings_plate ON bookings(license_plate);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_ticket ON bookings(ticket_code);
CREATE INDEX idx_bookings_checked_in ON bookings(checked_in);
CREATE INDEX idx_bookings_time ON bookings(start_time, end_time);

-- PARKING_SLOTS removed - using settings for slot count

-- PAYMENTS - Real-time polling
CREATE INDEX idx_payments_ref ON payments(payment_ref);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_created ON payments(created_at);

-- WEBHOOK_PAYMENTS - Fast webhook processing
CREATE INDEX idx_webhook_transaction ON webhook_payments(sepay_transaction_id);
CREATE INDEX idx_webhook_ticket ON webhook_payments(ticket_code);
CREATE INDEX idx_webhook_payment ON webhook_payments(payment_id);

-- SYSTEM TABLES
CREATE INDEX idx_logs_event ON system_logs(event_type);
CREATE INDEX idx_settings_key ON settings(`key`);

-- COMPOSITE INDEXES cho queries phức tạp
CREATE INDEX idx_tickets_stats ON tickets(status, created_at, amount);
CREATE INDEX idx_vehicles_recent ON vehicles(created_at DESC, status);

-- Thêm FOREIGN KEY cho vehicles.booking_id (phải dùng ALTER vì circular reference)
ALTER TABLE vehicles ADD CONSTRAINT fk_vehicles_booking 
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL;

-- ===================================================
-- BƯỚC 4: DỮ LIỆU MẶC ĐỊNH
-- ===================================================

-- Admin account (password: admin123)
INSERT INTO users (username, password, email, full_name, role) VALUES 
('admin', '$2b$10$lQPGnj2SXXKtN2pUu5Cqk.GB59SW5HOfm3M4y2/o/cMS/khWNCFP2', 'admin@xparking.com', 'System Administrator', 'admin');

-- Settings mặc định (slot count managed here)
INSERT INTO settings (`key`, value, description) VALUES 
('total_slots', '50', 'Tổng số chỗ đỗ xe'),
('occupied_slots', '0', 'Số chỗ đang sử dụng'),
('price_amount', '5000', 'Số tiền tính phí (VNĐ)'),
('price_minutes', '60', '5000/1h'),
('min_price', '5000', 'Giá tối thiểu (VNĐ)');

-- ===================================================
-- BƯỚC 5: STORED PROCEDURES (SIMPLIFIED - No slot table)
-- ===================================================

DROP PROCEDURE IF EXISTS GetSlotCount;
DROP PROCEDURE IF EXISTS IncrementSlot;
DROP PROCEDURE IF EXISTS DecrementSlot;
DROP PROCEDURE IF EXISTS ProcessVehicleEntry;
DROP PROCEDURE IF EXISTS ProcessVehicleExit;

DELIMITER //

-- Procedure: Lấy slot count
CREATE PROCEDURE GetSlotCount()
BEGIN
    SELECT 
        (SELECT CAST(value AS UNSIGNED) FROM settings WHERE `key` = 'total_slots') AS total_slots,
        (SELECT CAST(value AS UNSIGNED) FROM settings WHERE `key` = 'occupied_slots') AS occupied_slots,
        (SELECT CAST(value AS UNSIGNED) FROM settings WHERE `key` = 'total_slots') - 
        (SELECT CAST(value AS UNSIGNED) FROM settings WHERE `key` = 'occupied_slots') AS available_slots;
END //

-- Procedure: Xe VÀO (+1)
CREATE PROCEDURE IncrementSlot()
BEGIN
    UPDATE settings SET value = CAST(CAST(value AS UNSIGNED) + 1 AS CHAR) 
    WHERE `key` = 'occupied_slots';
END //

-- Procedure: Xe RA (-1)
CREATE PROCEDURE DecrementSlot()
BEGIN
    UPDATE settings SET value = CAST(GREATEST(CAST(value AS UNSIGNED) - 1, 0) AS CHAR) 
    WHERE `key` = 'occupied_slots';
END //

-- Procedure: Xử lý xe vào (simplified)
CREATE PROCEDURE ProcessVehicleEntry(
    IN p_plate VARCHAR(20),
    IN p_booking_id BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Increment slot count
    UPDATE settings SET value = CAST(CAST(value AS UNSIGNED) + 1 AS CHAR) 
    WHERE `key` = 'occupied_slots';
    
    -- Insert vehicle record
    INSERT INTO vehicles (license_plate, status, booking_id, entry_time) 
    VALUES (p_plate, 'in_parking', p_booking_id, NOW());
    
    COMMIT;
END //

-- Procedure: Xử lý xe ra (simplified)
CREATE PROCEDURE ProcessVehicleExit(
    IN p_ticket VARCHAR(20)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update vehicle status
    UPDATE vehicles 
    SET status = 'exited', exit_time = NOW() 
    WHERE ticket_code = p_ticket;
    
    -- Decrement slot count
    UPDATE settings SET value = CAST(GREATEST(CAST(value AS UNSIGNED) - 1, 0) AS CHAR) 
    WHERE `key` = 'occupied_slots';
    
    COMMIT;
END //

DELIMITER ;

-- ===================================================
-- BƯỚC 6: ANALYZE TABLES
-- ===================================================

ANALYZE TABLE users;
ANALYZE TABLE vehicles;
ANALYZE TABLE tickets;
ANALYZE TABLE bookings;
ANALYZE TABLE payments;
ANALYZE TABLE webhook_payments;
ANALYZE TABLE settings;

