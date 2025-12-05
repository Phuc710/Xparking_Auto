/**
 * ESP32_GATE1 
 * Hardware: 2 OLEDs (2 I2C), 2 IRs, 2 Servos, 4 Slots, 1 Smoke
 */

#include <Wire.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// === PINS ===
#define IR_IN_PIN 2
#define IR_OUT_PIN 15
#define SERVO_IN_PIN 5
#define SERVO_OUT_PIN 4
#define SMOKE_PIN 32

// I2C Entrance (IN) - pins 21,22
#define SDA_ENTRANCE 21
#define SCL_ENTRANCE 22

// I2C Exit (OUT) - pins 18,19
#define SDA_EXIT 18
#define SCL_EXIT 19

const int SLOT_PINS[4] = {25, 26, 33, 14};

// === OLED ===
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_ADDR 0x3C

TwoWire I2C_entrance = TwoWire(0);
TwoWire I2C_exit = TwoWire(1);

Adafruit_SSD1306 oled_in(SCREEN_WIDTH, SCREEN_HEIGHT, &I2C_entrance, -1);
Adafruit_SSD1306 oled_out(SCREEN_WIDTH, SCREEN_HEIGHT, &I2C_exit, -1);

// === CONFIG ===
const char* WIFI_SSID = "MUOI CA PHE CN2";
const char* WIFI_PASS = "68686868";
const char* MQTT_SERVER = "192.168.1.127";
const int MQTT_PORT = 1883;

// === OBJECTS ===
WiFiClient espClient;
PubSubClient mqtt(espClient);
Servo servoIn;
Servo servoOut;

// === TIMING ===
const unsigned long IR_STABLE_TIME = 500;
const unsigned long SLOT_MONITOR_TIMEOUT = 10000;
const unsigned long STATUS_REPORT_INTERVAL = 5000;
const unsigned long AUTO_CLOSE_DELAY = 500;
const unsigned long PROCESSING_TIMEOUT = 15000;
const unsigned long BARRIER_TIMEOUT = 20000;
const unsigned long VERIFY_TIMEOUT = 30000;
const int SMOKE_THRESHOLD = 6000;

// === STATE IN ===
enum StateIn {
  IN_IDLE,
  IN_PROCESSING_ENTRY,
  IN_WAITING_FOR_SLOT,
  IN_BARRIER_OPEN_WAITING_PASS
};
StateIn stateIn = IN_IDLE;

// === STATE OUT ===
enum StateOut {
  OUT_IDLE,
  OUT_WAITING_VERIFY,
  OUT_BARRIER_OPEN
};
StateOut stateOut = OUT_IDLE;

// === VARIABLES ===
bool barrierInOpen = false;
bool barrierOutOpen = false;
bool carPassedIn = false;
bool carPassedOut = false;
bool emergencyActive = false;
bool isMonitoringSlots = false;

unsigned long stateInStartTime = 0;
unsigned long stateOutStartTime = 0;
unsigned long carPassTimeIn = 0;
unsigned long carPassTimeOut = 0;
unsigned long lastDetectOut = 0;
unsigned long slotMonitorStartTime = 0;
unsigned long lastStatusReport = 0;

int emptySlotsToMonitor = 0;
int slotsToMonitor[4];

// === TOPICS ===
const char* T_ENTRANCE = "xparking/gate1/entrance";
const char* T_EXIT = "xparking/gate1/exit";
const char* T_SLOTS = "xparking/gate1/slots";
const char* T_ALERT = "xparking/gate1/alert";
const char* T_CMD = "xparking/gate1/command";
const char* T_STATUS = "xparking/gate1/status";

// === FORWARD DECLARATIONS ===
void showOLED(Adafruit_SSD1306 &oled, String l1, String l2 = "");
void openBarrierIn();
void closeBarrierIn();
void openBarrierOut();
void closeBarrierOut();
void publishMessage(String topic, String event, String data = "");
void publishStatus();
void checkSmokeSensor();
void onMessage(char* topic, byte* payload, unsigned int len);
void reconnect();
void handleIRIn();
void handleIROut();
void handleAutoCloseBarriers();
void handleSlotMonitoring();
void handleStatusReporting();
void resetInToIdle();
void resetOutToIdle();

