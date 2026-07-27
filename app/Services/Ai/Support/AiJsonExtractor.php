<?php

namespace App\Services\Ai\Support;

/**
 * Extrae JSON de la respuesta cruda de un modelo (tolerante a texto envolvente).
 */
final class AiJsonExtractor
{
    /**
     * @return array<string,mixed>|null
     */
    public static function extraer(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        // Modelo devolvió texto alrededor del JSON: recortar primer objeto {...}.
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }
}
