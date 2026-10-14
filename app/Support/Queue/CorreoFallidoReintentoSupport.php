<?php

declare(strict_types=1);

namespace App\Support\Queue;

/**
 * Decide si un failed job es un correo que se cayó por SMTP transitorio
 * y todavía puede reencolarse. No toca jobs con reintento propio (Waitry,
 * Anita, padrones, etc.).
 */
final class CorreoFallidoReintentoSupport
{
    public const CLAVE_REINTENTOS = 'reintentos_correo_transitorio';

    public const REENCOLAR = 'reencolar';

    public const TOPE = 'tope';

    public const OMITIR = 'omitir';

    public static function decision(string $payloadJson, string $exception, int $maxReintentos): string
    {
        if (! self::esCorreoEncolado($payloadJson)) {
            return self::OMITIR;
        }

        if (! self::esFalloSmtpTransitorio($exception)) {
            return self::OMITIR;
        }

        $payload = json_decode($payloadJson, true);
        $ya = is_array($payload) ? (int) ($payload[self::CLAVE_REINTENTOS] ?? 0) : 0;
        if ($ya >= $maxReintentos) {
            return self::TOPE;
        }

        return self::REENCOLAR;
    }

    public static function esCorreoEncolado(string $payloadJson): bool
    {
        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return false;
        }

        return ($payload['data']['commandName'] ?? '') === 'Illuminate\\Mail\\SendQueuedMailable';
    }

    public static function esFalloSmtpTransitorio(string $exception): bool
    {
        $linea = strtok($exception, "\n") ?: $exception;

        if (preg_match('/\b(535|550|551|552|553|554)\b/', $linea) === 1) {
            return false;
        }

        $esTransporte = str_contains($linea, 'Symfony\\Component\\Mailer\\Exception\\TransportException')
            || str_contains($linea, 'Symfony\\Component\\Mailer\\Exception\\UnexpectedResponseException');
        if (! $esTransporte) {
            return false;
        }

        $marcas = [
            'Connection could not be established',
            'Unable to connect',
            'getaddrinfo',
            'Fallo temporal',
            'Temporary failure in name resolution',
            'La red es inaccesible',
            'Connection timed out',
            'timed out',
            '421',
            '450',
            '451',
        ];
        foreach ($marcas as $marca) {
            if (str_contains($linea, $marca)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{json: string, reintentos: int}|null
     */
    public static function payloadConReintento(string $payloadJson): ?array
    {
        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return null;
        }

        $reintentos = (int) ($payload[self::CLAVE_REINTENTOS] ?? 0) + 1;
        $payload[self::CLAVE_REINTENTOS] = $reintentos;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        return ['json' => $json, 'reintentos' => $reintentos];
    }
}
