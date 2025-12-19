"""
Test API Checkout - XPARKING
Kiểm tra API checkout với các trường hợp khác nhau
"""
import requests
import json
from datetime import datetime

# ========== CONFIG ==========
API_URL = "https://xparking.elementfx.com/api/gateway.php"
DOMAIN = "xparking.elementfx.com"

# Tạo session với cookies (bypass infinityfree nếu cần)
session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'application/json',
})

# ========== TEST CASES ==========
def test_checkout_valid():
    """Test checkout với ticket_code hợp lệ (xe đang trong bãi)"""
    print("\n" + "="*60)
    print("TEST 1: Checkout với ticket_code hợp lệ")
    print("="*60)
    
    # Từ database, xe đang in_parking
    ticket_code = "VEEE1BDE56"  # Ticket của xe 84H153236
    license_plate = "84H153236"  # BSX đang trong bãi
    
    params = {
        'action': 'checkout',
        'ticket_code': ticket_code,
        'license_plate': license_plate
    }
    
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status Code: {response.status_code}")
        print(f"Response: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
        
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                print("✅ CHECKOUT THÀNH CÔNG")
                print(f"   License Plate: {data.get('license_plate')}")
                print(f"   Exit Time: {data.get('exit_time')}")
            else:
                print(f"❌ CHECKOUT THẤT BẠI: {data.get('error')}")
        else:
            print(f"❌ HTTP ERROR: {response.status_code}")
            print(f"   Response: {response.text}")
            
    except Exception as e:
        print(f"❌ EXCEPTION: {e}")

def test_checkout_invalid_ticket():
    """Test checkout với ticket_code không tồn tại"""
    print("\n" + "="*60)
    print("TEST 2: Checkout với ticket_code không tồn tại")
    print("="*60)
    
    ticket_code = "VEINVALID1"
    
    params = {
        'action': 'checkout',
        'ticket_code': ticket_code
    }
    
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status Code: {response.status_code}")
        print(f"Response: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
        
        data = response.json()
        if not data.get('success'):
            print("✅ ĐÚNG: API trả về lỗi như mong đợi")
        else:
            print("❌ SAI: API không nên checkout thành công với ticket không tồn tại")
            
    except Exception as e:
        print(f"❌ EXCEPTION: {e}")

def test_checkout_missing_ticket():
    """Test checkout thiếu ticket_code"""
    print("\n" + "="*60)
    print("TEST 3: Checkout thiếu ticket_code")
    print("="*60)
    
    params = {
        'action': 'checkout'
        # Không có ticket_code
    }
    
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status Code: {response.status_code}")
        print(f"Response: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
        
        data = response.json()
        if not data.get('success'):
            print("✅ ĐÚNG: API trả về lỗi thiếu tham số")
        else:
            print("❌ SAI: API không nên checkout thành công khi thiếu ticket_code")
            
    except Exception as e:
        print(f"❌ EXCEPTION: {e}")

def test_checkout_already_exited():
    """Test checkout với ticket đã được sử dụng (xe đã ra)"""
    print("\n" + "="*60)
    print("TEST 4: Checkout với ticket đã được sử dụng")
    print("="*60)
    
    # Sử dụng ticket đã checkout ở TEST 1
    ticket_code = "VEEE1BDE56"
    
    params = {
        'action': 'checkout',
        'ticket_code': ticket_code
    }
    
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status Code: {response.status_code}")
        print(f"Response: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
        
        data = response.json()
        if not data.get('success'):
            print("✅ ĐÚNG: API trả về lỗi vì xe đã checkout")
        else:
            print("⚠️ CẢNH BÁO: API cho phép checkout 2 lần (có thể là bug)")
            
    except Exception as e:
        print(f"❌ EXCEPTION: {e}")

def test_get_slot_count():
    """Test kiểm tra slot count sau checkout"""
    print("\n" + "="*60)
    print("TEST 5: Kiểm tra slot count")
    print("="*60)
    
    params = {
        'action': 'get_slot_count'
    }
    
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status Code: {response.status_code}")
        data = response.json()
        
        if data.get('success'):
            print("✅ SLOT COUNT:")
            print(f"   Total Slots: {data.get('total_slots')}")
            print(f"   Occupied: {data.get('occupied_slots')}")
            print(f"   Available: {data.get('available_slots')}")
        else:
            print(f"❌ KHÔNG LẤY ĐƯỢC SLOT COUNT: {data.get('error')}")
            
    except Exception as e:
        print(f"❌ EXCEPTION: {e}")

# ========== MAIN ==========
def main():
    print("="*60)
    print("      TEST API CHECKOUT - XPARKING")
    print("="*60)
    print(f"API URL: {API_URL}")
    print(f"Thời gian: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # Kiểm tra slot count trước khi test
    print("\n📊 TRẠNG THÁI BAN ĐẦU:")
    test_get_slot_count()
    
    # Chạy các test cases
    print("\n🧪 BẮT ĐẦU TEST CASES:")
    
    # Test 1: Checkout hợp lệ
    test_checkout_valid()
    
    # Test 2: Ticket không tồn tại
    test_checkout_invalid_ticket()
    
    # Test 3: Thiếu ticket_code
    test_checkout_missing_ticket()
    
    # Test 4: Checkout lần 2 (đã exited)
    test_checkout_already_exited()
    
    # Kiểm tra slot count sau test
    print("\n📊 TRẠNG THÁI SAU TEST:")
    test_get_slot_count()
    
    print("\n" + "="*60)
    print("      KẾT THÚC TEST")
    print("="*60)

if __name__ == "__main__":
    main()
