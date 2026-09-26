<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates hardware calling /api/device/*.
 *
 * The ESP32-CAM has no session and cannot log in, so it presents a shared
 * secret in the X-Device-Key header instead. The comparison is
 * constant-time so the key cannot be recovered by timing the endpoint.
 *
 * The device routes are registered in the "api" group, which carries no
 * session or CSRF middleware, so this is the only thing standing between a
 * public URL and unauthenticated writes to the database.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('esp32cam.device_api_key');

        // Ingest is opt-in: with no key configured the endpoint stays closed
        // rather than defaulting to open on a public deployment.
        if (blank($expected)) {
            return response()->json([
                'message' => 'Device ingest is disabled. Set DEVICE_API_KEY in .env.',
            ], 503);
        }

        $provided = (string) $request->header('X-Device-Key', '');

        if ($provided === '' || ! hash_equals((string) $expected, $provided)) {
            return response()->json([
                'message' => 'Invalid or missing device key.',
            ], 401);
        }

        // The firmware sends JSON but no Accept header, and with no session
        // there is nowhere to redirect a validation failure to — Laravel
        // would answer with an HTML redirect to "/" instead of a 422 the
        // device can act on. Forcing the Accept header makes
        // expectsJson() true, so every error comes back as JSON.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
