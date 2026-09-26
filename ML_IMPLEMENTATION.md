# Machine Learning Implementation

How the trained model in `real dataset/` is wired into the application, and how
the ESP32-CAM feeds it.

---

## 1. What the model is

`real dataset/metadata.json` describes a **Teachable Machine** image model
trained on real waste photos:

| Property | Value |
| --- | --- |
| `tfjsVersion` | 1.7.4 |
| `tmVersion` | 2.4.16 |
| `labels` | `Paper`, `Plastic`, `Class 3` |
| `imageSize` | 224 |
| Backbone | MobileNetV2, cut at `out_relu` |
| Head | `Dense(100, relu)` → `Dense(3, softmax)` |
| Weights | 263 tensors, float32, 2.05 MB |

Because the last layer is a **softmax**, the raw network output is already a
probability distribution and the application never normalises it a second time.

> **The model was not retrained.** It was already trained when this work
> started, so the task here was integration, not training. See
> [§6](#6-retraining) for how to add classes.

---

## 2. Where inference runs, and why

**In the browser, using TensorFlow.js 1.7.4.** Not in PHP, and not in a Python
service.

- **The runtime already matches the model.** `metadata.json` was written by
  tfjs `1.7.4`. The layer/weight manifest format is version-specific; pinning
  the same runtime removes an entire class of "model won't load" problems.
- **No new process to run.** The project is a Laravel app on XAMPP. A Python
  service would mean a second runtime, a second dependency set and a port to
  keep alive for a demo.
- **No build step.** The runtime is vendored at
  `public/vendor/tfjs/tf.min.js`, so `npm run build` is not required for
  classification to work.
- **Latency is fine.** A MobileNetV2 forward pass is a few tens of
  milliseconds on the WebGL backend.

The trade-off is honest: the model runs on the *client*, so a user could tamper
with the numbers they send. That is why the server re-validates the label and
re-derives the compartment rather than trusting the request
(see [§5](#5-what-the-server-trusts)).

---

## 3. Request flow

```
 ESP32-CAM                  Laravel                        Browser
 ─────────                  ──────                        ───────
 GET /stream  ─────────▶  Esp32CamController::stream()  ──▶  <img src=…>
   (MJPEG)               (relays frame by frame)              │
                                                                │ drawImage()
                                                                ▼
                                                        <canvas> 224x224
                                                                │ tf.browser.fromPixels()
                                                                ▼
                                                        TensorFlow.js forward pass
                                                                │ top-1 + confidence
                                                                ▼
                                                          POST /api/classifications
                                                                │
   ClassificationController::store()  ◀──────────────────────────┘
     • validates the label against metadata.json
     • maps label → compartment
     • writes the JPEG under public/images/classifications
     • inserts a row into classification_logs
```

### Why the stream is proxied

The browser never talks to the camera directly. A cross-origin `<img>` taints
its canvas, and `tf.browser.fromPixels()` throws a security error on a tainted
canvas. A direct `http://192.168.x.x/stream` is also blocked outright on an
HTTPS page. Relaying through `/api/esp32cam/stream` fixes both at once: the
frame is same-origin and untainted.

---

## 4. Preprocessing — must match the trainer

`public/js/live-classifier.js` reproduces `@teachablemachine/image` exactly:

```js
// 1. centre-square crop onto a 224x224 canvas   (mirrors cropTo())
crop(frame, 224, inferCanvas);

// 2. pixels → tensor, rescaled to [-1, 1]        (mirrors capture())
tf.browser.fromPixels(inferCanvas)
  .expandDims(0)
  .toFloat()
  .div(tf.scalar(127))
  .sub(tf.scalar(1));

// 3. forward pass; output is already softmax probabilities
model.predict(input);
```

Getting this wrong is the most common reason a ported model "loads but always
predicts one class". The `div(127).sub(1)` in particular is not a rounding
detail — it is the normalisation MobileNetV2 was trained with.

Verified end to end: feeding a 224x224 tensor through this chain returns three
probabilities summing to `1.000000`.

---

## 5. What the server trusts

`ClassificationController::store()` re-derives everything that matters:

- The label must exist in `metadata.json`, otherwise `422`. A crafted request
  cannot invent a class.
- The compartment comes from `config/waste.php` on the server. The browser's
  idea of the bin is never used.
- The image must be a real JPEG (magic bytes checked, dimensions parsed by
  `App\Support\Jpeg` because this project's PHP has no GD extension) and under
  512 KB.
- `sensor_data_id` is a non-nullable foreign key, so a detection is attached to
  the most recent reading; a zeroed reading is created if the device has not
  reported yet.

---

## 6. Retraining

The `real dataset/` folder is the single source of truth. Browsers cannot read
outside `public/`, so after every retrain:

```bash
php artisan waste:sync-model          # copy the three files into public/
php artisan waste:sync-model --check  # inspect without copying
php artisan config:clear
```

### Adding or renaming classes

1. Retrain in Teachable Machine with the new class names.
2. Export the model and replace the three files in `real dataset/`.
3. Add the mapping in `config/waste.php`:

   ```php
   'label_map' => [
       'paper'         => 'paper',
       'plastic'       => 'plastic',
       'biodegradable' => 'biodegradable',
   ],
   ```

4. `php artisan waste:sync-model`

The live page reads its class list and the compartment mapping from the model
metadata and the config, so **no Blade or JavaScript changes are needed** for a
retrain that changes the classes or the input resolution.

> **On `Class 3`.** The third class in the current model is still the
> placeholder name Teachable Machine gives unnamed classes. It is mapped to the
> **reject** compartment. If it is meant to be biodegradable waste, retrain
> with the real name and update the mapping, otherwise genuine food scraps will
> be logged as rejects.

---

## 7. Configuration

### `config/waste.php`

| Key | Purpose |
| --- | --- |
| `model.source` | The `real dataset` folder (source of truth) |
| `model.served` | The copy the browser downloads |
| `label_map` | Model label → compartment |
| `compartments` | Display name, icon and colour per bin |
| `inference.threshold` | Auto-save confidence, default 70% |
| `inference.frame_interval_ms` | Frame sampling interval, default 500 ms |
| `inference.stable_frames` | Agreeing frames before an auto-save, default 3 |
| `snapshots.max_kb` | Snapshot size cap, default 512 KB |

### `config/esp32cam.php` / `.env`

| Variable | Example |
| --- | --- |
| `ESP32CAM_ENABLED` | `true` |
| `ESP32CAM_URL` | `http://192.168.1.50:81` |
| `ESP32CAM_API_KEY` | leave empty unless you harden the sketch |
| `ESP32CAM_FRAMESIZE` | `VGA` |
| `WASTE_CONFIDENCE_THRESHOLD` | `70` |

---

## 8. Endpoints

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/classifications/live` | The live classification page |
| `GET` | `/api/waste-model` | Model description (labels, classes, URLs) |
| `POST` | `/api/classifications` | Persist a browser detection |
| `GET` | `/api/esp32cam/stream` | Relayed MJPEG stream |
| `GET` | `/api/esp32cam/capture` | One still frame |
| `GET` | `/api/esp32cam/status` | Camera health (latency, resolution) |
| `GET` | `/api/live-stream` | Server-Sent Events: readings, detections, alerts |

The first seven require a session. The POST accepts `label`, `confidence`,
optional `source` and optional `image` (a base64 JPEG data URL).

### Device endpoints

The ESP32-CAM pushes into the application on its own timer, so the dashboard
updates with no browser open on the live page. These live in the `api` group
(no session, no CSRF) and are gated on the `X-Device-Key` header by
`AuthenticateDevice`:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/device/ping` | Confirms the key; returns the report interval |
| `POST` | `/api/device/telemetry` | Bin levels + device health |
| `POST` | `/api/device/detection` | A detection from an on-device model |

Telemetry is **partial by design**: a compartment the payload omits is carried
forward from the previous reading rather than written as zero, so wiring a
single ultrasonic sensor to one bin does not zero out the other three.

---

## 9. Real-time updates

`GET /api/live-stream` is a Server-Sent Events feed. The server polls the
three tables once a second and pushes anything written since the last tick as
`reading`, `detection` and `alert` events.

`public/js/live-stream.js` subscribes on every page and re-dispatches each
frame as a DOM `CustomEvent` (`waste:reading`, `waste:detection`,
`waste:alert`). The dashboard KPI cards and the live page's recent-detections
list update in place; the navbar refreshes its badge off the same events.

**Why SSE and not Reverb or polling.** The device already speaks plain HTTP,
the app runs on XAMPP with no extra daemon, and SSE is one-way: no handshake,
no extra port, and it survives Railway's proxy unmodified. Polling would cost
a request per page per interval and still lag the device by up to that
interval. Reverb would add a service to install and keep alive for a feed that
only ever flows server → browser.

Each response is closed after `LIVE_STREAM_TTL` seconds (30 by default) and the
browser reconnects, so a long-lived stream cannot pin a server worker forever.
That means `PHP_CLI_SERVER_WORKERS` matters: `php artisan serve` is
single-threaded by default, and with one worker an open dashboard would block
the camera's own telemetry POSTs. Apache and Railway (with the worker count
set) handle this; see the concurrency notes in `.env.example`.

---

## 10. Without a camera

The live page has two fallbacks so the model can be demonstrated before the
hardware is wired:

- **Webcam** — uses `getUserMedia`, no hardware beyond a laptop camera.
- **Upload** — classifies a still image from disk.

Both go through the same inference and save path as the ESP32-CAM feed.

---

## 11. Files

| Path | Role |
| --- | --- |
| `real dataset/` | Trained model (source of truth) |
| `public/models/waste-classifier/` | Served copy |
| `public/vendor/tfjs/tf.min.js` | TensorFlow.js 1.7.4 |
| `config/waste.php` | Model paths, label map, inference defaults |
| `config/esp32cam.php` | Camera, ingest, threshold and stream settings |
| `app/Support/WasteClassifier.php` | Reads metadata, resolves compartments |
| `app/Support/FillLevelMonitor.php` | Opens, escalates and resolves alerts |
| `app/Support/Jpeg.php` | JPEG header reader (no GD needed) |
| `app/Http/Controllers/Esp32CamController.php` | Stream relay, capture, status |
| `app/Http/Controllers/ClassificationController.php` | `live`, `model`, `store` |
| `app/Http/Controllers/DeviceController.php` | `ping`, `telemetry`, `detection` |
| `app/Http/Controllers/LiveStreamController.php` | Server-Sent Events feed |
| `app/Http/Middleware/AuthenticateDevice.php` | `X-Device-Key` check |
| `app/Console/Commands/SyncWasteModelCommand.php` | `waste:sync-model` |
| `database/seeders/DemoDataSeeder.php` | Demo readings, detections, alerts |
| `public/js/live-classifier.js` | Inference loop and save logic |
| `public/js/live-stream.js` | EventSource client and DOM updates |
| `resources/views/classifications/live.blade.php` | The page |
| `esp32cam/src/main.cpp` | Camera firmware (PlatformIO) |
| `platformio.ini` | PlatformIO project, at the repository root |