// === OLED ===
void showOLED(Adafruit_SSD1306 &oled, String l1, String l2) {
  oled.clearDisplay();
  oled.setTextSize(1);
  oled.setTextColor(WHITE);
  
  int16_t x, y;
  uint16_t w, h;
  oled.getTextBounds(l1, 0, 0, &x, &y, &w, &h);
  oled.setCursor((SCREEN_WIDTH - w) / 2, 15);
  oled.println(l1);
  
  if (l2.length() > 0) {
    oled.getTextBounds(l2, 0, 0, &x, &y, &w, &h);
    oled.setCursor((SCREEN_WIDTH - w) / 2, 35);
    oled.println(l2);
  }
  oled.display();
}

// === BARRIER ===
void openBarrierIn() {
  if (!barrierInOpen) {
    servoIn.write(90);
    barrierInOpen = true;
    carPassedIn = false;
    Serial.println("[IN] Barrier OPEN");
  }
}

void closeBarrierIn() {
  if (barrierInOpen) {
    servoIn.write(0);
    barrierInOpen = false;
    Serial.println("[IN] Barrier CLOSE");
    if (!emergencyActive) showOLED(oled_in, "X PARKING", "Entrance");
  }
}

void openBarrierOut() {
  if (!barrierOutOpen) {
    servoOut.write(90);
    barrierOutOpen = true;
    carPassedOut = false;
    Serial.println("[OUT] Barrier OPEN");
  }
}

void closeBarrierOut() {
  if (barrierOutOpen) {
    servoOut.write(0);
    barrierOutOpen = false;
    Serial.println("[OUT] Barrier CLOSE");
    if (!emergencyActive) showOLED(oled_out, "X PARKING", "Exit");
  }
}

// === MQTT ===
void publishMessage(String topic, String event, String data) {
  StaticJsonDocument<300> doc;
  doc["event"] = event;
  doc["station"] = (topic == T_ENTRANCE) ? "IN" : (topic == T_EXIT) ? "OUT" : "GATE1";
  doc["timestamp"] = millis();
  if (data != "") doc["data"] = data;
  
  char buffer[400];
  serializeJson(doc, buffer);
  mqtt.publish(topic.c_str(), buffer);
}

void publishStatus() {
  int occupiedSlotsCount = 0;
  for (int i = 0; i < 4; i++) {
    if (digitalRead(SLOT_PINS[i]) == LOW) occupiedSlotsCount++;
  }
  
  StaticJsonDocument<700> doc;
  doc["event"] = "STATUS_REPORT";
  doc["station"] = "GATE1";
  doc["state_in"] = (int)stateIn;
  doc["state_out"] = (int)stateOut;
  doc["emergency"] = emergencyActive;
  doc["barrier_in_open"] = barrierInOpen;
  doc["barrier_out_open"] = barrierOutOpen;
  doc["monitoring_slots"] = isMonitoringSlots;
  doc["occupied_slots"] = occupiedSlotsCount;
  doc["available_slots"] = 4 - occupiedSlotsCount;
  doc["smoke_level"] = analogRead(SMOKE_PIN);
  doc["timestamp"] = millis();
  
  JsonArray slots = doc.createNestedArray("slot_status");
  for (int i = 0; i < 4; i++) {
    JsonObject slot = slots.createNestedObject();
    slot["id"] = String("A0") + String(i + 1);
    slot["occupied"] = digitalRead(SLOT_PINS[i]) == LOW;
  }
  
  char buffer[800];
  serializeJson(doc, buffer);
  mqtt.publish(T_STATUS, buffer);
}

