<?php

namespace App\Support\Contable\Efe;

/**
 * Clasificación EFE (columna B de Datos) a partir de líneas del mayor por concepto.
 *
 * En Anita el concepto 53 del mayor incluye ventas de máquinas y otros movimientos;
 * en el EFE solo entran traspasos compensables (piernas origen/destino).
 */
class EfeClasificacionConceptoSupport
{
    public const CONCEPTO_MOVIMIENTOS_COMPENSABLES = 53;

    public const CONCEPTO_MEDIOS_DE_PAGO = 40;

    public const CONCEPTO_PRESTAMOS = 43;

    public const CONCEPTO_VENTA = 47;

    public const CONCEPTO_CANON_LOTERIA = 31;

    /** Caja central slots — excepción l-mayorconc.c (ING/COA/COB/EGR → 40, resto → 47). */
    private const CUENTA_CAJA_SLOTS = 113010001;

    /** Traspasos T.Coin a Macro (216030-00x) → medios de pago en EFE. */
    private const CUENTA_TCOIN_DESDE = 216030000;

    private const CUENTA_TCOIN_HASTA = 216031000;

    /** Intereses préstamos bancarios (532030-002) → 43 en EFE. */
    private const CUENTA_INTERESES_PRESTAMO_BANCARIO = 532030002;

    /** Acreditación giro descuento (521120-006) → 43. */
    private const CUENTA_GIRO_PRESTAMO = 521120006;

    /** Ventas IZV cuenta puente 211010-007 → 47 (l-mayorconc.c). */
    private const CUENTA_VENTA_IZV = 211010007;

    /** Cierres gastronomía/estacionamiento ctamov — IVA débito puente 214010-009 → 47. */
    private const CUENTA_VENTA_GASTRO_IVA = 214010009;

    /** Créditos interco (116010-xxx): en EFE entran solo préstamos HW (38) y Kandiko (14). */
    private const CUENTA_INTERCO_DESDE = 116010000;

    private const CUENTA_INTERCO_HASTA = 117000000;

    private const CUENTA_PRESTAMO_KANDIKO = 116010001;

    private const CUENTA_PRESTAMO_HW = 116010004;

    public const CONCEPTO_SUELDOS = 18;

    private const CUENTA_INTERCO_UT_BYSON = 116010005;

    /** Total Coin máquinas — ajuste duplicados premios (tipo 0) va a medios de pago (40). */
    private const CUENTA_AJUSTE_TOTAL_COIN = 113010011;

    /** Ajuste duplicados premios en cuenta alquiler máquinas — omitido vía filtro post-proceso si aplica. */
    private const CUENTA_AJUSTE_DUPLIC_ALQUILER = 521280004;

    /** Gastos bancarios 532040-xxx — Anita EFE col B concepto 36 = 0. */
    private const CUENTA_GASTOS_BANCARIOS_DESDE = 532040000;

    private const CUENTA_GASTOS_BANCARIOS_HASTA = 532041000;

    /** Cuenta Anita «150000-xxx» — Instituto Provincial de Lotería (OPV canon). */
    private const CUENTA_LOTERIA_DESDE = 150000000;

    private const CUENTA_LOTERIA_HASTA = 151000000;

