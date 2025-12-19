"""
HTTP Server - Health check only
ESP32-CAM giờ là HTTP Server, Python GET trực tiếp từ ESP32-CAM
Không cần nhận POST từ ESP32-CAM nữa
Port 5000
"""

from flask import Flask, jsonify
import threading
import logging

logger = logging.getLogger('XParking')

app = Flask(__name__)

@app.route('/health', methods=['GET'])
def health():
    """Health check"""
    return jsonify({'status': 'ok'}), 200

@app.route('/status', methods=['GET'])
def status():
    """System status"""
    return jsonify({
        'status': 'running',
        'esp32_cam': 'Python GET from ESP32-CAM HTTP Server',
        'webcam': 'USB Webcam for EXIT'
    }), 200

def start_server(host='0.0.0.0', port=5000):
    """Start HTTP server in background thread"""
    def run():
        # Disable Flask logs
        log = logging.getLogger('werkzeug')
        log.setLevel(logging.ERROR)
        
        logger.info(f"[HTTP] Server starting on {host}:{port}")
        app.run(host=host, port=port, threaded=True, use_reloader=False)
    
    thread = threading.Thread(target=run, daemon=True)
    thread.start()
    return thread