void checkSmokeSensor() {
  static unsigned long lastCheck = 0;
  if (millis() - lastCheck < 2000) return;
  lastCheck = millis();
  
  int val = analogRead(SMOKE_PIN);
  
  if (val > SMOKE_THRESHOLD && !emergencyActive) {
    emergencyActive = true;
    Serial.println("[GATE1] KHAN CAP - KHOI");
    publishMessage(T_ALERT, "EMERGENCY_SMOKE", String(val));
    openBarrierIn();
    openBarrierOut();
    showOLED(oled_in, "!!! KHAN CAP !!!", "PHAT HIEN KHOI");
    showOLED(oled_out, "!!! KHAN CAP !!!", "PHAT HIEN KHOI");
  } else if (val <= SMOKE_THRESHOLD - 500 && emergencyActive) {
    emergencyActive = false;
    Serial.println("[GATE1] Het khan cap");
    publishMessage(T_ALERT, "EMERGENCY_CLEAR", String(val));
    closeBarrierIn();
    closeBarrierOut();
    showOLED(oled_in, "X PARKING", "Entrance");
    showOLED(oled_out, "X PARKING", "Exit");
  }
}

void onMessage(char* topic, byte* payload, unsigned int len) {
  String msg = "";
  for (int i = 0; i < len; i++) msg += (char)payload[i];
  
  StaticJsonDocument<500> doc;
  if (deserializeJson(doc, msg)) return;
  
  String event = doc["event"] | "";
  String station = doc["station"] | "";
  
  if (station == "IN" || event == "OPEN_BARRIER" || event == "MONITOR_SLOTS" || event == "SHOW_MESSAGE_IN") {
    if (event == "OPEN_BARRIER") {
      openBarrierIn();
      stateIn = IN_BARRIER_OPEN_WAITING_PASS;
      stateInStartTime = millis();
      publishMessage(T_ENTRANCE, "BARRIER_OPENED");
    }
    else if (event == "MONITOR_SLOTS") {
      isMonitoringSlots = true;
      slotMonitorStartTime = millis();
      if (stateIn != IN_BARRIER_OPEN_WAITING_PASS) stateIn = IN_WAITING_FOR_SLOT;
      emptySlotsToMonitor = 0;
      
      if (doc.containsKey("slots") && doc["slots"].is<JsonArray>()) {
        JsonArray slots = doc["slots"].as<JsonArray>();
        for (JsonVariant slot : slots) {
          String slot_id = slot.as<String>();
          for (int i = 0; i < 4; i++) {
            if (slot_id == "A0" + String(i + 1)) {
              if (emptySlotsToMonitor < 4) {
                slotsToMonitor[emptySlotsToMonitor++] = SLOT_PINS[i];
              }
              break;
            }
          }
        }
      }
    }
    else if (event == "SHOW_MESSAGE_IN") {
      showOLED(oled_in, doc["line1"] | "", doc["line2"] | "");
    }
    else if (event == "RESET_IN") {
      resetInToIdle();
    }
    else if (event == "STOP_SLOT_MONITOR") {
      isMonitoringSlots = false;
      if (!emergencyActive) stateIn = IN_IDLE;
      emptySlotsToMonitor = 0;
    }
  }
  else if (station == "OUT" || event == "DISPLAY_OUT" || event == "BARRIER_OUT") {
    if (event == "DISPLAY_OUT") {
      showOLED(oled_out, doc["line1"] | "", doc["line2"] | "");
    }
    else if (event == "BARRIER_OUT") {
      String action = doc["action"] | "";
      if (action == "open") {
        openBarrierOut();
        stateOut = OUT_BARRIER_OPEN;
        stateOutStartTime = millis();
        carPassedOut = false;
        showOLED(oled_out, "TAM BIET", "HEN GAP LAI");
      }
      else if (action == "close") {
        resetOutToIdle();
      }
    }
    else if (event == "RESET_OUT") {
      resetOutToIdle();
    }
  }
}

