<?php

namespace App\Support\Compras;

/**
 * Movimientos a anticipo bienes de uso (puente Capex) sin Capex en la OC.
 *
 * Alcance: solo ERP. Aperturas y asientos/importaciones Anita se omiten;
 * con Capex siempre OK; sin Capex en ERP → desvío (OC sin Capex o sin OC).
 */
final class ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport
{
    public const TIPO_OC_SIN_CAPEX = 'oc_sin_capex';

    public const TIPO_SIN_OC = 'sin_oc';

    /**
     * Saldos de apertura / iniciales sin documento operativo.
     */
    public static function esApertura(
        ?string $observacion,
        ?string $anitaTipo,
        ?int $comprobanteProveedorId,
        ?int $recepcionProveedorId,
        ?int $ordencompraId,
    ): bool {
        if ($comprobanteProveedorId || $recepcionProveedorId || $ordencompraId) {
            return false;
        }
        if (trim((string) $anitaTipo) !== '') {
            return false;
        }

        $obs = trim((string) $observacion);
        if ($obs === '' || $obs === 'B') {
            return true;
        }

        $upper = mb_strtoupper($obs, 'UTF-8');

        return str_contains($upper, 'APERTURA')
            || str_contains($upper, 'SALDO INICIAL');
    }

    /**
     * Fuera del alcance "solo ERP": CP importado Anita o asiento de subdiario Anita.
     * Si hay CP con origen distinto de ANITA_IMPORT, manda el CP (no omitir por sync posterior).
     *
     * @param  bool  $tieneDocumentoErp  true si el asiento está ligado a CP o COM ERP
     */
    public static function esFueraAlcanceErp(
        ?string $origenEntradaCp,
        ?string $anitaOrigenAsiento,
        ?string $observacion,
        ?string $anitaTipo = null,
        ?string $anitaNro = null,
        bool $tieneDocumentoErp = false,
    ): bool {
        if ($origenEntradaCp === ComprobanteProveedorOrigenEntrada::ANITA_IMPORT) {
            return true;
        }

        // CP ERP (manual, OC, precarga, etc.): dentro de alcance aunque el asiento tenga trazas Anita.
        if ($origenEntradaCp !== null && $origenEntradaCp !== '') {
            return false;
        }

        if (trim((string) $anitaOrigenAsiento) !== '') {
            return true;
        }

        $obs = trim((string) $observacion);
        if (str_contains($obs, '[SUBD]')) {
            return true;
        }

        // Import Anita estilo "B FIS A 2 1415" (letra + tipo + datos), no el "B" solo de apertura.
        if (preg_match('/^B\s+[A-Z]{2,}/', $obs)) {
            return true;
        }

        // Sin CP/COM: tipo/nro Anita (OPP, cuotas, etc.) ⇒ fuera de alcance.
        // Con documento ERP no omitir solo por anita_tipo (puede llenarse post-sync).
        if (! $tieneDocumentoErp
            && (trim((string) $anitaTipo) !== '' || trim((string) $anitaNro) !== '')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *     ok: bool,
     *     omitido: bool,
     *     tipo: string|null,
     *     motivo: string|null
     * }
     */
    public static function clasificar(
        float $montoEnCuentaPuente,
        bool $ocTieneCapex,
        ?int $ordencompraId,
        bool $esApertura,
        bool $fueraAlcanceErp,
        float $tolerancia = 0.01,
    ): array {
        if (abs($montoEnCuentaPuente) < $tolerancia) {
            return ['ok' => true, 'omitido' => false, 'tipo' => null, 'motivo' => null];
        }

        if ($esApertura) {
            return [
                'ok' => false,
                'omitido' => true,
                'tipo' => null,
                'motivo' => 'Asiento de apertura / saldo inicial (fuera de auditoría operativa).',
            ];
        }

        // Con Capex la imputación es válida aunque el asiento venga de Anita.
        if ($ocTieneCapex) {
            return ['ok' => true, 'omitido' => false, 'tipo' => null, 'motivo' => null];
        }

        if ($fueraAlcanceErp) {
            return [
                'ok' => false,
                'omitido' => true,
                'tipo' => null,
                'motivo' => 'Origen Anita / subdiario (auditoría solo ERP).',
            ];
        }

        if ($ordencompraId === null || $ordencompraId <= 0) {
            return [
                'ok' => false,
                'omitido' => false,
                'tipo' => self::TIPO_SIN_OC,
                'motivo' => 'Imputación a anticipo BS.USO sin orden de compra vinculada.',
            ];
        }

        return [
            'ok' => false,
            'omitido' => false,
            'tipo' => self::TIPO_OC_SIN_CAPEX,
            'motivo' => 'Imputación a anticipo BS.USO (cuenta puente Capex) pero la OC no tiene Capex asignado en artículos.',
        ];
    }

    /**
     * @deprecated Usar clasificar(); se mantiene por compatibilidad de tests previos.
     *
     * @return array{ok: bool, motivo: string|null}
     */
    public static function evaluar(
        float $montoEnCuentaPuente,
        bool $ocTieneCapex,
        ?int $ordencompraId,
        float $tolerancia = 0.01,
    ): array {
        $r = self::clasificar(
            $montoEnCuentaPuente,
            $ocTieneCapex,
            $ordencompraId,
            false,
            false,
            $tolerancia,
        );

        return [
            'ok' => $r['ok'],
            'motivo' => $r['motivo'],
        ];
    }
}
