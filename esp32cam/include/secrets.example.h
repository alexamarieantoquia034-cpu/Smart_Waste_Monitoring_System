/*
 * Wi-Fi and device credentials for the ESP32-CAM firmware.
 *
 * Copy this file to secrets.h and fill it in:
 *
 *   cp esp32cam/include/secrets.example.h esp32cam/include/secrets.h
 *
 * secrets.h is gitignored, so your Wi-Fi password and device key never reach
 * GitHub or a Railway build. The firmware will not compile until you create it.
 */
#pragma once

#define WIFI_SSID     "YOUR_WIFI_SSID"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"

/*
 * Where the Laravel application lives, as seen from the camera.
 *
 * Use your PC's LAN address, never "localhost" — the ESP32 resolves that to
 * itself. The port must match whichever server you run:
 *   php artisan serve            -> 8000
 *   XAMPP Apache (default)       -> 80
 */
#define SERVER_BASE_URL "http://192.168.1.50:8000"

/*
 * Must match DEVICE_API_KEY in the Laravel .env file. Print the value of
 * .env's DEVICE_API_KEY here; the generated one is:
 *   lruf3n4bzpom92g80ek61xh7swvay5qdicjt
 */
#define DEVICE_API_KEY  "lruf3n4bzpom92g80ek61xh7swvay5qdicjt"

/* Stable identifier for this unit; shows up in the server logs. */
#define DEVICE_ID      "esp32cam-01"