void handleIRIn() {
  bool currentIR = digitalRead(IR_IN_PIN) == LOW;
  static bool lastIR = false;
  static unsigned long stableStart = 0;
  
  if (currentIR != lastIR) {
    lastIR = currentIR;
    stableStart = millis();
    return;
  }
  if (millis() - stableStart < IR_STABLE_TIME) return;

  if (barrierInOpen && !currentIR && !carPassedIn) {
    carPassedIn = true;
    carPassTimeIn = millis();
    Serial.println("[IN] Xe qua IR");
    publishMessage(T_ENTRANCE, "CAR_PASSED_IR");
  }
  
  switch (stateIn) {
    case IN_IDLE:
      if (currentIR) {
        stateIn = IN_PROCESSING_ENTRY;
        stateInStartTime = millis();
        Serial.println("[IN] Xe vao");
        publishMessage(T_ENTRANCE, "CAR_DETECT_IN");
        showOLED(oled_in, "NHAN DIEN BSX", "VUI LONG CHO");
      }
      break;
    
    case IN_PROCESSING_ENTRY:
      if (!currentIR) {
        Serial.println("[IN] Xe lui ra");
        publishMessage(T_ENTRANCE, "CAR_REVERSE");
        resetInToIdle();
      }
      else if (millis() - stateInStartTime > PROCESSING_TIMEOUT) {
        Serial.println("[IN] Timeout");
        publishMessage(T_ENTRANCE, "ENTRY_TIMEOUT");
        resetInToIdle();
      }
      break;
      
    case IN_BARRIER_OPEN_WAITING_PASS:
      if (millis() - stateInStartTime > BARRIER_TIMEOUT) {
        Serial.println("[IN] Timeout barrier");
        publishMessage(T_ENTRANCE, "BARRIER_TIMEOUT");
        resetInToIdle();
      }
      break;
      
    default:
      break;
  }
}

void handleIROut() {
  static bool lastIR = false;
  static unsigned long lastChange = 0;
  
  bool ir = digitalRead(IR_OUT_PIN) == LOW;
  if (ir != lastIR) {
    lastIR = ir;
    lastChange = millis();
    return;
  }
  if (millis() - lastChange < IR_STABLE_TIME) return;
  
  if (barrierOutOpen && !ir && !carPassedOut) {
    carPassedOut = true;
    carPassTimeOut = millis();
    Serial.println("[OUT] Xe qua IR");
  }
  
  switch (stateOut) {
    case OUT_IDLE:
      if (ir && millis() - lastDetectOut > 2000) {
        lastDetectOut = millis();
        stateOut = OUT_WAITING_VERIFY;
        stateOutStartTime = millis();
        publishMessage(T_EXIT, "CAR_DETECT");
        showOLED(oled_out, "QUET VE", "DE RA BAI");
        Serial.println("[OUT] Xe ra");
      }
      break;
      
    case OUT_WAITING_VERIFY:
      if (!ir) {
        Serial.println("[OUT] Xe lui ra");
        publishMessage(T_EXIT, "CAR_REVERSE");
        resetOutToIdle();
      }
      else if (millis() - stateOutStartTime > VERIFY_TIMEOUT) {
        Serial.println("[OUT] Timeout verify");
        publishMessage(T_EXIT, "VERIFY_TIMEOUT");
        resetOutToIdle();
      }
      break;
      
    case OUT_BARRIER_OPEN:
      if (millis() - stateOutStartTime > BARRIER_TIMEOUT) {
        Serial.println("[OUT] Timeout barrier");
        publishMessage(T_EXIT, "BARRIER_TIMEOUT");
        resetOutToIdle();
      }
      break;
  }
}

void handleAutoCloseBarriers() {
  if (barrierInOpen && carPassedIn && millis() - carPassTimeIn >= AUTO_CLOSE_DELAY) {
    closeBarrierIn();
    if (!emergencyActive && (stateIn == IN_WAITING_FOR_SLOT || stateIn == IN_BARRIER_OPEN_WAITING_PASS)) {
      stateIn = IN_IDLE;
    }
    carPassedIn = false;
  }
  
  if (barrierOutOpen && carPassedOut && millis() - carPassTimeOut >= AUTO_CLOSE_DELAY) {
    closeBarrierOut();
    publishMessage(T_EXIT, "CAR_EXITED");
    Serial.println("[OUT] Xe da ra");
    carPassedOut = false;
    stateOut = OUT_IDLE;
    showOLED(oled_out, "X PARKING", "Exit");
  }
}

