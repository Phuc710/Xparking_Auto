<?php
/**
 * API GATEWAY - XPARKING
 * Endpoint chính cho tất cả API calls
 * 
 * Usage: gateway.php?action=<action>&<params>
 * 
 * Actions:
 * - TICKET: create_ticket, get_ticket, verify_ticket, use_ticket
 * - BOOKING: check_booking, get_booking, update_booking
 * - VEHICLE: checkin, checkout, get_vehicle_by_plate
 * - SLOTS: get_slots, update_slot
 */
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/csdl.php';
require_once __DIR__ . '/ticket_functions.php';

ApiResponse::init();

$action = ApiResponse::param('action');
if (!$action) ApiResponse::error('Missing action parameter');

// ============================================================
// ROUTE ACTIONS
// ============================================================
switch ($action) {

    // ========== TICKET ==========
    case 'create_ticket':
        $result = createTicket(['license_plate' => ApiResponse::param('license_plate')]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;
        
    case 'get_ticket':
        $result = getTicketInfo(ApiResponse::param('ticket_code'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;
        
    case 'verify_ticket':
        $result = verifyTicket(ApiResponse::param('ticket_code'), ApiResponse::param('license_plate'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;
        
    case 'use_ticket':
        $result = useTicket(ApiResponse::param('ticket_code'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    // ========== BOOKING ==========
    case 'check_booking':
        $plate = strtoupper(ApiResponse::param('license_plate'));
        if (!$plate) ApiResponse::error('Missing license_plate');
        
        // Tìm booking confirmed cho biển số này
        $bookings = supabaseQuery('bookings', "license_plate=eq.$plate&status=eq.confirmed", 'id,slot_id,start_time,end_time');
        $booking = $bookings[0] ?? null;
        
        $ticket_code = null;
        $qr_url = null;
        $valid_booking = false;
        
        // Nếu có booking, kiểm tra thời gian còn hiệu lực
        if ($booking) {
            $now = new DateTime();
            $end_time = new DateTime($booking['end_time']);
            
            // Chỉ chấp nhận booking chưa hết hạn
            if ($end_time > $now) {
                $ticket = supabaseGetOne('tickets', 'booking_id', $booking['id']);
                if ($ticket) {
                    $ticket_code = $ticket['ticket_code'];
                    $qr_url = $ticket['qr_url'];
                }
                $valid_booking = true;
            } else {
                // Booking đã hết hạn → tự động chuyển sang expired
                supabaseUpdate('bookings', 'id', $booking['id'], ['status' => 'expired']);
                $booking = null;
            }
        }
        
        ApiResponse::success([
            'has_booking' => $valid_booking,
            'booking_id' => $booking['id'] ?? null,
            'slot_id' => $booking['slot_id'] ?? null,
            'start_time' => $booking['start_time'] ?? null,
            'end_time' => $booking['end_time'] ?? null,
            'ticket_code' => $ticket_code,
            'qr_url' => $qr_url
        ]);
        break;
        
    case 'get_booking':
        $plate = strtoupper(ApiResponse::param('license_plate'));
        if (!$plate) ApiResponse::error('Missing license_plate');
        
        $bookings = supabaseQuery('bookings', "license_plate=eq.$plate&status=in.(confirmed,in_parking)&order=start_time.desc&limit=1");
        if ($bookings && $bookings[0]) {
            ApiResponse::success(['booking' => $bookings[0]]);
        }
        ApiResponse::error('No booking found');
        break;

    case 'update_booking':
        $id = ApiResponse::param('booking_id');
        $status = ApiResponse::param('status');
        if (!$id || !$status) ApiResponse::error('Missing booking_id or status');
        
        supabaseUpdate('bookings', 'id', $id, ['status' => $status]);
        ApiResponse::success(['booking_id' => $id, 'status' => $status]);
        break;
    
    case 'get_booking_by_id':
        $id = ApiResponse::param('booking_id');
        if (!$id) ApiResponse::error('Missing booking_id');
        
        $booking = supabaseGetOne('bookings', 'id', $id);
        if ($booking) {
            ApiResponse::success(['booking' => $booking]);
        }
        ApiResponse::error('Booking not found');
        break;

    // ========== VERIFY EXIT FULL (OPTIMIZED - 1 API call) ==========
    case 'verify_exit_full':
        $plate = strtoupper(trim(ApiResponse::param('license_plate') ?? ''));
        if (!$plate) ApiResponse::error('Missing license_plate');
        
        // 1. Tìm vehicle đang trong bãi
        $db = db();
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE license_plate = ? AND status = 'in_parking' ORDER BY entry_time DESC LIMIT 1");
        $stmt->execute([$plate]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            // BSX không tồn tại trong bãi → DỪNG NGAY
            ApiResponse::success([
                'found' => false,
                'error' => 'BSX_NOT_IN_PARKING',
                'message' => 'Xe không có trong bãi'
            ]);
            break;
        }
        
        $ticket_code = $vehicle['ticket_code'] ?? null;
        if (!$ticket_code) {
            ApiResponse::success([
                'found' => false,
                'error' => 'NO_TICKET',
                'message' => 'Xe không có vé'
            ]);
            break;
        }
        
        // 2. Lấy thông tin vé đầy đủ
        $ticket_info = getTicketInfo($ticket_code);
        if (!$ticket_info['success']) {
            ApiResponse::success([
                'found' => false,
                'error' => 'TICKET_NOT_FOUND',
                'message' => 'Không tìm thấy vé'
            ]);
            break;
        }
        
        $t = $ticket_info['ticket'];
        
        // 3. Tính toán allow_exit
        $allow_exit = false;
        $error_reason = null;
        
        if ($t['status'] === 'USED') {
            $error_reason = 'TICKET_ALREADY_USED';
        } elseif ($t['status'] === 'PAID') {
            // Đã thanh toán
            if ($t['has_overstay'] && $t['overstay_amount'] > 0) {
                // Có phí overstay chưa thanh toán
                $error_reason = 'OVERSTAY_UNPAID';
            } else {
                $allow_exit = true;
            }
        } elseif ($t['status'] === 'PENDING') {
            $error_reason = 'PAYMENT_PENDING';
        } else {
            $error_reason = 'INVALID_STATUS';
        }
        
        // 4. Trả về đầy đủ data 1 lần
        ApiResponse::success([
            'found' => true,
            'ticket_code' => $ticket_code,
            'license_plate' => $plate,
            'vehicle_id' => $vehicle['id'],
            'slot_id' => $vehicle['slot_id'],
            'status' => $t['status'],
            'time_in' => $t['time_in'],
            'time_out' => $t['time_out'],
            'minutes' => $t['minutes'],
            'amount' => $t['amount'],
            'has_overstay' => $t['has_overstay'] ?? false,
            'overstay_minutes' => $t['overstay_minutes'] ?? 0,
            'overstay_amount' => $t['overstay_amount'] ?? 0,
            'overstay_payment_ref' => $t['overstay_payment_ref'] ?? null,
            'booking_id' => $t['booking_id'] ?? null,
            'is_booking' => !empty($t['booking_id']),
            'allow_exit' => $allow_exit,
            'error_reason' => $error_reason
        ]);
        break;

    // ========== VEHICLE ========== (GLOBAL SLOT COUNT)
    case 'checkin':
        $plate = strtoupper(ApiResponse::param('license_plate'));
        $ticket = ApiResponse::param('ticket_code');
        $booking_id = ApiResponse::param('booking_id');
        
        if (!$plate) ApiResponse::error('Missing license_plate');
        
        try {
            $db = db();
            $db->beginTransaction();
            
            // 1. Check global slot availability
            $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('total_slots', 'occupied_slots') FOR UPDATE");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $total = (int)($settings['total_slots'] ?? 50);
            $occupied = (int)($settings['occupied_slots'] ?? 0);
            
            if ($occupied >= $total) {
                $db->rollBack();
                ApiResponse::error('Parking full - no available slots');
            }
            
            $entry_time = date('Y-m-d H:i:s');
            
            // 2. Find booking if exists
            $final_booking_id = $booking_id;
            if (!$final_booking_id) {
                $stmt = $db->prepare("SELECT id FROM bookings WHERE license_plate = ? AND status = 'confirmed' ORDER BY start_time ASC LIMIT 1");
                $stmt->execute([$plate]);
                $booking = $stmt->fetch();
                $final_booking_id = $booking['id'] ?? null;
            }
            
            // 3. Insert vehicle (no specific slot_id needed)
            $stmt = $db->prepare("INSERT INTO vehicles (license_plate, ticket_code, entry_time, status, booking_id) VALUES (?, ?, ?, 'in_parking', ?)");
            $stmt->execute([$plate, $ticket, $entry_time, $final_booking_id]);
            $vehicle_id = $db->lastInsertId();
            
            // 4. Increment occupied_slots (+1)
            $stmt = $db->prepare("UPDATE settings SET value = CAST(CAST(value AS UNSIGNED) + 1 AS CHAR) WHERE `key` = 'occupied_slots'");
            $stmt->execute();
            
            // 5. Update booking if exists
            if ($final_booking_id) {
                $stmt = $db->prepare("UPDATE bookings SET status = 'checked_in', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$final_booking_id]);
            }
            
            $db->commit();
            
            ApiResponse::success([
                'vehicle_id' => $vehicle_id,
                'license_plate' => $plate,
                'ticket_code' => $ticket,
                'entry_time' => $entry_time,
                'occupied_slots' => $occupied + 1,
                'available_slots' => $total - $occupied - 1
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Checkin error: " . $e->getMessage());
            ApiResponse::error('Checkin failed: ' . $e->getMessage());
        }
        break;

    case 'checkout':
        $ticket = ApiResponse::param('ticket_code');
        $plate = ApiResponse::param('license_plate');
        if (!$ticket) ApiResponse::error('Missing ticket_code');
        
        try {
            $db = db();
            $db->beginTransaction();
            
            // 1. Get vehicle
            $stmt = $db->prepare("SELECT * FROM vehicles WHERE ticket_code = ? AND status = 'in_parking' FOR UPDATE");
            $stmt->execute([$ticket]);
            $vehicle = $stmt->fetch();
            
            if (!$vehicle) {
                $db->rollBack();
                ApiResponse::error('Vehicle not found or already exited');
            }
            
            $exit_time = date('Y-m-d H:i:s');
            
            // 2. Update vehicle status
            $stmt = $db->prepare("UPDATE vehicles SET exit_time = ?, status = 'exited' WHERE ticket_code = ?");
            $stmt->execute([$exit_time, $ticket]);
            
            // 3. Decrement occupied_slots (-1)
            $stmt = $db->prepare("UPDATE settings SET value = CAST(GREATEST(CAST(value AS UNSIGNED) - 1, 0) AS CHAR) WHERE `key` = 'occupied_slots'");
            $stmt->execute();
            
            // 4. Complete booking if exists
            if ($vehicle['booking_id']) {
                $stmt = $db->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $stmt->execute([$vehicle['booking_id']]);
            }
            
            // 5. Mark ticket as USED
            $stmt = $db->prepare("UPDATE tickets SET status = 'USED', time_out = ? WHERE ticket_code = ?");
            $stmt->execute([$exit_time, $ticket]);
            
            $db->commit();
            
            ApiResponse::success([
                'message' => 'Checkout OK',
                'license_plate' => $vehicle['license_plate'],
                'exit_time' => $exit_time
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            ApiResponse::error('Checkout failed: ' . $e->getMessage());
        }
        break;

    case 'get_vehicle_by_plate':
        $plate = strtoupper(ApiResponse::param('license_plate'));
        if (!$plate) ApiResponse::error('Missing license_plate');
        
        $vehicles = supabaseQuery('vehicles', "license_plate=eq.$plate&status=eq.in_parking&order=entry_time.desc&limit=1");
        if ($vehicles && $vehicles[0]) {
            ApiResponse::success(['vehicle' => $vehicles[0]]);
        }
        ApiResponse::error('Vehicle not found');
        break;

    // ========== SLOTS (SIMPLIFIED - Global Count) ==========
    case 'get_slot_count':
        try {
            $db = db();
            $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('total_slots', 'occupied_slots')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $total = (int)($settings['total_slots'] ?? 50);
            $occupied = (int)($settings['occupied_slots'] ?? 0);
            $available = max(0, $total - $occupied);
            
            ApiResponse::success([
                'total_slots' => $total,
                'occupied_slots' => $occupied,
                'available_slots' => $available
            ]);
        } catch (Exception $e) {
            ApiResponse::error('get_slot_count failed: ' . $e->getMessage());
        }
        break;
    
    case 'increment_slot':
        // Xe VÀO: +1
        try {
            $db = db();
            $stmt = $db->prepare("UPDATE settings SET value = CAST(CAST(value AS UNSIGNED) + 1 AS CHAR) WHERE `key` = 'occupied_slots'");
            $stmt->execute();
            
            // Return new count
            $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('total_slots', 'occupied_slots')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            ApiResponse::success([
                'total_slots' => (int)($settings['total_slots'] ?? 50),
                'occupied_slots' => (int)($settings['occupied_slots'] ?? 0)
            ]);
        } catch (Exception $e) {
            ApiResponse::error('increment_slot failed: ' . $e->getMessage());
        }
        break;
    
    case 'decrement_slot':
        // Xe RA: -1
        try {
            $db = db();
            
            // Check if settings exist
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM settings WHERE `key` = 'occupied_slots'");
            $stmt->execute();
            $exists = $stmt->fetch()['cnt'] ?? 0;
            
            if ($exists == 0) {
                // Insert default if not exists
                $stmt = $db->prepare("INSERT INTO settings (`key`, value, description) VALUES ('occupied_slots', '0', 'Số chỗ đang sử dụng')");
                $stmt->execute();
            }
            
            // Decrement
            $stmt = $db->prepare("UPDATE settings SET value = CAST(GREATEST(CAST(value AS UNSIGNED) - 1, 0) AS CHAR) WHERE `key` = 'occupied_slots'");
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("decrement_slot UPDATE failed: " . json_encode($stmt->errorInfo()));
                ApiResponse::error('Failed to update slot count');
            }
            
            // Return new count
            $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('total_slots', 'occupied_slots')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            ApiResponse::success([
                'total_slots' => (int)($settings['total_slots'] ?? 50),
                'occupied_slots' => (int)($settings['occupied_slots'] ?? 0)
            ]);
        } catch (Exception $e) {
            error_log("decrement_slot exception: " . $e->getMessage() . " | " . $e->getTraceAsString());
            ApiResponse::error('decrement_slot failed: ' . $e->getMessage());
        }
        break;
    
    case 'get_slots':
        // Legacy - redirect to get_slot_count
        try {
            $db = db();
            $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('total_slots', 'occupied_slots')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $total = (int)($settings['total_slots'] ?? 50);
            $occupied = (int)($settings['occupied_slots'] ?? 0);
            
            // Return dummy slot for compatibility
            ApiResponse::success(['slots' => [['id' => 'SLOT', 'status' => $occupied < $total ? 'empty' : 'occupied']]]);
        } catch (Exception $e) {
            ApiResponse::error('get_slots failed');
        }
        break;

    // ========== DEFAULT ==========
    default:
        ApiResponse::error("Unknown action: $action");
}
