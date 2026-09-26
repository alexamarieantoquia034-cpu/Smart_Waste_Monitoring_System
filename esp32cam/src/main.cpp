/*
 * ESP32-CAM firmware for the Smart Waste Monitoring System
 * =====================================================================
 *
 * Built with PlatformIO (platformio.ini at the repository root) and flashed
 * from VS Code. No Arduino IDE required.
 *
 * Two directions of traffic:
 *
 *   1. Camera -> browser. The camera runs its own web server and Laravel
 *      relays it, so the TensorFlow.js model in the browser can read the
 *      pixels off an untainted canvas:
 *
 *        GET /          control page with a live preview
 *        GET /stream    endless multipart/x-mixed-replace MJPEG stream
 *        GET /capture   one JPEG frame (?framesize=FRAMESIZE_VGA)
 *        GET /status    JSON health check, no JPEG allocation
 *
 *   2. Camera -> application. On a timer the firmware POSTs bin levels to
 *      Laravel, which stores a sensor_data row and raises alerts when a bin
 *      fills up. This runs with no browser open, so the dashboard stays live:
 *
 *        POST /api/device/telemetry   bin levels + device health
 *
 * Put the printed IP in the Laravel .env to enable direction 1:
 *
 *        ESP32CAM_URL=http://192.168.1.50:81
 *
 * ---------------------------------------------------------------------
 * Board wiring
 * ---------------------------------------------------------------------
 * The default is the AI-Thinker ESP32-CAM. For another board define
 * BOARD_ESP32S3_WROOM or BOARD_ESP32S3_XIAO in platformio.ini and copy the
 * matching camera_pins.h out of the ESP32 Arduino core into esp32cam/src.
 *
 * The AI-Thinker board needs the 5V jumper moved from 3V3 to 5V and a stable
 * 2x 18650 pack, not a 3.3V supply: the OV2640 browns out below ~4.5V.
 * PSRAM is required.
 *
 * ---------------------------------------------------------------------
 * Fill level sensor
 * ---------------------------------------------------------------------
 * One HC-SR04 can be wired to TRIG/ECHO below to report the level of the
 * compartment named in ULTRASONIC_COMPARTMENT. Leave ENABLE_ULTRASONIC at 0
 * if no sensor is wired; the camera still streams and still reports device
 * health, it just has no level to report.
 */

#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <esp_camera.h>
#include <esp_http_server.h>
#include <soc/rtc_cntl_reg.h>
#include <driver/ledc.h>

#include "secrets.h"

// ----------------------------------------------------------------- Wi-Fi
#ifndef WIFI_SSID
#error "Create esp32cam/include/secrets.h from secrets.example.h and set your Wi-Fi details."
#endif

// ----------------------------------------------------------------- camera
#define FRAME_SIZE    FRAMESIZE_VGA   // default for both /stream and /capture
#define JPEG_QUALITY  12              // 10 = best quality, 63 = smallest

// The stream lives on its own port so the control page stays responsive.
#define HTTP_PORT     81

WebServer server(HTTP_PORT);

// -------------------------------------------------------- fill sensor
// Set to 1 once an HC-SR04 is wired. These pins are unused by the camera on
// the AI-Thinker board.
#define ENABLE_ULTRASONIC   0
#define ULTRASONIC_TRIG     13
#define ULTRASONIC_ECHO     12
#define ULTRASONIC_COMPARTMENT "reject"

// Distance (cm) from the sensor to an empty bin and to a completely full one.
#define BIN_EMPTY_CM        60.0f
#define BIN_FULL_CM         8.0f

// ------------------------------------------------------ device reporting
#define FIRMWARE_VERSION    "1.0.0"
#define TELEMETRY_INTERVAL_MS 10000UL
// Give up on the POST quickly so a dead server never stalls the stream.
#define TELEMETRY_TIMEOUT_MS 4000UL

static unsigned long lastTelemetryMs = 0;
static bool telemetryOk = false;
static float lastReportedLevel = -1.0f;

