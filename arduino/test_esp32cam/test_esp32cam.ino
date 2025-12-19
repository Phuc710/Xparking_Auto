#include <WiFi.h>
#include "esp_camera.h"
#include "esp_http_server.h"
#include "esp_timer.h"
#include "esp_wifi.h"
#include "lwip/sockets.h"

// WiFi
const char* ssid = "MUOI CA PHE CN2";
const char* password = "68686868";

// Cấu hình IP tĩnh - ĐỔI CHO KHỚP VỚI SUBNET 10.105.11.x
IPAddress local_IP(192, 168, 1, 205);   // IP tĩnh cho ESP32
IPAddress gateway(192, 168, 1, 1);      // Gateway từ ipconfig
IPAddress subnet(255, 255, 255, 0);
IPAddress dns(8, 8, 8, 8);

// Camera pins AI-THINKER
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
#define FLASH_GPIO_NUM     4

httpd_handle_t server = NULL;
unsigned long lastWifiCheck = 0;
const unsigned long WIFI_CHECK_INTERVAL = 30000;
int captureCount = 0;
unsigned long totalCaptureTime = 0;

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
  config.frame_size = FRAMESIZE_SVGA;  // 800x600 - better quality
  config.jpeg_quality = 10;  // 10-12 optimal for LPR
  config.fb_count = 2;
  config.grab_mode = CAMERA_GRAB_LATEST;

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("Camera FAILED!");
    return;
  }

  sensor_t* s = esp_camera_sensor_get();
  if (s) {
    s->set_brightness(s, 0);
    s->set_contrast(s, 0);
    s->set_saturation(s, 0);
    s->set_sharpness(s, 0);
    s->set_whitebal(s, 1);
    s->set_awb_gain(s, 1);
    s->set_wb_mode(s, 0);
    s->set_exposure_ctrl(s, 1);
    s->set_aec2(s, 0);
    s->set_ae_level(s, 0);
    s->set_aec_value(s, 300);
    s->set_gain_ctrl(s, 1);
    s->set_agc_gain(s, 0);
    s->set_gainceiling(s, (gainceiling_t)0);
    s->set_bpc(s, 0);
    s->set_wpc(s, 1);
    s->set_raw_gma(s, 1);
    s->set_lenc(s, 1);
    s->set_hmirror(s, 0);
    s->set_vflip(s, 0);
    s->set_dcw(s, 1);
    s->set_colorbar(s, 0);
  }

  Serial.print("Warming up camera");
  for (int i = 0; i < 5; i++) {
    camera_fb_t *fb = esp_camera_fb_get();
    if (fb) {
      esp_camera_fb_return(fb);
      Serial.print(".");
    }
    delay(100);
  }
  Serial.println(" OK");
  Serial.println("Camera OK");
}

static esp_err_t capture_handler(httpd_req_t *req) {
  unsigned long startTime = millis();
  Serial.println("\n=== CAPTURE REQUEST ===");

  for (int i = 0; i < 2; i++) {
    camera_fb_t *old = esp_camera_fb_get();
    if (old) esp_camera_fb_return(old);
    delay(20);
  }

  digitalWrite(FLASH_GPIO_NUM, HIGH);
  delay(80);

  camera_fb_t *fb = esp_camera_fb_get();
  digitalWrite(FLASH_GPIO_NUM, LOW);

  if (!fb) {
    Serial.println("✗ Capture FAILED");
    httpd_resp_send_500(req);
    return ESP_FAIL;
  }

  captureCount++;
  unsigned long captureTime = millis() - startTime;
  totalCaptureTime += captureTime;

  Serial.printf("✓ Captured: %d bytes (%.1f KB) in %lu ms\n", 
                fb->len, fb->len/1024.0, captureTime);

  httpd_resp_set_type(req, "image/jpeg");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  httpd_resp_set_hdr(req, "Cache-Control", "no-cache, no-store, must-revalidate");
  httpd_resp_set_hdr(req, "Connection", "keep-alive");

  esp_err_t res = httpd_resp_send(req, (const char *)fb->buf, fb->len);
  esp_camera_fb_return(fb);

  if (res == ESP_OK) {
    Serial.printf("✓ Sent in %lu ms (avg: %lu ms)\n\n", 
                  millis() - startTime, totalCaptureTime / captureCount);
  }

  return res;
}

