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
                'Cannot reach the ESP32-CAM at '.$url.'. '.$e->getMessage()
                .$this->streamHint($e),
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

        // The stock CameraWebServer keeps only two JPEG frame buffers. If the
        // camera's own control page is open in another tab it holds both, so
        // this request connects but never receives a frame. From the browser
        // that is an indistinguishable silent hang, so probe for the first
        // byte with a bounded wait and explain what to do.
        $first = $this->firstByte($body);

        if ($first === null || $first === '') {
            Log::warning('ESP32-CAM stream connected but delivered no frame.', ['url' => $url]);

            return $this->plainError(
                'The ESP32-CAM accepted the stream connection but sent no frames. '
                .'Its two frame buffers are almost certainly held by another client: '
                ."close the camera's own control page in your browser (or press Stop Stream) "
                .'and reload. The camera serves one stream at a time.',
                502,
            );
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
     * Turn a transport failure into something the operator can act on.
     *
     * The stock CameraWebServer is the usual source of trouble here and its
     * failure modes look identical from the network:
     *
     *   - "Connection refused" on the stream port means the stream server was
     *     never started. That sketch only opens it when Start Stream is
     *     pressed on its control page, so the port is simply closed.
     *   - A timeout on the control port means the board is on another subnet
     *     or blocked by the firewall.
     */
    protected function streamHint(GuzzleException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Connection refused')) {
            return ' The stream port is closed. Open the camera\'s control page on port 80,'
                .' press "Start Stream" once to open it, then close that tab and reload here.'
                .' The camera serves one stream at a time, so the page that opened it must be closed.';
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return ' The camera did not answer in time. Check it is on the same network as this'
                .' server and that the Windows firewall is not blocking inbound PHP connections.';
        }

        return '';
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
