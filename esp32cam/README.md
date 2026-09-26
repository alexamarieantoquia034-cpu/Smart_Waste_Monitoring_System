# ESP32-CAM firmware

Built with **PlatformIO** and flashed from **VS Code**. The Arduino IDE is not
used anywhere in this project any more.

`platformio.ini` lives at the repository root (not in this folder) so the
PlatformIO extension detects the project when you open the repository in VS
Code. All sources are still under `esp32cam/`.

| File | Purpose |
| --- | --- |
| `src/main.cpp` | The firmware |
| `include/secrets.example.h` | Template for Wi-Fi and the device key |
| `include/secrets.h` | **Your** credentials — gitignored, never committed |

## What it serves

| Route | Purpose |
| --- | --- |
| `GET /` | Small control page with a live preview |
| `GET /stream` | Endless `multipart/x-mixed-replace` MJPEG stream |
| `GET /capture` | One JPEG frame, accepts `?framesize=FRAMESIZE_VGA` |
| `GET /status` | JSON health check (IP, RSSI, uptime, last level) |

## What it sends

| Route | Purpose |
| --- | --- |
| `POST /api/device/telemetry` | Bin levels + device health, every 10s |
| `GET /api/device/ping` | Confirms the device key before real data flows |

---

## Setup

### 1. Install the extension

In VS Code open this repository, then install **PlatformIO IDE** from the
Extensions panel. `.vscode/extensions.json` already recommends it, so VS Code
offers it on first open.

### 2. Create your secrets file

```
copy esp32cam\include\secrets.example.h esp32cam\include\secrets.h
```

Fill in:

| Value | What to put |
| --- | --- |
| `WIFI_SSID` / `WIFI_PASSWORD` | Your Wi-Fi network |
| `SERVER_BASE_URL` | Where Laravel runs, **as the camera sees it** — `http://192.168.1.50:8000` for `artisan serve`, `http://192.168.1.50` for XAMPP. Never `localhost`: the ESP32 resolves that to itself. |
| `DEVICE_API_KEY` | Must match `DEVICE_API_KEY` in the Laravel `.env` |
| `DEVICE_ID` | A name for this unit, e.g. `esp32cam-01` |

`secrets.h` is in `.gitignore`, so your Wi-Fi password never reaches GitHub or
a Railway build. The firmware will not compile until you create it.

### 3. Wire the board and flash

Move the jumper on the camera board from **3V3 to 5V** before the first
upload. Then use the PlatformIO buttons in the status bar, or:

| Shortcut | Action |
| --- | --- |
| `Ctrl+Shift+B` | Build |
| `Ctrl+Shift+U` | Upload |
| `Ctrl+Shift+M` | Serial monitor |
| `Ctrl+Shift+P` → *Tasks: Run Task* | Clean, or serve Laravel |

If the upload fails with `Failed to connect to ESP32`, hold **IO0** down, tap
**RST**, release **IO0**, then upload again.

### 4. Read the boot log

The serial monitor prints the address to put in `.env`:

```
Connected. Stream at http://192.168.1.50:81

Add this to your Laravel .env file:
ESP32CAM_URL=http://192.168.1.50:81
Reporting telemetry to: http://192.168.1.50:8000
[telemetry] 201 -> {"ok":true,"reading":{...}}
```

A `201` from telemetry means the whole push path works.

---

## Connecting it to the application

In the Laravel `.env`:

```env
ESP32CAM_ENABLED=true
ESP32CAM_URL=http://192.168.1.50:81
ESP32CAM_FRAMESIZE=VGA

DEVICE_API_KEY=<the same key as in secrets.h>
ESP32CAM_INGEST_URL=http://192.168.1.50:8000
ESP32CAM_TELEMETRY_INTERVAL=10
```

Then:

```bash
php artisan config:clear
```

The camera pill in the top right of **/classifications/live** turns green once
the camera answers. Open **/dashboard** and the bin levels update on their own
as telemetry arrives — no refresh.

If the pill stays red, the usual causes are:

- The server and the camera are on different subnets.
- The Windows firewall is blocking inbound connections to PHP.
- The address changed after a reboot — reserve a static IP, or use mDNS at
  `http://esp32cam.local:81`.

---

---

## Using the stock CameraWebServer sketch instead

You do not have to flash `esp32cam/src/main.cpp`. The ESP32 Arduino core's
built-in **CameraWebServer** example works with this application too, and it is
what most guides tell you to upload first. Two things differ:

| | Stock CameraWebServer | `esp32cam/src/main.cpp` |
| --- | --- | --- |
| Control page | port **80** | port 81 |
| `/stream` | port **81** | port 81 |
| `/capture` | port **80** | port 81 |
| `/status` | not present | port 81 |
| Telemetry POST | no | yes |

Because capture and stream sit on different ports, set both in `.env`:

```env
ESP32CAM_URL=http://192.168.100.19:81
ESP32CAM_CAPTURE_URL=http://192.168.100.19
```

`ESP32CAM_CAPTURE_URL` is only needed for the stock sketch. Leave it empty for
our firmware, which serves everything from one port.

