#include <WiFi.h>
#include "esp_camera.h"
#include "esp_http_server.h"

// WiFi
const char* ssid = "MUOI CA PHE CN2";
const char* password = "68686868";

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
  config.fb_count = 1;

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("Camera FAILED!");
    return;
  }
  Serial.println("Camera OK");
}

// Handler: chụp và gửi ảnh
static esp_err_t capture_handler(httpd_req_t *req) {
  Serial.println("Capture request...");
  
  // Bật đèn flash
  digitalWrite(FLASH_GPIO_NUM, HIGH);
  delay(100);
  
  // Chụp ảnh
  camera_fb_t *fb = esp_camera_fb_get();
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  if (!fb) {
    Serial.println("Capture FAILED");
    httpd_resp_send_500(req);
    return ESP_FAIL;
  }
  
  Serial.printf("Captured: %d bytes\n", fb->len);
  
  // Gửi ảnh JPEG
  httpd_resp_set_type(req, "image/jpeg");
  httpd_resp_send(req, (const char *)fb->buf, fb->len);
  
  esp_camera_fb_return(fb);
  return ESP_OK;
}

void startServer() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();
  
  httpd_uri_t uri = {
    .uri = "/capture",
    .method = HTTP_GET,
    .handler = capture_handler
  };
  
  if (httpd_start(&server, &config) == ESP_OK) {
    httpd_register_uri_handler(server, &uri);
    Serial.println("Server started");
  }
}

void setup() {
  Serial.begin(115200);
  Serial.println("\nESP32-CAM Starting...");
  
  // Setup flash
  pinMode(FLASH_GPIO_NUM, OUTPUT);
  digitalWrite(FLASH_GPIO_NUM, LOW);
  
  // Setup camera
  setupCamera();
  
  // Connect WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println("\nWiFi connected!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  
  // Start server
  startServer();
  Serial.println("Ready!");
}

void loop() {
  delay(100);
}