"""
TEST CONCURRENT API - Kiểm tra khả năng xử lý song song của PHP API
"""
import threading
import requests
import time
from datetime import datetime

# API Gateway endpoint
API_GATEWAY = "https://xparking.elementfx.com/api/gateway.php"

# Session với headers
session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'application/json',
})

def test_concurrent_checkin():
    """Test 4 xe vào cùng lúc"""
    print("\n" + "="*60)
    print("TEST 1: 4 XE VÀO CÙNG LÚC (CONCURRENT CHECKIN)")
    print("="*60)
    
    results = []
    
    def checkin(gate_id, plate):
        start = time.time()
        try:
            # Dùng GET với params (GLOBAL SLOT COUNT - không cần slot_id)
            response = session.get(
                API_GATEWAY,
                params={
                    'action': 'checkin',
                    'license_plate': plate,
                    'ticket_code': f'TEST{gate_id}_{int(time.time())}'
                },
                timeout=10
            )
            elapsed = time.time() - start
            
            result = {
                'gate': gate_id,
                'plate': plate,
                'status': response.status_code,
                'time': elapsed,
                'success': response.status_code == 200
            }
            results.append(result)
            
            # Parse JSON response
            try:
                data = response.json()
                success = data.get('success', False)
                error_msg = data.get('error', '')
                if success:
                    print(f"  Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s - OK")
                else:
                    print(f"  Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s - FAIL: {error_msg}")
            except:
                print(f"  Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s - JSON parse error")
            
        except Exception as e:
            elapsed = time.time() - start
            results.append({
                'gate': gate_id,
                'plate': plate,
                'status': 'ERROR',
                'time': elapsed,
                'success': False,
                'error': str(e)
            })
            print(f"  Gate {gate_id} ({plate}): ERROR - {e}")
    
    # Start concurrent requests
    threads = []
    plates = ['29A11111', '29A22222', '29B33333', '29B44444']
    
    start_all = time.time()
    
    for i, plate in enumerate(plates, 1):
        t = threading.Thread(target=checkin, args=(i, plate))
        threads.append(t)
        t.start()
    
    # Wait for all
    for t in threads:
        t.join()
    
    total_time = time.time() - start_all
    
    # Summary
    print(f"\n  Total time: {total_time:.2f}s")
    print(f"  Success: {sum(1 for r in results if r['success'])}/{len(results)}")
    print(f"  Avg response: {sum(r['time'] for r in results)/len(results):.2f}s")
    
    return results


def test_concurrent_verify_exit():
    """Test 4 xe ra cùng lúc"""
    print("\n" + "="*60)
    print("TEST 2: 4 XE RA CÙNG LÚC (CONCURRENT VERIFY_EXIT)")
    print("="*60)
    
    results = []
    
    def verify_exit(gate_id, plate):
        start = time.time()
        try:
            response = session.get(
                API_GATEWAY,
                params={
                    'action': 'verify_exit_full',
                    'license_plate': plate
                },
                timeout=10
            )
            elapsed = time.time() - start
            
            result = {
                'gate': gate_id,
                'plate': plate,
                'status': response.status_code,
                'time': elapsed,
                'success': response.status_code == 200
            }
            results.append(result)
            
            try:
                data = response.json()
                found = data.get('found', False)
                print(f"  Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s - {'FOUND' if found else 'NOT_FOUND'}")
            except:
                print(f"  Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s")
            
        except Exception as e:
            elapsed = time.time() - start
            results.append({
                'gate': gate_id,
                'plate': plate,
                'status': 'ERROR',
                'time': elapsed,
                'success': False,
                'error': str(e)
            })
            print(f"  Gate {gate_id} ({plate}): ERROR - {e}")
    
    # Start concurrent requests
    threads = []
    plates = ['29A11111', '29A22222', '29B33333', '29B44444']
    
    start_all = time.time()
    
    for i, plate in enumerate(plates, 1):
        t = threading.Thread(target=verify_exit, args=(i, plate))
        threads.append(t)
        t.start()
    
    # Wait for all
    for t in threads:
        t.join()
    
    total_time = time.time() - start_all
    
    # Summary
    print(f"\n  Total time: {total_time:.2f}s")
    print(f"  Success: {sum(1 for r in results if r['success'])}/{len(results)}")
    print(f"  Avg response: {sum(r['time'] for r in results)/len(results):.2f}s")
    
    return results