void handleSlotMonitoring() {
  if (!isMonitoringSlots) return;
  
  if (millis() - slotMonitorStartTime > SLOT_MONITOR_TIMEOUT) {
    Serial.println("[GATE1] Slot monitor timeout");
    isMonitoringSlots = false;
    if (!emergencyActive) stateIn = IN_IDLE;
    emptySlotsToMonitor = 0;
    publishMessage(T_SLOTS, "MONITOR_TIMEOUT");
    return;
  }
  
  for (int i = 0; i < emptySlotsToMonitor; i++) {
    if (digitalRead(slotsToMonitor[i]) == LOW) {
      delay(200);
      if (digitalRead(slotsToMonitor[i]) == LOW) {
        for (int j = 0; j < 4; j++) {
          if (slotsToMonitor[i] == SLOT_PINS[j]) {
            String slotId = "A0" + String(j + 1);
            Serial.print("[GATE1] Xe vao slot: "); Serial.println(slotId);
            publishMessage(T_SLOTS, "CAR_ENTERED_SLOT", slotId);
            isMonitoringSlots = false;
            emptySlotsToMonitor = 0;
            if (!emergencyActive) stateIn = IN_IDLE;
            return;
          }
        }
      }
    }
  }
}

void handleStatusReporting() {
  if (millis() - lastStatusReport >= STATUS_REPORT_INTERVAL) {
    lastStatusReport = millis();
    publishStatus();
  }
}

void resetInToIdle() {
  stateIn = IN_IDLE;
  isMonitoringSlots = false;
  emptySlotsToMonitor = 0;
  carPassedIn = false;
  if (barrierInOpen && !emergencyActive) closeBarrierIn();
  showOLED(oled_in, "X PARKING", "Entrance");
}

void resetOutToIdle() {
  stateOut = OUT_IDLE;
  carPassedOut = false;
  if (barrierOutOpen && !emergencyActive) closeBarrierOut();
  showOLED(oled_out, "X PARKING", "Exit");
}

void reconnect() {
  while (!mqtt.connected()) {
    Serial.print("[GATE1] MQTT...");
    String id = "ESP32GATE1-" + String(random(0xffff), HEX);
    if (mqtt.connect(id.c_str())) {
      Serial.println("OK");
      mqtt.subscribe(T_CMD);
      mqtt.subscribe(T_ALERT);
      publishMessage(T_STATUS, "ONLINE");
    } else {
      Serial.println("FAIL");
      delay(2000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n[ESP32_GATE1] XPARKING v2.0");
  
  pinMode(IR_IN_PIN, INPUT_PULLUP);
  pinMode(IR_OUT_PIN, INPUT_PULLUP);
  pinMode(SMOKE_PIN, INPUT);
  for (int i = 0; i < 4; i++) pinMode(SLOT_PINS[i], INPUT_PULLUP);
  
  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  servoIn.setPeriodHertz(50);
  servoIn.attach(SERVO_IN_PIN, 500, 2500);
  servoOut.setPeriodHertz(50);
  servoOut.attach(SERVO_OUT_PIN, 500, 2500);
  servoIn.write(0);
  servoOut.write(0);
  
  I2C_entrance.begin(SDA_ENTRANCE, SCL_ENTRANCE, 100000);
  I2C_exit.begin(SDA_EXIT, SCL_EXIT, 100000);
  
  if (!oled_in.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR, false, false)) {
    Serial.println("[GATE1] OLED IN fail");
    while(1);
  }
  if (!oled_out.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR, false, false)) {
    Serial.println("[GATE1] OLED OUT fail");
    while(1);
  }
  
  showOLED(oled_in, "X PARKING", "Starting...");
  showOLED(oled_out, "X PARKING", "Starting...");
  
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("[GATE1] WiFi");
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println(" OK: " + WiFi.localIP().toString());
  
  mqtt.setServer(MQTT_SERVER, MQTT_PORT);
  mqtt.setCallback(onMessage);
  
  showOLED(oled_in, "X PARKING", "Entrance");
  showOLED(oled_out, "X PARKING", "Exit");
  
  Serial.println("[GATE1] Ready");
}

void loop() {
  if (!mqtt.connected()) reconnect();
  mqtt.loop();
  
  if (!emergencyActive) {
    handleIRIn();
    handleIROut();
    handleAutoCloseBarriers();
    handleSlotMonitoring();
  }
  
  checkSmokeSensor();
  handleStatusReporting();
  
  delay(50);
}
