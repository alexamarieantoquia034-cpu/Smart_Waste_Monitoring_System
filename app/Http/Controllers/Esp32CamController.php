<?php

namespace App\Http\Controllers;

use App\Support\Jpeg;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;

/**
 * Bridges the ESP32-CAM web server into the application.
 *
 * The camera streams an endless multipart/x-mixed-replace MJPEG response.
 * Proxying it through Laravel is what makes the machine-learning pipeline
 * work in the browser: the frame arrives same-origin, so the capture canvas
 * stays untainted and tf.browser.fromPixels() is allowed to read it. It also
 * avoids mixed-content failures when the site is served over HTTPS.
 */
class Esp32CamController extends Controller
{
    /**
     * Relay the live MJPEG stream to the browser.
     */
    public function stream()
    {
        $config = config('esp32cam');

        if (! $config['enabled'] || $config['base_url'] === '') {
            return $this->plainError(
                'ESP32-CAM is not configured. Set ESP32CAM_URL in your .env file.',
                503,
            );
        }

        // Every frame must reach the browser immediately. Drop any handler
        // output buffer and turn off compression/buffering. Done here so both
        // the native relay and the polling fallback stream unbuffered.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ini_set('zlib.output_compression', 'Off');
        ini_set('implicit_flush', '1');
        ob_implicit_flush(true);

        if ($config['stream_mode'] !== 'poll') {
            $relay = $this->relayNativeStream($config);

            // The camera delivered a live stream, or the mode forbids the
            // polling fallback. Either way the response is already committed.
            if ($relay !== null) {
                return $relay;
            }
        }

        if ($config['stream_mode'] === 'native') {
            return $this->plainError(
                'The ESP32-CAM did not deliver a stream, and ESP32CAM_STREAM_MODE=native '
                .'forbids the /capture fallback. Power-cycle the camera to release its '
                .'stream port, or set ESP32CAM_STREAM_MODE=auto.',
                502,
            );
        }

        return $this->streamByPollingCapture($config);
    }

