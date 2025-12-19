import paho.mqtt.client as mqtt
import json
import logging

logger = logging.getLogger('XParking')

class MQTTGate1:
    def __init__(self, config, system):
        self.config = config
        self.system = system
        self.mqtt = None
        # ESP32-CAM giờ là HTTP Server, không cần MQTT trigger nữa
        self.topics = {
            'entrance': 'xparking/gate1/entrance',
            'exit': 'xparking/gate1/exit',
            'slots': 'xparking/gate1/slots',
            'alert': 'xparking/gate1/alert',
            'command': 'xparking/gate1/command',
        }
    
    def connect(self):
        try:
            self.mqtt = mqtt.Client()
            self.mqtt.on_connect = self._on_connect
            self.mqtt.on_message = self._on_message
            self.mqtt.connect(self.config.config['mqtt_broker'], self.config.config['mqtt_port'], 60)
            self.mqtt.loop_start()
            return True
        except Exception as e:
            logger.error(f"[G1] MQTT error: {e}")
            return False
    
    def _on_connect(self, client, userdata, flags, rc):
        if rc == 0:
            logger.info("[G1] MQTT connected")
            for name, topic in self.topics.items():
                if name != 'command':
                    client.subscribe(topic)
            self.display("in", "X-PARKING", "GATE 1")
            self.display("out", "X-PARKING", "GATE 1")
    
    def _on_message(self, client, userdata, msg):
        topic = msg.topic
        try:
            # ESP32-CAM giờ gửi ảnh qua HTTP, không qua MQTT nữa
            payload = msg.payload.decode('utf-8')
            data = json.loads(payload) if payload.startswith('{') else {'event': payload}
            event = data.get('event', '')
            
            if topic == self.topics['entrance']:
                if event == 'CAR_DETECT_IN':
                    logger.info("[G1_In] detected")
                    self.system.executor.submit(self.system.handle_entry)
                elif event == 'CAR_ENTERED':
                    # Xe đã vào (barrier closed) → +1 slot, sync hosting
                    logger.info("[G1_In] CAR_ENTERED → +1 slot")
                    self.system.executor.submit(self.system._on_car_entered, 1)
            
            elif topic == self.topics['exit']:
                if event == 'CAR_DETECT':
                    logger.info("[G1_Out] detected")
                    self.system.executor.submit(self.system.handle_exit)
                elif event == 'CAR_EXITED':
                    # Xe đã ra (barrier closed) → -1 slot, sync hosting
                    logger.info("[G1_Out] CAR_EXITED → -1 slot")
                    self.system.executor.submit(self.system._on_car_exited, 1)
            
            elif topic == self.topics['alert']:
                self.system._handle_alert(payload)
            
            # slots topic không cần xử lý nữa - dùng global count
                
        except Exception as e:
            logger.error(f"[G1] MQTT msg error: {e}")
    
    def publish(self, topic_name, message):
        if self.mqtt and self.mqtt.is_connected():
            topic = self.topics.get(topic_name)
            if topic:
                if isinstance(message, dict):
                    message = json.dumps(message)
                self.mqtt.publish(topic, message)
    
    def display(self, station, line1, line2=""):
        event = "SHOW_MESSAGE_IN" if station == 'in' else "DISPLAY_OUT"
        self.publish('command', {"event": event, "station": station.upper(), "line1": line1, "line2": line2})
    
    def barrier(self, station, action):
        if station == 'in' and action == 'open':
            self.publish('command', {"event": "OPEN_BARRIER", "station": "IN"})
        else:
            self.publish('command', {"event": "BARRIER_OUT", "station": "OUT", "action": action})
    
    def disconnect(self):
        if self.mqtt:
            self.mqtt.loop_stop()
            self.mqtt.disconnect()
            logger.info("[G1] MQTT disconnected")