static esp_err_t status_handler(httpd_req_t *req) {
  char json[300];
  unsigned long uptime = millis() / 1000;
  int avgTime = captureCount > 0 ? totalCaptureTime / captureCount : 0;

  snprintf(json, sizeof(json), 
    "{\"ip\":\"%s\",\"rssi\":%d,\"captures\":%d,\"avg_time\":%d,\"uptime\":%lu,\"heap\":%d}", 
    WiFi.localIP().toString().c_str(),
    WiFi.RSSI(),
    captureCount,
    avgTime,
    uptime,
    ESP.getFreeHeap()
  );

  httpd_resp_set_type(req, "application/json");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  httpd_resp_send(req, json, strlen(json));
  return ESP_OK;
}

void startServer() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();
  config.server_port = 80;
  config.ctrl_port = 32768;
  config.max_open_sockets = 7;
  config.max_uri_handlers = 8;
  config.max_resp_headers = 8;
  config.backlog_conn = 5;
  config.lru_purge_enable = true;
  config.recv_wait_timeout = 10;
  config.send_wait_timeout = 10;
  config.stack_size = 8192;
  config.task_priority = 5;

  httpd_uri_t capture_uri = {
    .uri = "/capture",
    .method = HTTP_GET,
    .handler = capture_handler
  };

  httpd_uri_t status_uri = {
    .uri = "/status",
    .method = HTTP_GET,
    .handler = status_handler
  };

  if (httpd_start(&server, &config) == ESP_OK) {
    httpd_register_uri_handler(server, &capture_uri);
    httpd_register_uri_handler(server, &status_uri);
    Serial.println("✓ Server started");
  }
}

void checkWiFi() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("\n[WARN] WiFi disconnected! Reconnecting...");
    WiFi.disconnect();
    WiFi.begin(ssid, password);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\n[OK] WiFi reconnected!");
      Serial.printf("IP: %s | Signal: %d dBm\n", 
                    WiFi.localIP().toString().c_str(), WiFi.RSSI());
    } else {
      Serial.println("\n[ERR] WiFi reconnection failed!");
      ESP.restart();
    }
  }
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n================================");
  Serial.println("ESP32-CAM OPTIMIZED v2.0");
  Serial.println("================================\n");

  pinMode(FLASH_GPIO_NUM, OUTPUT);
  digitalWrite(FLASH_GPIO_NUM, LOW);

  setupCamera();

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  esp_wifi_set_ps(WIFI_PS_NONE);

  if (!WiFi.config(local_IP, gateway, subnet, dns)) {
    Serial.println("✗ Static IP config failed!");
  } else {
    Serial.println("✓ Static IP configured");
  }

  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("\n✗ WiFi connection failed!");
    ESP.restart();
  }

  Serial.println("\n✓ WiFi connected!");
  Serial.printf("IP: %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("Signal: %d dBm\n", WiFi.RSSI());
  Serial.printf("Heap: %d bytes\n\n", ESP.getFreeHeap());

  startServer();

  Serial.println("================================");
  Serial.println("READY FOR HIGH-SPEED CAPTURE!");
  Serial.println("GET /capture - Capture image");
  Serial.println("GET /status  - System status");
  Serial.println("================================\n");
}

void loop() {
  unsigned long currentMillis = millis();

  if (currentMillis - lastWifiCheck >= WIFI_CHECK_INTERVAL) {
    lastWifiCheck = currentMillis;
    checkWiFi();
  }

  delay(100);
}