"""
Test API Slot Management - XPARKING
Kiểm tra các API liên quan đến slot (increment/decrement)
"""
import requests
import json
from datetime import datetime

API_URL = "https://xparking.elementfx.com/api/gateway.php"

session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'application/json',
})

def test_get_slot_count():
    """Test lấy slot count"""
    print("\n" + "="*60)
    print("TEST: get_slot_count")
    print("="*60)
    
    params = {'action': 'get_slot_count'}
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Response: {json.dumps(data, indent=2, ensure_ascii=False)}")
            if data.get('success'):
                print(f"✅ Total: {data.get('total_slots')}, Occupied: {data.get('occupied_slots')}, Available: {data.get('available_slots')}")
        else:
            print(f"❌ HTTP {response.status_code}")
            print(f"Response: {response.text}")
    except Exception as e:
        print(f"❌ Exception: {e}")

def test_increment_slot():
    """Test increment slot (+1)"""
    print("\n" + "="*60)
    print("TEST: increment_slot")
    print("="*60)
    
    params = {'action': 'increment_slot'}
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Response: {json.dumps(data, indent=2, ensure_ascii=False)}")
            if data.get('success'):
                print(f"✅ Slot +1 → Occupied: {data.get('occupied_slots')}/{data.get('total_slots')}")
        else:
            print(f"❌ HTTP {response.status_code}")
            print(f"Response: {response.text}")
    except Exception as e:
        print(f"❌ Exception: {e}")

def test_decrement_slot():
    """Test decrement slot (-1)"""
    print("\n" + "="*60)
    print("TEST: decrement_slot")
    print("="*60)
    
    params = {'action': 'decrement_slot'}
    print(f"Request: {params}")
    
    try:
        response = session.get(API_URL, params=params, timeout=10)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Response: {json.dumps(data, indent=2, ensure_ascii=False)}")
            if data.get('success'):
                print(f"✅ Slot -1 → Occupied: {data.get('occupied_slots')}/{data.get('total_slots')}")
        else:
            print(f"❌ HTTP {response.status_code}")
            print(f"Response Text: {response.text}")
            try:
                error_data = response.json()
                print(f"Error JSON: {json.dumps(error_data, indent=2, ensure_ascii=False)}")
            except:
                pass
    except Exception as e:
        print(f"❌ Exception: {e}")

def main():
    print("="*60)
    print("   TEST SLOT API - XPARKING")
    print("="*60)
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    # 1. Xem trạng thái hiện tại
    test_get_slot_count()
    
    # 2. Test increment
    print("\n🔼 TEST INCREMENT:")
    test_increment_slot()
    test_get_slot_count()
    
    # 3. Test decrement (LỖI Ở ĐÂY)
    print("\n🔽 TEST DECREMENT:")
    test_decrement_slot()
    test_get_slot_count()
    
    print("\n" + "="*60)
    print("   KẾT THÚC")
    print("="*60)

if __name__ == "__main__":
    main()