// ----------------------------------------------------------------- helpers
static const char* indexHtml = R"rawliteral(
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Smart Waste ESP32-CAM</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#14061c;color:#fff;
       margin:0;padding:1.5rem;text-align:center}
  h1{font-size:1.1rem;margin:0 0 1rem}
  img{max-width:100%;border-radius:12px;background:#000}
  a{display:inline-block;margin-top:1rem;padding:.5rem 1rem;border-radius:8px;
    background:#e6007e;color:#fff;text-decoration:none;font-size:.8rem}
  code{color:#ff8ac2}
</style></head><body>
<h1>Smart Waste &middot; ESP32-CAM</h1>
<img src="http://%s:%d/stream">
<br><a href="/capture">Single frame</a>
<p style="font-size:.75rem;color:#c9a">Firmware %s &middot; <code>/status</code></p>
</body></html>
)rawliteral";

// ------------------------------------------------------------------ camera
static void configureCamera() {
  camera_config_t config;

  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = 5;
  config.pin_d1 = 18;
  config.pin_d2 = 19;
  config.pin_d3 = 21;
  config.pin_d4 = 36;
  config.pin_d5 = 39;
  config.pin_d6 = 34;
  config.pin_d7 = 35;
  config.pin_d8 = 32;
  config.pin_xclk = 0;
  config.pin_pclk = 22;
  config.pin_vsync = 25;
  config.pin_href = 26;
  config.pin_sccb_sda = 23;
  config.pin_sccb_scl = 27;
  config.pin_pwdn = 32;
  config.pin_reset = -1;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  config.frame_size = FRAME_SIZE;
  config.jpeg_quality = JPEG_QUALITY;
  config.fb_count = 2;          // one buffer being encoded, one being sent
  config.fb_location = CAMERA_FB_IN_PSRAM;
  config.grab_mode = CAMERA_GRAB_LATEST;

  esp_error_t err = esp_camera_init(&config);

  if (err != ESP_OK) {
    Serial.printf("Camera init failed: 0x%x\n", err);
    Serial.println("Check the board define, the PSRAM jumper and the 5V rail.");
    delay(10000);
    ESP.restart();
  }

  // Drop the sensor's default 12 MP down to something the model can use.
  sensor_t *s = esp_camera_sensor_get();
  if (s != NULL) {
    s->set_framesize(s, FRAME_SIZE);
    s->set_quality(s, JPEG_QUALITY);
    s->set_brightness(s, 1);    // +1 lifts the dark chute interior
    s->set_saturation(s, 0);
  }

  Serial.printf("Camera ready, frame size = %d\n", (int) FRAME_SIZE);
}

// --------------------------------------------------------- fill sensor
static float readFillLevel() {
#if ENABLE_ULTRASONIC
  // HC-SR04: 10us trigger pulse, then wait for the echo to go high.
  digitalWrite(ULTRASONIC_TRIG, LOW);
  delayMicroseconds(3);
  digitalWrite(ULTRASONIC_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(ULTRASONIC_TRIG, LOW);

  unsigned long duration = pulseIn(ULTRASONIC_ECHO, HIGH, 30000UL);

  if (duration == 0) {
    Serial.println("Ultrasonic read timed out; keeping the last value.");
    return lastReportedLevel < 0 ? 0.0f : lastReportedLevel;
  }

  float cm = duration * 0.0343f / 2.0f;

  if (cm > BIN_EMPTY_CM) cm = BIN_EMPTY_CM;
  if (cm < BIN_FULL_CM)  cm = BIN_FULL_CM;

  // Closer means fuller: invert the distance into a 0-100 percentage.
  float level = (BIN_EMPTY_CM - cm) / (BIN_EMPTY_CM - BIN_FULL_CM) * 100.0f;

  return constrain(level, 0.0f, 100.0f);
#else
  return 0.0f;
#endif
}

// ------------------------------------------------------- device reporting
// The compartment the fill sensor is wired to, as the server expects it.
#if ENABLE_ULTRASONIC
  #define REPORTED_COMPARTMENT ULTRASONIC_COMPARTMENT
#else
  #define REPORTED_COMPARTMENT "reject"
#endif

static void reportTelemetry() {
  float level = readFillLevel();

  if (level >= 0.0f) {
    lastReportedLevel = level;
  }

  char url[192];
  snprintf(url, sizeof(url), "%s/api/device/telemetry", SERVER_BASE_URL);

  HTTPClient http;
  http.setTimeout(TELEMETRY_TIMEOUT_MS);

  if (!http.begin(url)) {
    Serial.println("[telemetry] could not start the request");
    return;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_API_KEY);

  // Built by hand: ArduinoJson is not bundled and the payload is a fixed set
  // of numbers, so a fixed-size buffer is the simpler option.
  char body[512];
  snprintf(
    body, sizeof(body),
    "{\"device_id\":\"%s\",\"firmware\":\"%s\",\"uptime_ms\":%lu,"
    "\"rssi\":%d,\"ip\":\"%s\",\"%s_level\":%.1f}",
    DEVICE_ID,
    FIRMWARE_VERSION,
    millis(),
    WiFi.RSSI(),
    WiFi.localIP().toString().c_str(),
    REPORTED_COMPARTMENT,
    lastReportedLevel < 0.0f ? 0.0f : lastReportedLevel
  );

  int code = http.POST((uint8_t *) body, strlen(body));

  if (code > 0) {
    telemetryOk = true;
    Serial.printf("[telemetry] %d -> %s\n", code, http.getString().c_str());
  } else {
    telemetryOk = false;
    Serial.printf("[telemetry] failed: %s\n", http.errorToString(code).c_str());
  }

  http.end();
}

// ------------------------------------------------------------------ routes
static void handleRoot() {
  char page[900];
  snprintf(page, sizeof(page), indexHtml,
           WiFi.localIP().toString().c_str(), HTTP_PORT, FIRMWARE_VERSION);
  server.send(200, "text/html", page);
}

static void handleStatus() {
  char json[360];
  snprintf(json, sizeof(json),
           "{\"device_id\":\"%s\",\"firmware\":\"%s\",\"ip\":\"%s\",\"rssi\":%d,"
           "\"uptime_ms\":%lu,\"framesize\":\"FRAMESIZE_VGA\","
           "\"compartment\":\"%s\",\"telemetry_ok\":%s,\"last_level\":%.1f}",
           DEVICE_ID, FIRMWARE_VERSION, WiFi.localIP().toString().c_str(),
           WiFi.RSSI(), millis(), REPORTED_COMPARTMENT,
           telemetryOk ? "true" : "false",
           lastReportedLevel);
  server.send(200, "application/json", json);
}

static void handleNotFound() {
  server.send(404, "text/plain", "Not found. Try /stream, /capture or /status");
}

static void handleCapture() {
  // The app requests a specific size so the saved still matches the live feed.
  if (server.hasArg("framesize")) {
    framesize_t size = (framesize_t) atoi(server.arg("framesize").c_str());

    if (size >= FRAMESIZE_QQVGA && size <= FRAMESIZE_UHD) {
      sensor_t *s = esp_camera_sensor_get();
      if (s != NULL) {
        s->set_framesize(s, size);
      }
    }
  }

  camera_fb_t *frame = esp_camera_fb_get();

  if (frame == NULL) {
    server.send(500, "text/plain", "Camera capture failed");
    return;
  }

  server.sendContent("HTTP/1.1 200 OK\r\n"
                     "Content-Type: image/jpeg\r\n"
                     "Content-Length: " + String(frame->len) + "\r\n"
                     "Cache-Control: no-store\r\n"
                     "Connection: close\r\n\r\n");
  server.sendContent_P((PGM_P) frame->buf, frame->len);
  esp_camera_fb_return(frame);
}

static void handleStream() {
  camera_fb_t *frame = NULL;

  // An endless multipart response has no Content-Length.
  server.setContentLength(CONTENT_LENGTH_UNKNOWN);
  server.send(200, "multipart/x-mixed-replace;boundary=frame", "");
  server.sendContent("Cache-Control: no-store\r\n");
  server.sendContent("X-Accel-Buffering: no\r\n\r\n");

  while (true) {
    frame = esp_camera_fb_get();

    if (frame == NULL) {
      Serial.println("Stream halted: camera returned no frame.");
      break;
    }

    server.sendContent("--frame\r\n");
    server.sendContent("Content-Type: image/jpeg\r\n");
    server.sendContent("Content-Length: " + String(frame->len) + "\r\n\r\n");
    server.sendContent_P((PGM_P) frame->buf, frame->len);
    server.sendContent("\r\n");

    esp_camera_fb_return(frame);

    // Stop when the browser goes away, otherwise the loop pins the CPU.
    if (!server.client().connected()) {
      break;
    }
  }
}

// -------------------------------------------------------------------- main
void setup() {
  Serial.begin(115200);
  Serial.println();
  Serial.println("Smart Waste ESP32-CAM starting...");

  // The AI-Thinker regulator browns out on Wi-Fi bursts at 3.3V.
  rtc_clk_poweroff(en_rtc_power_ldo_on);

#if ENABLE_ULTRASONIC
  pinMode(ULTRASONIC_TRIG, OUTPUT);
  pinMode(ULTRASONIC_ECHO, INPUT);
  Serial.printf("HC-SR04 enabled on TRIG=%d ECHO=%d -> %s bin\n",
                ULTRASONIC_TRIG, ULTRASONIC_ECHO, ULTRASONIC_COMPARTMENT);
#else
  Serial.println("No HC-SR04 wired; reporting a zero level.");
#endif

  configureCamera();

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  WiFi.setSleep(false);

  Serial.print("Connecting to ");
  Serial.print(WIFI_SSID);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.print("Connected. Stream at http://");
  Serial.print(WiFi.localIP());
  Serial.print(":");
  Serial.println(HTTP_PORT);
  Serial.println();
  Serial.println("Add this to your Laravel .env file:");
  Serial.print("ESP32CAM_URL=http://");
  Serial.print(WiFi.localIP());
  Serial.println(":81");
  Serial.print("Reporting telemetry to: ");
  Serial.println(SERVER_BASE_URL);

  server.on("/", HTTP_GET, handleRoot);
  server.on("/stream", HTTP_GET, handleStream);
  server.on("/capture", HTTP_GET, handleCapture);
  server.on("/status", HTTP_GET, handleStatus);
  server.onNotFound(handleNotFound);
  server.begin();

  Serial.printf("HTTP server listening on port %d\n", HTTP_PORT);

  // Report once straight away so the dashboard fills in without waiting a
  // full interval for the first push.
  reportTelemetry();
}

void loop() {
  server.handleClient();

  unsigned long now = millis();

  // Subtraction stays correct across the ~49 day millis() wrap, which a
  // `now > last + interval` comparison would not.
  if (now - lastTelemetryMs >= TELEMETRY_INTERVAL_MS) {
    lastTelemetryMs = now;
    reportTelemetry();
  }
}
