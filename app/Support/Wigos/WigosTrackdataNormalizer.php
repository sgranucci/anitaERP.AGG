<?php

namespace App\Support\Wigos;

use InvalidArgumentException;

/**
 * Limpia trackdata de tarjeta tal como lo envía el lector (sentinelas ISO, asterisco, etc.).
 */
final class WigosTrackdataNormalizer
{
    /** @var list<string> */
    private const PREFIJOS_LECTOR = [';', '%', '*'];

    /** @var list<string> */
    private const SUFIJOS_LECTOR = ['?', ';', '%'];

    public static function normalizar(string $trackdata, bool $exigirNoVacio = true): string
    {
        $track = trim($trackdata);
        $track = preg_replace('/[\x00-\x1F\x7F]/u', '', $track) ?? '';
        $track = trim($track);

        while ($track !== '' && in_array($track[0], self::PREFIJOS_LECTOR, true)) {
            $track = substr($track, 1);
        }

        while ($track !== '' && in_array($track[strlen($track) - 1], self::SUFIJOS_LECTOR, true)) {
            $track = substr($track, 0, -1);
        }

        $track = trim($track);

        if ($exigirNoVacio && $track === '') {
            throw new InvalidArgumentException('Debe pasar o escanear la tarjeta.');
        }

        return $track;
    }
}
