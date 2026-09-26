<?php

namespace App\Http\Controllers;

use App\Support\Jpeg;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        $url = $config['base_url'].$config['stream_path'];

        // Every frame must reach the browser immediately. Drop any handler
        // output buffer and turn off compression/buffering.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ini_set('zlib.output_compression', 'Off');
        ini_set('implicit_flush', '1');
        ob_implicit_flush(true);

        try {
            $response = $this->client((float) $config['stream_read_timeout'])->get($url, [
                'stream' => true,
                'query' => $this->query($config),
                'headers' => $this->headers($config),
            ]);
        } catch (GuzzleException $e) {
            Log::warning('ESP32-CAM stream unreachable.', ['url' => $url, 'error' => $e->getMessage()]);

            return $this->plainError(
                'Cannot reach the ESP32-CAM at '.$url.'. '.$e->getMessage(),
                502,
            );
        }

        if ($response->getStatusCode() >= 400) {
            return $this->plainError(
                'The ESP32-CAM returned HTTP '.$response->getStatusCode().' for '.$url.'.',
                502,
            );
        }

        $body = $response->getBody();

        $this->sendStreamHeaders((string) $response->getHeaderLine('Content-Type'));

        // Runs until the camera stops sending or the visitor closes the tab.
        while (! $body->eof() && ! connection_aborted()) {
            echo $body->read($config['chunk_size']);
            flush();
        }

        // The multipart body is already on the wire and the session cookie
        // headers have been flushed, so the framework cannot finish this
        // request normally.
        exit;
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

        try {
            $response = $this->client()->get(
                $config['base_url'].$config['capture_path'],
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

        try {
            $response = $this->client()->get(
                $config['base_url'].$config['capture_path'],
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