def test_mixed_operations():
    """Test hỗn hợp: 2 xe vào + 2 xe ra"""
    print("\n" + "="*60)
    print("TEST 3: 2 XE VÀO + 2 XE RA (MIXED OPERATIONS)")
    print("="*60)
    
    results = []
    
    def checkin(gate_id, plate):
        start = time.time()
        try:
            response = session.get(
                API_GATEWAY,
                params={
                    'action': 'checkin',
                    'license_plate': plate,
                    'ticket_code': f'IN{gate_id}_{int(time.time())}'
                },
                timeout=10
            )
            elapsed = time.time() - start
            print(f"  [IN] Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s")
            results.append({'type': 'IN', 'gate': gate_id, 'time': elapsed, 'status': response.status_code})
        except Exception as e:
            elapsed = time.time() - start
            print(f"  [IN] Gate {gate_id} ({plate}): ERROR - {e}")
            results.append({'type': 'IN', 'gate': gate_id, 'time': elapsed, 'status': 'ERROR'})
    
    def verify_exit(gate_id, plate):
        start = time.time()
        try:
            response = session.get(
                API_GATEWAY,
                params={
                    'action': 'verify_exit_full',
                    'license_plate': plate
                },
                timeout=10
            )
            elapsed = time.time() - start
            print(f"  [OUT] Gate {gate_id} ({plate}): {response.status_code} - {elapsed:.2f}s")
            results.append({'type': 'OUT', 'gate': gate_id, 'time': elapsed, 'status': response.status_code})
        except Exception as e:
            elapsed = time.time() - start
            print(f"  [OUT] Gate {gate_id} ({plate}): ERROR - {e}")
            results.append({'type': 'OUT', 'gate': gate_id, 'time': elapsed, 'status': 'ERROR'})
    
    # Start mixed operations
    threads = []
    
    start_all = time.time()
    
    # 2 xe vào
    threads.append(threading.Thread(target=checkin, args=(1, '29A11111')))
    threads.append(threading.Thread(target=checkin, args=(2, '29A22222')))
    
    # 2 xe ra
    threads.append(threading.Thread(target=verify_exit, args=(1, '29B33333')))
    threads.append(threading.Thread(target=verify_exit, args=(2, '29B44444')))
    
    for t in threads:
        t.start()
    
    for t in threads:
        t.join()
    
    total_time = time.time() - start_all
    
    # Summary
    print(f"\n  Total time: {total_time:.2f}s")
    print(f"  IN operations: {sum(1 for r in results if r['type'] == 'IN')}")
    print(f"  OUT operations: {sum(1 for r in results if r['type'] == 'OUT')}")
    print(f"  Avg response: {sum(r['time'] for r in results)/len(results):.2f}s")
    
    return results


def test_stress_load():
    """Test stress: 10 requests cùng lúc"""
    print("\n" + "="*60)
    print("TEST 4: STRESS TEST (10 CONCURRENT REQUESTS)")
    print("="*60)
    
    results = []
    
    def api_call(req_id):
        start = time.time()
        try:
            response = session.get(
                API_GATEWAY,
                params={
                    'action': 'get_slot_count'
                },
                timeout=15
            )
            elapsed = time.time() - start
            results.append({
                'id': req_id,
                'status': response.status_code,
                'time': elapsed,
                'success': response.status_code == 200
            })
            print(f"  Request {req_id:2d}: {response.status_code} - {elapsed:.2f}s")
        except Exception as e:
            elapsed = time.time() - start
            results.append({
                'id': req_id,
                'status': 'ERROR',
                'time': elapsed,
                'success': False
            })
            print(f"  Request {req_id:2d}: ERROR - {e}")
    
    threads = []
    start_all = time.time()
    
    for i in range(1, 11):
        t = threading.Thread(target=api_call, args=(i,))
        threads.append(t)
        t.start()
    
    for t in threads:
        t.join()
    
    total_time = time.time() - start_all
    
    # Summary
    print(f"\n  Total time: {total_time:.2f}s")
    print(f"  Success: {sum(1 for r in results if r['success'])}/{len(results)}")
    print(f"  Avg response: {sum(r['time'] for r in results)/len(results):.2f}s")
    print(f"  Max response: {max(r['time'] for r in results):.2f}s")
    print(f"  Min response: {min(r['time'] for r in results):.2f}s")
    
    return results


def main():
    print("\n" + "="*60)
    print("XPARKING API CONCURRENT TEST")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"API: {API_GATEWAY}")
    print("="*60)
    
    try:
        # Test 1: Concurrent checkin
        test_concurrent_checkin()
        time.sleep(2)
        
        # Test 2: Concurrent verify_exit
        test_concurrent_verify_exit()
        time.sleep(2)
        
        # Test 3: Mixed operations
        test_mixed_operations()
        time.sleep(2)
        
        # Test 4: Stress test
        test_stress_load()
        
    except KeyboardInterrupt:
        print("\n\nTest interrupted by user")
    except Exception as e:
        print(f"\n\nTest error: {e}")
    
    print("\n" + "="*60)
    print("TEST COMPLETED")
    print("="*60 + "\n")


if __name__ == "__main__":
    main()
