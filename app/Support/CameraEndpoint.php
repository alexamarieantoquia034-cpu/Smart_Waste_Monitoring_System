<?php

namespace App\Support;

/**
 * Works out whether the configured camera address is something the running
 * server can actually reach.
 *
 * The ESP32-CAM gets a private LAN address (192.168.x.x, 10.x.x.x or
 * 172.16-31.x.x) from the router. That address only resolves inside the local
 * network, so it works when the application is served from the same machine
 * (XAMPP, php artisan serve) and can never work when the application is
 * hosted somewhere else, such as Railway.
 *
 * Without this check the live page reports "ESP32-CAM is not configured" for a
 * camera that is configured perfectly well and responding on the LAN, which
 * sends people looking for a .env problem that is not there.
 */
class CameraEndpoint
{
    /**
     * @return array{
     *     configured: bool,
     *     enabled: bool,
     *     url: string,
     *     capture_url: string,
     *     host: string,
     *     private_network: bool,
     *     message: string|null
     * }
     */
    public function describe(): array
    {
        $config = config('esp32cam');

        $url = (string) $config['base_url'];
        $captureUrl = (string) ($config['capture_url'] ?: $url);
        $host = $url === '' ? '' : (string) parse_url($url, PHP_URL_HOST);

        $private = $host !== '' && $this->isPrivateHost($host);

        return [
            'configured' => $url !== '',
            'enabled' => (bool) $config['enabled'],
            'url' => $url,
            'capture_url' => $captureUrl,
            'host' => $host,
            'private_network' => $private,
            'message' => $this->message($url, $host, $private, (bool) $config['enabled']),
        ];
    }

    /**
     * True for addresses that only resolve on the local network: the RFC 1918
     * private ranges, loopback, link-local, and the naming conventions used
     * for machines on a LAN.
     *
     * A public DNS name is explicitly *not* treated as private, otherwise a
     * camera deliberately exposed through a tunnel would be blocked from the
     * UI even though it is perfectly reachable.
     */
    protected function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        // A single label with no dot ("esp32cam", "bin") is a LAN host name.
        if (! str_contains($host, '.')) {
            return true;
        }

        // Suffixes reserved for local networks (mDNS, .lan, .internal, ...).
        foreach (['.local', '.lan', '.home', '.internal', '.home.arpa', '.localdomain'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A sentence describing the situation, or null when there is nothing to
     * say. Wording is deliberately conditional: a private address is reachable
     * when the app runs on the same network and unreachable otherwise, and
     * only the live status check can tell which. Asserting either here would
     * show a confident "unreachable" warning next to a working video feed.
     */
    protected function message(string $url, string $host, bool $private, bool $enabled): ?string
    {
        if (! $enabled) {
            return 'The camera is switched off. Set ESP32CAM_ENABLED=true in .env.';
        }

        if ($url === '') {
            return 'No camera address is set. Point ESP32CAM_URL at the camera, for example http://192.168.100.19:81';
        }

        if ($private) {
            return 'ESP32CAM_URL points at '.$host.', a private address on your local network. '
                .'This works while the application runs on the same network as the camera, '
                .'so check the status above. A hosted server cannot route there at all; '
                .'for a deployment you will need a public address for the camera, reached '
                .'through port forwarding or a tunnel.';
        }

        return null;
    }
}
