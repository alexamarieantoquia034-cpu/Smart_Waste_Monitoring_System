<?php

namespace App\Support;

/**
 * Minimal JPEG inspector.
 *
 * The GD extension is not enabled in this project's PHP runtime, so image
 * dimensions and format are read straight from the JPEG byte stream instead.
 */
class Jpeg
{
    /**
     * True when the payload starts with the JPEG SOI marker.
     */
    public static function isJpeg(string $bytes): bool
    {
        return str_starts_with($bytes, "\xFF\xD8\xFF");
    }

    /**
     * Read the pixel dimensions from the first Start-Of-Frame segment.
     *
     * @return array{0: int, 1: int}|null [width, height] or null when the
     *                                    payload is not a readable JPEG.
     */
    public static function dimensions(string $bytes): ?array
    {
        if (! self::isJpeg($bytes)) {
            return null;
        }

        $length = strlen($bytes);
        $offset = 2;

        while ($offset + 3 < $length) {
            // Markers are 0xFF followed by a type byte; skip any fill bytes.
            if ($bytes[$offset] !== "\xFF") {
                $offset++;

                continue;
            }

            $marker = ord($bytes[$offset + 1]);

            // Standalone markers carry no length segment.
            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $offset += 2;

                continue;
            }

            // End of image / start of scan: no frame header will follow.
            if ($marker === 0xD9 || $marker === 0xDA) {
                return null;
            }

            $segmentLength = (ord($bytes[$offset + 2]) << 8) | ord($bytes[$offset + 3]);

            if ($segmentLength < 2) {
                return null;
            }

            // SOF0..SOF15, excluding DHT (0xC4), JPG (0xC8) and DAC (0xCC).
            $isFrame = $marker >= 0xC0
                && $marker <= 0xCF
                && $marker !== 0xC4
                && $marker !== 0xC8
                && $marker !== 0xCC;

            if ($isFrame) {
                if ($offset + 9 > $length - 1) {
                    return null;
                }

                return [
                    (ord($bytes[$offset + 7]) << 8) | ord($bytes[$offset + 8]),
                    (ord($bytes[$offset + 5]) << 8) | ord($bytes[$offset + 6]),
                ];
            }

            $offset += 2 + $segmentLength;
        }

        return null;
    }
}
