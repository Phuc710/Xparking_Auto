/**
 * ESP32-CAM Gate2 - HTTP Server Mode
 * CHỈ DÙNG CHO ENTRY (nhận diện BSX xe vào)
 * Python GET http://192.168.1.202/capture để lấy ảnh JPEG
 * 
 * Endpoints:
 *   GET /capture  - Chụp và trả về ảnh JPEG
 *   GET /status   - Trạng thái camera (JSON)
 */

#include <WiFi.h>
#include "esp_camera.h"
#include "esp_http_server.h"


// ========== CONFIG ==========
const char* ssid = "Wifi chùa";
const char* password = "3thanghe";

// Static IP for Gate 2
IPAddress local_IP(10, 105, 11, 202);    // ĐỔI: 192.168.1.205 → 10.105.11.205
IPAddress gateway(10, 105, 11, 132);     // Gateway của bạn (từ ipconfig)
IPAddress subnet(255, 255, 255, 0);      // Giữ nguyên
IPAddress dns(8, 8, 8, 8);               // Giữ nguyên
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
#define FLASH_GPIO_NUM     4

httpd_handle_t server = NULL;
int captureCount = 0;

// ========== CAMERA SETUP ==========
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
  config.frame_size = FRAMESIZE_VGA;  // 640x480
  config.jpeg_quality = 12;
  config.fb_count = 2;
  config.grab_mode = CAMERA_GRAB_LATEST;

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("Camera FAILED!");
    return;
  }
  
  // Warm up camera - take 5 dummy frames
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
}

// ========== HTTP HANDLER: /capture ==========
static esp_err_t capture_handler(httpd_req_t *req) {
  Serial.println("\n=== CAPTURE REQUEST ===");
  
  // 1. Clear old buffers
  Serial.println("Clearing old buffers...");
  for (int i = 0; i < 3; i++) {
    camera_fb_t *old = esp_camera_fb_get();
    if (old) {
      esp_camera_fb_return(old);
      Serial.printf("  Cleared buffer %d\n", i+1);
    }
    delay(30);
  }
  
  // 2. Flash ON
  digitalWrite(FLASH_GPIO_NUM, HIGH);
  delay(100);
  
  // 3. Capture new frame
  Serial.println("Capturing new image...");
  camera_fb_t *fb = esp_camera_fb_get();
  
  // 4. Flash OFF
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  if (!fb) {
    Serial.println("[ERR] Capture FAILED");
    httpd_resp_send_500(req);
    return ESP_FAIL;
  }
  
  captureCount++;
  Serial.printf("[OK] Captured: %d bytes (#%d)\n", fb->len, captureCount);
  
  // 5. Send JPEG response
  httpd_resp_set_type(req, "image/jpeg");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  esp_err_t res = httpd_resp_send(req, (const char *)fb->buf, fb->len);
  
  // 6. Release buffer
  esp_camera_fb_return(fb);
  
  if (res == ESP_OK) {
    Serial.println("[OK] Image sent\n");
  }
  
  return res;
}

// ========== HTTP HANDLER: /status ==========
static esp_err_t status_handler(httpd_req_t *req) {
  char json[200];
  snprintf(json, sizeof(json),
    "{\"ip\":\"%s\",\"gate\":2,\"captures\":%d,\"rssi\":%d}",
    WiFi.localIP().toString().c_str(),
    captureCount,
    WiFi.RSSI()
  );
  
  httpd_resp_set_type(req, "application/json");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  return httpd_resp_send(req, json, strlen(json));
}

// ========== START HTTP SERVER ==========
void startServer() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();
  
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
    Serial.println("[OK] Server started");
  }
}

// ========== SETUP ==========
void setup() {
  Serial.begin(115200);
  Serial.println("\n================================");
  Serial.println("ESP32-CAM GATE2 - HTTP Server");
  Serial.println("================================\n");
  
  // Flash
  pinMode(FLASH_GPIO_NUM, OUTPUT);
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  // Camera
  setupCamera();
  
  // Static IP
  if (!WiFi.config(local_IP, gateway, subnet, dns)) {
    Serial.println("[WARN] Static IP failed");
  }
  
  // WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println("\n[OK] WiFi connected!");
  Serial.printf("IP: %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("Signal: %d dBm\n\n", WiFi.RSSI());
  
  // Server
  startServer();
  
  Serial.println("================================");
  Serial.println("READY!");
  Serial.println("GET /capture - Capture image");
  Serial.println("GET /status  - Get status");
  Serial.println("================================\n");
}

// ========== LOOP ==========
void loop() {
  delay(100);
}