### The camera serves one stream at a time

The stock sketch keeps only two JPEG frame buffers in PSRAM. It hands them to
whoever asks for `/stream` and there is no second client, so:

1. Open `http://192.168.100.19` (port 80) and press **Start Stream** once. If
   you skip this, port 81 is closed and the application reports
   *"Connection refused"*.
2. **Close that tab.** Leaving it open keeps both buffers busy, so the
   application's stream connects and then receives no frames at all. The live
   page now detects exactly that and tells you to close the camera page rather
   than hanging silently.
3. Reload `/classifications/live`.

Flashing `esp32cam/src/main.cpp` avoids all of this: it serves the stream, the
capture and the health check from one port and needs no Start Stream click.

---

## Fill level sensor

The firmware can read one HC-SR04 to report a real bin level. Enable it in
`src/main.cpp`:

```cpp
#define ENABLE_ULTRASONIC   1
#define ULTRASONIC_TRIG     13
#define ULTRASONIC_ECHO     12
#define ULTRASONIC_COMPARTMENT "reject"
```

| Distance | Reported level |
| --- | --- |
| `BIN_EMPTY_CM` (60) | 0% |
| `BIN_FULL_CM` (8) | 100% |

With it left at `0` the camera still streams and still reports health, it
simply has no level to send. Only one sensor fits on the AI-Thinker's free
pins; a compartment reports 0 until you wire it.

A compartment that reaches `ESP32CAM_WARN_THRESHOLD` (75%) opens a **Near
Full** alert, and at `ESP32CAM_FILL_THRESHOLD` (85%) it escalates to **Full**.
Dropping back below the warning line resolves it. That logic lives server-side
in `app/Support/FillLevelMonitor.php`, so it behaves the same for real
hardware and for the demo data.

---

## Frame size

`FRAME_SIZE` is defined near the top of `main.cpp`. The classifier centre-crops
whatever it receives down to 224x224, so the crop of a small frame is blurry
and accuracy suffers.

| Framesize | Pixels | Notes |
| --- | --- | --- |
| `FRAMESIZE_QQVGA` | 160x120 | Too small, avoid |
| `FRAMESIZE_QVGA` | 320x240 | Acceptable fallback on a weak link |
| `FRAMESIZE_VGA` | 640x480 | **Default, best accuracy/size balance** |
| `FRAMESIZE_SVGA` | 800x600 | Better detail, needs a faster link |
| `FRAMESIZE_UXGA` | 1600x1200 | Stills only, too slow to stream |

At VGA the stream runs at roughly 8-12 fps over 2.4GHz Wi-Fi. The application
samples it every `WASTE_FRAME_INTERVAL_MS` (500 ms by default), so the stream
does not need to be fast — the classifier only needs a recent frame.

## Power

The OV2640 needs at least 4.5V. A 3.3V rail works until the Wi-Fi bursts and
then the image tears or the board browns out. Use a 2x 18650 pack on the 5V
jumper, or a bench supply that can deliver 500 mA peaks. PSRAM is required.

## Wiring

Upload and power wiring only. The camera is already wired on the AI-Thinker
board.

| Camera pin | Board pin | Use |
| --- | --- | --- |
| U0R | GPIO3 | Serial TX — the monitor reads the boot log here |
| GND | GND | Common ground |
| 5V | 5V | Power (move the jumper off 3V3) |
| U0T | GPIO1 | Serial RX |
| PSRAM | — | Leave alone, this is a solder bridge |

## Note on the proxy

The application never points a browser straight at the camera. It relays the
stream through `/api/esp32cam/stream` on purpose: a cross-origin `<img>` would
taint the capture canvas and `tf.browser.fromPixels()` would refuse to read it.
Relaying keeps the frame same-origin. See the comment at the top of
`app/Http/Controllers/Esp32CamController.php`.

## Concurrency

The MJPEG relay and the live event stream are both long-lived requests, so each
holds a server worker open. Apache (XAMPP) handles that fine. `php artisan
serve` is single-threaded on Windows — PHP's built-in server cannot fork
there — so an open stream will appear to freeze the whole app. Use Apache
locally, or `php artisan serve --no-reload` with `PHP_CLI_SERVER_WORKERS` set
on Linux (which is what Railway does).

## A note on where the machine learning runs

Inference runs **in the browser** with TensorFlow.js, not on the ESP32. An
AI-Thinker ESP32-CAM has neither the RAM nor the flash to hold a MobileNetV2
graph at 224x224, and the bundled model is a TensorFlow.js artefact. The
division of labour is deliberate:

| Where | What it does |
| --- | --- |
| ESP32-CAM | Serves frames, reports bin levels and health |
| Browser (TF.js) | Runs the model, writes the classification log |
| Laravel | Relays the stream, validates and stores results, raises alerts, pushes live updates |

`POST /api/device/detection` exists for a future on-device model. If you add
one, the server still validates the label against `metadata.json` and derives
the compartment itself, so a wrong label cannot be stored.

