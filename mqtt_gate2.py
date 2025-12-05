"""
MQTT Handler cho Gate 2
Xu ly tat ca giao tiep MQTT giua Python va ESP32 Gate2 + ESP32-CAM Gate2
"""

import paho.mqtt.client as mqtt
import json
import logging
import threading

logger = logging.getLogger('XParking')

class MQTTGate2:
    def __init__(self, config, system_functions):
        self.config = config
        self.system = system_functions
        self.mqtt = None
        self.gate_id = "gate2"
        
        # Topics
        self.topics = {
            'entrance': f'xparking/{self.gate_id}/entrance',
            'exit': f'xparking/{self.gate_id}/exit',
            'slots': f'xparking/{self.gate_id}/slots',
            'alert': f'xparking/{self.gate_id}/alert',
            'status': f'xparking/{self.gate_id}/status',
            'command': f'xparking/{self.gate_id}/command',
            'cam_trigger': f'xparking/{self.gate_id}/cam/trigger',
            'cam_image': f'xparking/{self.gate_id}/cam/image',
            'cam_status': f'xparking/{self.gate_id}/cam/status'
        }
    
    def connect(self):
        """Ket noi MQTT broker"""
        try:
            self.mqtt = mqtt.Client()
            self.mqtt.on_connect = self._on_connect
            self.mqtt.on_message = self._on_message
            self.mqtt.connect(
                self.config.config['mqtt_broker'],
                self.config.config['mqtt_port'], 60
            )
            self.mqtt.loop_start()
            logger.info(f"[GATE2] MQTT connecting...")
            return True
        except Exception as e:
            logger.error(f"[GATE2] MQTT error: {e}")
            return False
    
    def _on_connect(self, client, userdata, flags, rc):
        """Callback khi ket noi thanh cong"""
        if rc == 0:
            logger.info("[GATE2] MQTT connected")
            
            # Subscribe topics
            for name, topic in self.topics.items():
                if name not in ['command', 'cam_trigger']:  # Khong subscribe topics publish
                    client.subscribe(topic)
                    logger.info(f"[GATE2] Subscribe: {topic}")
            
            # Gui lenh khoi tao
            self.display("in", "X-PARKING", "GATE 2 IN")
            self.display("out", "X-PARKING", "GATE 2 OUT")
        else:
            logger.error(f"[GATE2] MQTT connect fail: {rc}")
    
    def _on_message(self, client, userdata, msg):
        """Xu ly message tu MQTT"""
        topic = msg.topic
        
        try:
            # Anh binary tu ESP32-CAM
            if topic == self.topics['cam_image']:
                if self.config.waiting_for_qr_gate2:
                    logger.info(f"[GATE2] 📷 Nhan anh: {len(msg.payload)//1024}KB")
                    self.system.executor.submit(self.system._process_qr_from_bytes_gate2, msg.payload)
                return
            
            # Text messages
            payload = msg.payload.decode('utf-8')
            
            # Entrance events
            if topic == self.topics['entrance']:
                data = json.loads(payload) if payload.startswith('{') else {'event': payload}
                event = data.get('event', '')
                
                if event == 'CAR_DETECT_IN':
                    logger.info("[GATE2] 🚗 Xe vao")
                    self.system.executor.submit(self.system.handle_entry_gate2)
                elif event == 'CAR_PASSED_IR':
                    logger.info("[GATE2] ✅ Xe qua cong vao")
            
            # Exit events
            elif topic == self.topics['exit']:
                data = json.loads(payload) if payload.startswith('{') else {'event': payload}
                event = data.get('event', '')
                
                if event == 'CAR_DETECT':
                    logger.info("[GATE2] 🚀 Xe ra")
                    self.system.executor.submit(self.system.handle_exit_gate2)
                elif event == 'CAR_EXITED':
                    logger.info("[GATE2] ✅ Xe da ra")
                elif event == 'CAR_REVERSE':
                    logger.info("[GATE2] ⚠️ Xe lui ra")
                elif event in ['VERIFY_TIMEOUT', 'BARRIER_TIMEOUT']:
                    logger.warning(f"[GATE2] ⏱️ {event}")
            
            # Slots update
            elif topic == self.topics['slots']:
                self._handle_slot_update(payload)
            
            # Status update
            elif topic == self.topics['status']:
                self._handle_status_update(payload)
            
            # Alert
            elif topic == self.topics['alert']:
                self._handle_alert(payload)
                
        except Exception as e:
            logger.error(f"[GATE2] MQTT message error: {e}")
    
    def _handle_slot_update(self, payload):
        """Xu ly cap nhat slot"""
        try:
            data = json.loads(payload)
            slot_id = data.get('slot_id')
            status = data.get('status')
            if slot_id and status:
                self.system.gui.update_slot_status(slot_id, status)
        except:
            pass
    
    def _handle_status_update(self, payload):
        """Xu ly cap nhat status"""
        logger.info(f"[GATE2] Status: {payload}")
    
    def _handle_alert(self, payload):
        """Xu ly canh bao"""
        logger.warning(f"[GATE2] ⚠️ Alert: {payload}")
    
    def publish(self, topic_name, message):
        """Publish message"""
        if self.mqtt and self.mqtt.is_connected():
            topic = self.topics.get(topic_name)
            if topic:
                if isinstance(message, (dict, list)):
                    message = json.dumps(message)
                self.mqtt.publish(topic, message)
    
    def display(self, station, line1, line2=""):
        """Hien thi message tren LCD"""
        if station == 'in':
            self.publish('command', {
                "event": "SHOW_MESSAGE_IN",
                "station": "IN",
                "line1": line1,
                "line2": line2
            })
        else:
            self.publish('command', {
                "event": "DISPLAY_OUT",
                "station": "OUT",
                "line1": line1,
                "line2": line2
            })
    
    def barrier(self, station, action):
        """Dieu khien barrier"""
        if station == 'in':
            if action == 'open':
                self.publish('command', {
                    "event": "OPEN_BARRIER",
                    "station": "IN"
                })
        else:
            self.publish('command', {
                "event": "BARRIER_OUT",
                "station": "OUT",
                "action": action
            })
    
    def trigger_camera(self):
        """Trigger ESP32-CAM chup anh"""
        self.publish('cam_trigger', 'capture')
    
    def disconnect(self):
        """Ngat ket noi MQTT"""
        if self.mqtt:
            self.mqtt.loop_stop()
            self.mqtt.disconnect()
            logger.info("[GATE2] MQTT disconnected")
