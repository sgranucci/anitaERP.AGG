<?php

namespace App\Support\Caja\Flash;

/**
 * Office 365 a veces no acepta el SMTP (red inaccesible).
 * Esos cortes se reintentan; un rechazo de autenticación no.
 */
final class FlashReporteAggSmtpReintentoSupport
{
    /** @var list<string> */
    private const PATRONES_TRANSPORTE = [
        'connection could not be established',
        'network is unreachable',
        'la red es inaccesible',
        'connection timed out',
        'unable to connect',
        'connection reset',
        'temporary failure',
        'stream_socket_client',
        'smtp.office365.com',
        'operation timed out',
        'broken pipe',
    ];

    /** @var list<string> */
    private const PATRONES_NO_REINTENTAR = [
        'authentication failed',
        'failed to authenticate',
        '535 ',
        'invalid login',
        'username and password',
    ];

    public static function habilitado(): bool
    {
        return (bool) config('caja.flash_reporte_agg.reintento_smtp', true);
    }

    public static function esperaMinutos(): int
    {
        return max(1, (int) config('caja.flash_reporte_agg.reintento_minutos', 15));
    }

    public static function intentosInmediatos(): int
    {
        return max(1, min(5, (int) config('caja.flash_reporte_agg.reintento_intentos', 2)));
    }

    public static function esErrorTransporte(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        if ($texto === '') {
            return false;
        }
        foreach (self::PATRONES_NO_REINTENTAR as $patron) {
            if (str_contains($texto, $patron)) {
                return false;
            }
        }
        foreach (self::PATRONES_TRANSPORTE as $patron) {
            if (str_contains($texto, $patron)) {
                return true;
            }
        }

        return false;
    }

    public static function mensajeConAvisoReintento(string $errorOriginal): string
    {
        $base = 'No se pudo enviar el mail: '.$errorOriginal;
        if (! self::habilitado() || ! self::esErrorTransporte($errorOriginal)) {
            return $base;
        }

        return $base.'. Se reintentará automáticamente en unos minutos.';
    }
}
