/**
 * ESP32_CAM_GATE1 - Camera cho XPARKING Gate 1
 * Chup BSX + QR code gui ve Python qua MQTT
 * Che do tiet kiem: Chi bat len khi co trigger
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include "esp_camera.h"
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// WiFi & MQTT
const char* WIFI_SSID = "MUOI CA PHE CN2";
const char* WIFI_PASS = "68686868";
const char* MQTT_SERVER = "192.168.1.127";
const int MQTT_PORT = 1883;
const int MQTT_BUFFER = 50000;

// Camera pins (AI-Thinker)
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

// Flash LED
#define FLASH_GPIO_NUM    4

WiFiClient espClient;
PubSubClient mqtt(espClient);

const char* T_TRIGGER = "xparking/gate1/cam/trigger";
const char* T_IMAGE = "xparking/gate1/cam/image";
const char* T_STATUS = "xparking/gate1/cam/status";

bool captureRequested = false;
bool cameraActive = false;

void setupCamera() {
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  
  // Toi uu cho QR: VGA, quality 12
  config.frame_size = FRAMESIZE_VGA;
  config.jpeg_quality = 12;
  config.fb_count = psramFound() ? 2 : 1;
  
  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("Cam init fail");
    return;
  }
  
  // Toi uu cho QR scan
  sensor_t *s = esp_camera_sensor_get();
  s->set_brightness(s, 0);
  s->set_contrast(s, 1);
  s->set_saturation(s, 0);
  s->set_whitebal(s, 1);
  s->set_awb_gain(s, 1);
  s->set_exposure_ctrl(s, 1);
  s->set_aec_value(s, 300);
  s->set_gain_ctrl(s, 1);
  s->set_hmirror(s, 0);
  s->set_vflip(s, 0);
  
  Serial.println("Cam OK");
}

void onMessage(char* topic, byte* payload, unsigned int len) {
  if (String(topic) == T_TRIGGER && len >= 7) {
    if (strncmp((char*)payload, "capture", 7) == 0) {
      captureRequested = true;
    }
  }
}

void wakeCamera() {
  if (!cameraActive) {
    digitalWrite(PWDN_GPIO_NUM, LOW);
    delay(100);
    cameraActive = true;
    Serial.println("Cam ON");
  }
}

void sleepCamera() {
  if (cameraActive) {
    digitalWrite(PWDN_GPIO_NUM, HIGH);
    cameraActive = false;
    Serial.println("Cam OFF");
  }
}

void captureAndSend() {
  wakeCamera();
  
  // Flash ON
  digitalWrite(FLASH_GPIO_NUM, HIGH);
  delay(100);
  
  Serial.print("Chup anh...");
  camera_fb_t *fb = esp_camera_fb_get();
  
  // Flash OFF
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  if (!fb) {
    Serial.println("FAIL");
    mqtt.publish(T_STATUS, "fail");
    sleepCamera();
    return;
  }
  
  Serial.printf("OK %dKB\n", fb->len / 1024);
  
  // Gui anh qua MQTT
  if (mqtt.publish(T_IMAGE, fb->buf, fb->len)) {
    Serial.println("Gui anh OK");
    mqtt.publish(T_STATUS, "sent");
  } else {
    Serial.println("Gui anh FAIL");
    mqtt.publish(T_STATUS, "send_fail");
  }
  
  esp_camera_fb_return(fb);
  
  // Delay 1s roi chup tiep (cho QR)
  delay(1000);
  
  // Chup anh thu 2 (QR)
  digitalWrite(FLASH_GPIO_NUM, HIGH);
  delay(100);
  
  fb = esp_camera_fb_get();
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  if (fb) {
    Serial.printf("Chup QR: %dKB\n", fb->len / 1024);
    mqtt.publish(T_IMAGE, fb->buf, fb->len);
    esp_camera_fb_return(fb);
  }
  
  sleepCamera();
}

void reconnect() {
  while (!mqtt.connected()) {
    Serial.print("MQTT...");
    String id = "CAM_" + String(random(0xffff), HEX);
    if (mqtt.connect(id.c_str())) {
      Serial.println("OK");
      mqtt.subscribe(T_TRIGGER);
      mqtt.publish(T_STATUS, "online");
    } else {
      Serial.println("FAIL");
      delay(2000);
    }
  }
}

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  Serial.begin(115200);
  Serial.println("\n=== ESP32-CAM GATE1 ===");
  
  // Setup flash LED
  pinMode(FLASH_GPIO_NUM, OUTPUT);
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  // Setup camera power
  pinMode(PWDN_GPIO_NUM, OUTPUT);
  digitalWrite(PWDN_GPIO_NUM, HIGH);  // Start in sleep mode
  
  setupCamera();
  sleepCamera();  // Sleep sau khi init
  
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("WiFi...");
  while (WiFi.status() != WL_CONNECTED) { 
    delay(500); 
    Serial.print("."); 
  }
  Serial.println("OK");
  Serial.println("IP: " + WiFi.localIP().toString());
  
  mqtt.setServer(MQTT_SERVER, MQTT_PORT);
  mqtt.setBufferSize(MQTT_BUFFER);
  mqtt.setCallback(onMessage);
  
  Serial.println("Ready (Sleep mode)\n");
}

void loop() {
  if (!mqtt.connected()) reconnect();
  mqtt.loop();
  
  if (captureRequested) {
    captureRequested = false;
    captureAndSend();
  }
  
  delay(10);
}

