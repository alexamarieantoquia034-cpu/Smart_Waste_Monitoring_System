<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\ClassificationLog;
use App\Models\SensorData;
use App\Support\FillLevelMonitor;

/**
 * Pushes new sensor readings, detections and alerts to the browser as they
 * land, using Server-Sent Events.
 *
 * Why SSE rather than WebSockets: the ESP32-CAM already POSTs over plain HTTP
 * and the application runs on XAMPP with no extra daemon. SSE is one-way,
 * needs no handshake or extra port, and works through Apache and Railway's
 * proxy without configuration. The browser reconnects on its own, so the
 * server deliberately closes each response after config('esp32cam.stream_ttl')
 * seconds rather than pinning a worker forever.
 *
 * The alternative, polling, costs a request per page per interval and lags
 * behind the device by up to that interval.
 */
class LiveStreamController extends Controller
{
    /**
     * Open the event stream. The client stays connected until the TTL expires
     * or the tab is closed.
     */
    public function stream(FillLevelMonitor $monitor)
    {
        $intervalMs = max(250, (int) config('esp32cam.stream_interval_ms', 1000));
        $ttl = max(5, (int) config('esp32cam.stream_ttl', 60));

        // Start from the newest rows so a page load replays nothing, only
        // genuinely new activity.
        $lastReading = (int) (SensorData::query()->max('id') ?? 0);
        $lastDetection = (int) (ClassificationLog::query()->max('id') ?? 0);
        $lastAlert = (int) (Alert::query()->max('id') ?? 0);

        return response()->stream(function () use (
            $monitor,
            $intervalMs,
            $ttl,
            &$lastReading,
            &$lastDetection,
            &$lastAlert
        ) {
            // A long-lived response must not be cut off by the script timeout.
            @set_time_limit(0);

            $this->sendHeaders();

            // Padding flushes the response immediately so the browser fires
            // onopen without waiting for the first real event.
            $this->comment('open');

            $deadline = microtime(true) + $ttl;

            while (microtime(true) < $deadline && ! connection_aborted()) {
                foreach ($this->poll($monitor, $lastReading, $lastDetection, $lastAlert) as $event) {
                    $this->emit($event['name'], $event['data']);
                }

                // A comment frame doubles as the keep-alive that stops
                // proxies closing an idle connection.
                $this->comment('tick');

                usleep($intervalMs * 1000);
            }

            $this->emit('bye', ['reason' => 'ttl']);
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            // Nginx honours this and skips response buffering, which would
            // otherwise hold events back until the response completed.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Fetch anything written since the last tick.
     *
     * The limits keep a burst (a re-run seeder, a device catching up) from
     * turning into one enormous frame; anything beyond them arrives on the
     * next tick because the cursor only advances to what was actually read.
     *
     * @return array<int, array{name: string, data: mixed}>
     */
    protected function poll(FillLevelMonitor $monitor, int &$lastReading, int &$lastDetection, int &$lastAlert): array
    {
        $events = [];

        $readings = SensorData::query()
            ->where('id', '>', $lastReading)
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($readings->isNotEmpty()) {
            $lastReading = (int) $readings->last()->id;

            $events[] = [
                'name' => 'reading',
                'data' => $readings->map(fn (SensorData $r) => $this->readingPayload($r, $monitor))->all(),
            ];
        }

        $detections = ClassificationLog::query()
            ->where('id', '>', $lastDetection)
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($detections->isNotEmpty()) {
            $lastDetection = (int) $detections->last()->id;

            $events[] = [
                'name' => 'detection',
                'data' => $detections->map(fn (ClassificationLog $log) => [
                    'id' => $log->id,
                    'waste_type' => $log->waste_type,
                    'confidence' => (float) $log->confidence,
                    'compartment' => $log->compartment,
                    'image_url' => $log->image_path ? asset($log->image_path) : null,
                    'created_at' => $log->created_at->toDateTimeString(),
                ])->all(),
            ];
        }

        $alerts = Alert::query()
            ->where('id', '>', $lastAlert)
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($alerts->isNotEmpty()) {
            $lastAlert = (int) $alerts->last()->id;

            $events[] = [
                'name' => 'alert',
                'data' => $alerts->map(fn (Alert $alert) => [
                    'id' => $alert->id,
                    'compartment' => $alert->compartment,
                    'status' => $alert->status,
                    'message' => $alert->message,
                    'created_at' => $alert->created_at->toDateTimeString(),
                ])->all(),
            ];
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readingPayload(SensorData $reading, FillLevelMonitor $monitor): array
    {
        return [
            'id' => $reading->id,
            'levels' => $monitor->levels($reading),
            'full' => array_keys($monitor->fullCompartments($reading)),
            'created_at' => $reading->created_at->toIso8601String(),
        ];
    }

    /**
     * SSE needs the raw headers, so the response is written directly and any
     * output buffer is dropped first.
     */
    protected function sendHeaders(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ini_set('zlib.output_compression', 'Off');
        ini_set('implicit_flush', '1');
        ob_implicit_flush(true);

        if (! headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
        }
    }

    protected function emit(string $event, mixed $payload): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES)."\n\n";
        flush();
    }

    /**
     * A comment line is ignored by EventSource but keeps the socket warm.
     */
    protected function comment(string $text): void
    {
        echo ': '.$text."\n\n";
        flush();
    }
}