    /**
     * Relay the camera's own MJPEG stream.
     *
     * @return \Illuminate\Http\Response|null A response when the stream could
     *                                        not be used and the caller
     *                                        should fall back to polling,
     *                                        or null once frames are being
     *                                        written straight to the socket.
     */
    protected function relayNativeStream(array $config)
    {
        $url = $config['base_url'].$config['stream_path'];

        try {
            $response = $this->client((float) $config['stream_read_timeout'])->get($url, [
                'stream' => true,
                'query' => $this->query($config),
                'headers' => $this->headers($config),
            ]);
        } catch (GuzzleException $e) {
            Log::warning('ESP32-CAM stream unreachable, falling back to capture polling.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = $response->getBody();

        // The stock CameraWebServer keeps only two JPEG frame buffers and
        // serves a single stream client. If that client is gone but the socket
        // has not yet been reaped, this request connects and never receives a
        // frame - from the browser an indistinguishable silent hang. Probe for
        // the first byte with a bounded wait and fall back to polling.
        $first = $this->firstByte($body);

        if ($first === null || $first === '') {
            Log::warning('ESP32-CAM stream connected but delivered no frame; using capture polling.');

            return null;
        }

        $this->sendStreamHeaders((string) $response->getHeaderLine('Content-Type'));

        // Runs until the camera stops sending or the visitor closes the tab.
        while (! $body->eof() && ! connection_aborted()) {
            // The probe already consumed the first byte, so write it back out
            // before continuing with the rest of the stream.
            echo $first;
            echo $body->read($config['chunk_size']);
            flush();

            $first = null;
        }

        // The multipart body is already on the wire and the session cookie
        // headers have been flushed, so the framework cannot finish this
        // request normally.
        exit;
    }

    /**
     * Rebuild the MJPEG response the browser expects by polling /capture.
     *
     * The camera's dedicated stream port serves exactly one client and can be
     * left wedged by a dropped connection, which cannot be cleared without
     * physically rebooting the board. /capture is an ordinary request, so it
     * stays available. Assembling the multipart body here means the frontend
     * and the public URL do not change.
     */
    protected function streamByPollingCapture(array $config)
    {
        $host = $config['capture_url'] !== '' ? $config['capture_url'] : $config['base_url'];

        // Deliberately no leading delimiter here. The body must open with the
        // first part's boundary; emitting a close delimiter first makes
        // browsers treat the document as already finished.
        $this->sendStreamHeaders('');

        $interval = max(50, (int) $config['poll_interval_ms']) / 1000;
        $deadline = $config['stream_max_seconds'] > 0
            ? microtime(true) + $config['stream_max_seconds']
            : null;

        $client = $this->client();
        $misses = 0;

        while (! connection_aborted()) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                // Let the browser reconnect to a fresh worker rather than
                // holding this one for the rest of the session.
                break;
            }

            $frame = null;

            try {
                $response = $client->get($host.$config['capture_path'], [
                    'query' => $this->query($config) + ['framesize' => $config['framesize']],
                    'headers' => ['Accept' => 'image/jpeg'],
                ]);

                $bytes = (string) $response->getBody();

                if ($response->getStatusCode() < 400 && Jpeg::isJpeg($bytes)) {
                    $frame = $bytes;
                    $misses = 0;
                }
            } catch (GuzzleException $e) {
                // Log once, not once per frame: a dropped camera should not
                // fill the log while the browser holds the request open.
                if (++$misses === 1 || $misses % 25 === 0) {
                    Log::warning('ESP32-CAM capture poll failed.', [
                        'url' => $host.$config['capture_path'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($frame !== null) {
                $this->writePart($frame);
            } elseif ($misses > 30) {
                return $this->plainError(
                    'Lost contact with the ESP32-CAM while streaming. '
                    .$host.$config['capture_path'].' is not answering.',
                    502,
                );
            }

            usleep((int) ($interval * 1000000));
        }

        $this->writePart(null);
        exit;
    }

    /**
     * Write one multipart part; a null frame writes the closing delimiter.
     *
     * Every delimiter is preceded by CRLF, matching the layout the ESP32
     * CameraWebServer itself emits. A body that opens with "--frame--" is
     * misread by browsers as an already-closed multipart document, which
     * leaves the <img> with no paintable frame even though later parts
     * decode fine for canvas reads.
     */
    protected function writePart(?string $jpeg): void
    {
        if ($jpeg === null) {
            echo "\r\n--frame--\r\n";
            flush();

            return;
        }

        echo "\r\n--frame\r\n"
            ."Content-Type: image/jpeg\r\n"
            .'Content-Length: '.strlen($jpeg)."\r\n\r\n"
            .$jpeg;
        flush();
    }

    /**
     * Grab a single still frame from the camera.
     */
    public function capture()
    {
        $config = config('esp32cam');

        if (! $config['enabled'] || $config['base_url'] === '') {
            return $this->plainError(
                'ESP32-CAM is not configured. Set ESP32CAM_URL in your .env file.',
                503,
            );
        }

        $startedAt = microtime(true);
        $latency = fn () => (int) round((microtime(true) - $startedAt) * 1000);

        $host = $config['capture_url'] !== '' ? $config['capture_url'] : $config['base_url'];

        try {
            $response = $this->client()->get(
                $host.$config['capture_path'],
                [
                    'query' => $this->query($config) + ['framesize' => $config['framesize']],
                    'headers' => $this->headers($config),
                ],
            );
        } catch (GuzzleException $e) {
            Log::warning('ESP32-CAM capture failed.', ['error' => $e->getMessage()]);

            return $this->plainError('Cannot capture from the ESP32-CAM. '.$e->getMessage(), 502);
        }

        $bytes = (string) $response->getBody();

        if ($response->getStatusCode() >= 400 || ! Jpeg::isJpeg($bytes)) {
            return $this->plainError('The ESP32-CAM did not return a JPEG frame.', 502);
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Health check used by the live page to show the camera state.
     */
    public function status()
    {
        $config = config('esp32cam');

        if (! $config['enabled'] || $config['base_url'] === '') {
            return response()->json([
                'configured' => false,
                'online' => false,
                'message' => 'Set ESP32CAM_URL in .env to point at your camera.',
            ]);
        }

        $startedAt = microtime(true);
        $latency = fn () => (int) round((microtime(true) - $startedAt) * 1000);

        $host = $config['capture_url'] !== '' ? $config['capture_url'] : $config['base_url'];

        try {
            $response = $this->client()->get(
                $host.$config['capture_path'],
                [
                    'query' => $this->query($config) + ['framesize' => $config['framesize']],
                    'headers' => $this->headers($config),
                ],
            );

            $bytes = (string) $response->getBody();
            $dimensions = Jpeg::dimensions($bytes);
            $online = $response->getStatusCode() < 400 && Jpeg::isJpeg($bytes);

            return response()->json([
                'configured' => true,
                'online' => $online,
                'url' => $config['base_url'],
                'latency_ms' => $latency(),
                'framesize' => $config['framesize'],
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'bytes' => strlen($bytes),
                'message' => $online
                    ? 'Camera is streaming.'
                    : 'The camera answered but did not return a JPEG frame.',
            ]);
        } catch (GuzzleException $e) {
            Log::warning('ESP32-CAM status check failed.', ['error' => $e->getMessage()]);

            return response()->json([
                'configured' => true,
                'online' => false,
                'url' => $config['base_url'],
                'latency_ms' => $latency(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Wait a bounded time for the first byte of an endless stream response.
     *
     * The stream deliberately runs with a read timeout of 0 (never time out),
     * so a plain read() here would hang the request forever when the camera
     * connects but sends nothing. Waiting on the underlying socket with
     * stream_select() bounds the wait without cancelling the stream itself.
     *
     * @return string|null The first byte, '' if the stream ended, or null if
     *                     nothing arrived within the grace period.
     */
    protected function firstByte(StreamInterface $body): ?string
    {
        try {
            $resource = $body->getResource();
        } catch (\Throwable) {
            // Not a plain socket (a PumpStream, for example). Fall through and
            // let read() decide.
            $resource = null;
        }

        if (is_resource($resource)) {
            $read = [$resource];
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, 3);

            if ($ready === 0) {
                return null;
            }
        }

        return $body->read(1);
    }

    /**
     * Guzzle is built for request/response cycles, so the endless stream needs
     * its read timeout disabled and must be pulled rather than buffered.
     */
    protected function client(?float $timeout = null): Client
    {
        $config = config('esp32cam');

        return new Client([
            'connect_timeout' => $config['connect_timeout'],
            'timeout' => $timeout ?? (float) $config['read_timeout'],
            'http_errors' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function query(array $config): array
    {
        return filled($config['api_key'])
            ? ['api_key' => (string) $config['api_key']]
            : [];
    }

    /**
     * @return array<string, string>
     */
    protected function headers(array $config): array
    {
        return filled($config['api_key'])
            ? ['X-Api-Key' => (string) $config['api_key']]
            : ['Accept' => 'multipart/x-mixed-replace, image/jpeg'];
    }

    /**
     * Echo the camera's own content type so the browser parses the relayed
     * boundary exactly as it would when talking to the camera directly.
     */
    protected function sendStreamHeaders(string $upstreamContentType): void
    {
        if (headers_sent()) {
            return;
        }

        $contentType = Str::contains($upstreamContentType, 'multipart/')
            ? $upstreamContentType
            : 'multipart/x-mixed-replace; boundary=frame';

        header('Content-Type: '.$contentType);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Connection: close');
        header('X-Accel-Buffering: no');
    }

    protected function plainError(string $message, int $status)
    {
        return response($message, $status)->header('Content-Type', 'text/plain');
    }
}
