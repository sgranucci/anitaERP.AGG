<?php

namespace App\Support\Ventas;

/**
 * Resiliencia de emisión WSFEv1 (CAE en línea vs CAEA) compartida entre unidades de negocio.
 */
final class ArcaWsfeEmisionResiliencia
{
    /**
     * true: todas las emisiones usan el PV CAEA configurado y no llaman al WS ARCA en línea.
     */
    public static function forzarModoCaea(): bool
    {
        return filter_var(config('arca_wsfe.emision.forzar_modo_caea'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * true: si falla la comunicación con ARCA en una transacción CAE, reintenta una vez con PV CAEA.
     */
    public static function reintentarCaeaSiFallaComunicacion(): bool
    {
        return filter_var(config('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function debeReintentarPorMensaje(?string $mensaje): bool
    {
        if ($mensaje === null || $mensaje === '') {
            return false;
        }

        $m = strtolower($mensaje);
        foreach (
            [
                'soap', 'curl', 'timeout', 'connection', 'could not connect', 'network',
                'errno', 'failed to connect', 'ssl', 'wsfe', 'arca', 'afip', 'comunicacion',
            ] as $needle
        ) {
            if (str_contains($m, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Falla al solicitar CAE donde no hubo respuesta clara de ARCA (timeout, red, SOAP sin resultado).
     * En ese caso conviene consultar FECompUltimoAutorizado / FECompConsultar antes de dar por fallida la emisión.
     */
    public static function esFallaComunicacionSinRespuestaClara(?string $mensaje): bool
    {
        if ($mensaje === null || trim($mensaje) === '') {
            return true;
        }

        if (self::debeReintentarPorMensaje($mensaje)) {
            return true;
        }

        $m = strtolower($mensaje);
        foreach (['sin resultado', 'empty response', 'respuesta vac', 'no response', 'gateway'] as $needle) {
            if (str_contains($m, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{puntoventa_id:int, usa_caea:bool}
     */
    public static function resolverPuntoventaEmision(int $puntoventaCaeId, int $puntoventaCaeaId, bool $forzarCaeaTransaccion = false): array
    {
        $usaCaea = self::forzarModoCaea() || $forzarCaeaTransaccion;

        return [
            'puntoventa_id' => $usaCaea ? $puntoventaCaeaId : $puntoventaCaeId,
            'usa_caea' => $usaCaea,
        ];
    }

    public static function debeReintentarTransaccionConCaea(?string $mensaje, bool $yaUsaCaea): bool
    {
        if ($yaUsaCaea || self::forzarModoCaea()) {
            return false;
        }

        return self::reintentarCaeaSiFallaComunicacion() && self::debeReintentarPorMensaje($mensaje);
    }

    public static function mensajeAvisoModoCaeaForzado(): ?string
    {
        if (! self::forzarModoCaea()) {
            return null;
        }

        return 'Modo CAEA forzado (ARCA_WSFE_FORZAR_MODO_CAEA): no se consultó el web service en línea.';
    }
}