    /**
     * @param  array<string, mixed>  $linea
     * @return int|null  null = omitir línea en Datos
     */
    public function resolverConceptoId(array $linea): ?int
    {
        $origen = (string) ($linea['origen'] ?? '');
        if ($origen === 'Remanente mayor plano') {
            return null;
        }

        $tipoComp = strtoupper(trim((string) ($linea['tipo_comp'] ?? '')));
        $descripcion = trim((string) ($linea['descripcion'] ?? ''));
        $conceptoId = (int) ($linea['concepto_id'] ?? 0);
        $cuenta = (int) ($linea['cuenta'] ?? 0);

        if ($cuenta === self::CUENTA_CAJA_SLOTS) {
            if (in_array($tipoComp, ['ING', 'COA', 'COB', 'EGR'], true)) {
                return self::CONCEPTO_MEDIOS_DE_PAGO;
            }

            return self::CONCEPTO_VENTA;
        }

        if ($cuenta >= self::CUENTA_TCOIN_DESDE
            && $cuenta < self::CUENTA_TCOIN_HASTA
            && $tipoComp === 'ING') {
            return self::CONCEPTO_MEDIOS_DE_PAGO;
        }

        if ($cuenta === self::CUENTA_VENTA_IZV && $tipoComp === 'IZV') {
            return self::CONCEPTO_VENTA;
        }

        if ($cuenta === self::CUENTA_VENTA_GASTRO_IVA && in_array($tipoComp, ['IZV', 'RMV'], true)) {
            return self::CONCEPTO_VENTA;
        }

        if ($cuenta === self::CUENTA_INTERESES_PRESTAMO_BANCARIO) {
            return self::CONCEPTO_PRESTAMOS;
        }

        if ($cuenta === self::CUENTA_GIRO_PRESTAMO) {
            return self::CONCEPTO_PRESTAMOS;
        }

        if ($cuenta === self::CUENTA_AJUSTE_TOTAL_COIN && in_array($tipoComp, ['0', ''], true)) {
            if ($this->esAjusteDuplicPremiosTotalCoinEfe($descripcion)) {
                return self::CONCEPTO_MEDIOS_DE_PAGO;
            }

            return null;
        }

        if ($cuenta >= self::CUENTA_GASTOS_BANCARIOS_DESDE
            && $cuenta < self::CUENTA_GASTOS_BANCARIOS_HASTA) {
            return null;
        }

        if ($cuenta === self::CUENTA_INTERCO_UT_BYSON) {
            return self::CONCEPTO_SUELDOS;
        }

        if ($cuenta >= self::CUENTA_INTERCO_DESDE
            && $cuenta < self::CUENTA_INTERCO_HASTA
            && ! in_array($cuenta, [self::CUENTA_PRESTAMO_HW, self::CUENTA_PRESTAMO_KANDIKO], true)) {
            return null;
        }

        if ($this->esTraspasoCompensableEfe($origen)) {
            return self::CONCEPTO_MOVIMIENTOS_COMPENSABLES;
        }

        if ($this->esPagoCanonLoteriaEfe($linea)) {
            return self::CONCEPTO_CANON_LOTERIA;
        }

        if ($tipoComp === 'REM') {
            return null;
        }

        if ($conceptoId === self::CONCEPTO_MOVIMIENTOS_COMPENSABLES) {
            if ($this->esVentaMaquinasEfe($tipoComp, $descripcion)) {
                return self::CONCEPTO_MEDIOS_DE_PAGO;
            }

            if ($tipoComp === 'ING' && str_contains($origen, 'contrapartida')) {
                return self::CONCEPTO_MEDIOS_DE_PAGO;
            }

            return 0;
        }

        if ($conceptoId === 0 && $this->esVentaMaquinasEfe($tipoComp, $descripcion)) {
            return self::CONCEPTO_MEDIOS_DE_PAGO;
        }

        return $conceptoId;
    }

    /**
     * Piernas origen/destino de traspasos entre disponibilidades (netean en Resumen col B).
     */
    public function esTraspasoCompensableEfe(string $origen): bool
    {
        return (bool) preg_match('/\b(TRF|ING|EGR|IEV|0)\s+(origen|destino)\b/i', $origen);
    }

    public function esVentaMaquinasEfe(string $tipoComp, string $descripcion): bool
    {
        if ($tipoComp === 'MAQ') {
            return true;
        }

        return stripos($descripcion, 'venta maquinas') !== false;
    }

    /** Anita EFE: «Ajust por duplic x premios tc» en 113010-011 → concepto 40. */
    public function esAjusteDuplicPremiosTotalCoinEfe(string $descripcion): bool
    {
        $texto = mb_strtolower(trim($descripcion));

        return str_contains($texto, 'duplic') && str_contains($texto, 'premio');
    }

    /**
     * En Anita EFE los OPV a cuenta 150000 (IPLyC) van a canon lotería (31), no a transferencias (21).
     *
     * @param  array<string, mixed>  $linea
     */
    public function esPagoCanonLoteriaEfe(array $linea): bool
    {
        $tipoComp = strtoupper(trim((string) ($linea['tipo_comp'] ?? '')));
        if ($tipoComp !== 'OPV') {
            return false;
        }

        $cuenta = (int) ($linea['cuenta'] ?? 0);
        if ($cuenta >= self::CUENTA_LOTERIA_DESDE && $cuenta < self::CUENTA_LOTERIA_HASTA) {
            return true;
        }

        $descripcion = mb_strtoupper(trim((string) ($linea['descripcion'] ?? '')));

        return str_contains($descripcion, 'INST. PROV. DE LO')
            || str_contains($descripcion, 'INST PROV DE LO');
    }

    public function formatearClave(int $conceptoId, string $nombreConcepto): string
    {
        $etiqueta = $conceptoId === 0
            ? 'SIN CLASIFICAR (INFO ANTERIOR)'
            : mb_strtoupper(trim($nombreConcepto));

        return 'Concepto: '.$conceptoId.' '.str_pad($etiqueta, 30, ' ', STR_PAD_RIGHT);
    }
}
