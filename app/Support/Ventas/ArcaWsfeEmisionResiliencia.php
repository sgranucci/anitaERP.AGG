<?php

namespace App\Support\Ventas;

/**
 * Resiliencia de emisión ARCA (CAE en línea vs CAEA) para WSFEv1 y WSMTXCA.
 */
final class ArcaWsfeEmisionResiliencia
{
    public static function esWsMtxca(?string $webservice): bool
    {
        return ($webservice ?? '') === 'wsmtxca';
    }

    private static function configKey(?string $webservice): string
    {
        return self::esWsMtxca($webservice) ? 'arca_mtxca' : 'arca_wsfe';
    }

    /**
     * true: todas las emisiones usan el PV CAEA configurado y no llaman al WS ARCA en línea.
     */
    public static function forzarModoCaea(?string $webservice = null): bool
    {
        return filter_var(config(self::configKey($webservice).'.emision.forzar_modo_caea'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * true: si falla la comunicación con ARCA en una transacción CAE, reintenta una vez con PV CAEA.
     */
    public static function reintentarCaeaSiFallaComunicacion(?string $webservice = null): bool
    {
        return filter_var(
            config(self::configKey($webservice).'.emision.reintentar_caea_si_falla_comunicacion'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function debeReintentarPorMensaje(?string $mensaje, ?string $webservice = null): bool
    {
        if ($mensaje === null || $mensaje === '') {
            return false;
        }

        $m = strtolower($mensaje);
        foreach (
            [
                'soap', 'curl', 'timeout', 'connection', 'could not connect', 'network',
                'errno', 'failed to connect', 'ssl', 'wsfe', 'wsmtxca', 'mtxca', 'arca', 'afip', 'comunicacion',
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
     */
    public static function esFallaComunicacionSinRespuestaClara(?string $mensaje, ?string $webservice = null): bool
    {
        if ($mensaje === null || trim($mensaje) === '') {
            return true;
        }

        if (self::debeReintentarPorMensaje($mensaje, $webservice)) {
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
    public static function resolverPuntoventaEmision(
        int $puntoventaCaeId,
        int $puntoventaCaeaId,
        bool $forzarCaeaTransaccion = false,
        ?string $webservice = null,
    ): array {
        $usaCaea = self::forzarModoCaea($webservice) || $forzarCaeaTransaccion;

        return [
            'puntoventa_id' => $usaCaea ? $puntoventaCaeaId : $puntoventaCaeId,
            'usa_caea' => $usaCaea,
        ];
    }

    public static function debeReintentarTransaccionConCaea(?string $mensaje, bool $yaUsaCaea, ?string $webservice = null): bool
    {
        if ($yaUsaCaea || self::forzarModoCaea($webservice)) {
            return false;
        }

        return self::reintentarCaeaSiFallaComunicacion($webservice) && self::debeReintentarPorMensaje($mensaje, $webservice);
    }

    public static function mensajeAvisoModoCaeaForzado(?string $webservice = null): ?string
    {
        if (! self::forzarModoCaea($webservice)) {
            return null;
        }

        $env = self::esWsMtxca($webservice) ? 'ARCA_MTXCA_FORZAR_MODO_CAEA' : 'ARCA_WSFE_FORZAR_MODO_CAEA';

        return "Modo CAEA forzado ({$env}): no se consultó el web service en línea.";
    }
}
