<?php

namespace App\Support\Contable\MayorPlanoCuenta;

class MayorPlanoCuentaSupport
{
    /** @var list<string> */
    private const TIPOS_ASIENTO_CIERRE = ['CIE', 'CER', 'CIER'];

    /** @var list<string> */
    private const TIPOS_ASIENTO_INFLACION = ['INF', 'AJI', 'AJU', 'INFL'];

    /** Asiento de apertura de ejercicio (lee_saldo_inicial lo incluye siempre). */
    public const TIPO_ASIENTO_APERTURA = 'APE';

    /**
     * Origen mínimo de saldos en Biyemas: con cierre 2025 hecho, ejercicio 2026
     * arranca con saldos acumulados desde 01/01/26 (APE).
     * No se lee ctamov anterior a esta fecha para saldo inicial.
     */
    public const SALDO_ORIGEN_MINIMO_YMD = 20260101;

    public static function formatearCodigoCuenta(int $codigo): string
    {
        $s = str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6, 3);
    }

    public static function parsearCodigoCuenta(string $valor): int
    {
        $digits = preg_replace('/\D/', '', trim($valor)) ?? '';

        return $digits !== '' ? (int) $digits : 0;
    }

    public static function formatearComprobante(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        if ($nro <= 0) {
            return '';
        }

        $l = trim($letra) !== '' ? trim($letra) : ' ';

        return $l.sprintf('%04d', $sucursal).'-'.sprintf('%08d', $nro);
    }

    public static function formatearFecha(int $fechaYmd): string
    {
        $s = str_pad((string) $fechaYmd, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8) {
            return '';
        }

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 2, 2);
    }

    /**
     * Equivalente a FILA_valida_tipo_asiento() según fl_incluye_asi (help_10) en l-mayor.c:
     * 1 todos | 2 sin cierre (CIE/CER/CIER) | 3 sin inflación (INF/AJI/AJU/INFL) | 4 sin ambos.
     * APE y demás tipos no se excluyen en ningún modo.
     */
    public static function movimientoVisiblePorTipoAsiento(string $tipoAsiento, string $modoInclusion): bool
    {
        $tipo = strtoupper(trim($tipoAsiento));

        return match ($modoInclusion) {
            'todos' => true,
            'sin_cierre' => ! self::esAsientoCierre($tipo),
            'sin_inflacion' => ! self::esAsientoInflacion($tipo),
            'sin_cierre_ni_inflacion' => ! self::esAsientoCierre($tipo) && ! self::esAsientoInflacion($tipo),
            default => ! self::esAsientoCierre($tipo) && ! self::esAsientoInflacion($tipo),
        };
    }

    public static function esAsientoCierre(string $tipoAsiento): bool
    {
        $tipo = strtoupper(trim($tipoAsiento));

        return in_array($tipo, self::TIPOS_ASIENTO_CIERRE, true);
    }

    public static function esAsientoInflacion(string $tipoAsiento): bool
    {
        $tipo = strtoupper(trim($tipoAsiento));

        return in_array($tipo, self::TIPOS_ASIENTO_INFLACION, true);
    }

    /**
     * Moneda de referencia = inversa de la del asiento.
     * Pesos (id 1) → dólares (2); extranjera (id >= 2) → pesos (1).
     */
    public static function monedaReferenciaId(int $monedaAsientoId): int
    {
        return max(1, $monedaAsientoId) <= 1 ? 2 : 1;
    }

    /**
     * Firma Mon.Referencia: Debe positivo / Haber negativo.
     */
    public static function firmarImporteDh(float $importe, string $dh): float
    {
        $monto = abs($importe);

        return strtoupper(trim($dh)) === 'H' ? -$monto : $monto;
    }

    /**
     * Importe en moneda de referencia (inversa del asiento) con cotización del asiento.
     * - Pesos (id/cod 1) → dólares = importe / cotización
     * - Extranjera (id >= 2) → pesos = importe * cotización
     * Sin cotización válida → 0 (el procesador puede usar cotización diaria como fallback).
     */
    public static function importeMonedaReferencia(
        float $importeNativo,
        string $dh,
        string $codMon = '1',
        float $cotizacion = 0.0,
    ): float {
        $monto = abs($importeNativo);
        if ($monto < 0.00001) {
            return 0.0;
        }

        $cotiz = $cotizacion;
        if ($cotiz < 0.01) {
            return 0.0;
        }

        $monedaAsientoId = max(1, (int) (trim($codMon) !== '' ? trim($codMon) : '1'));
        $convertido = $monedaAsientoId <= 1
            ? $monto / $cotiz
            : $monto * $cotiz;

        return self::firmarImporteDh($convertido, $dh);
    }

    /** Inicio de ejercicio contable (01/01 del año del período). Equivalente a EMPM_extrae_ejercicio(). */
    public static function inicioEjercicio(int $fechaYmd): int
    {
        $s = str_pad((string) $fechaYmd, 8, '0', STR_PAD_LEFT);
        $anio = (int) substr($s, 0, 4);

        return (int) ($anio.'0101');
    }

    /**
     * l-mayor.c: si in_desde_fecha < fecha_comienzo_ejercicio → comienzo -= 1000000 (año anterior).
     */
    public static function fechaComienzoEjercicioAjustada(int $fechaDesde, int $fechaComienzoEjercicio): int
    {
        if ($fechaDesde < $fechaComienzoEjercicio) {
            return self::ejercicioAnterior($fechaComienzoEjercicio);
        }

        return $fechaComienzoEjercicio;
    }

    public static function ejercicioAnterior(int $fechaYmd): int
    {
        $s = str_pad((string) $fechaYmd, 8, '0', STR_PAD_LEFT);
        $anio = (int) substr($s, 0, 4) - 1;
        $resto = substr($s, 4);

        return (int) ($anio.$resto);
    }

    public static function esAsientoApertura(string $tipoAsiento): bool
    {
        return strtoupper(trim($tipoAsiento)) === self::TIPO_ASIENTO_APERTURA;
    }

    /**
     * Desde qué fecha acumular saldo inicial: ejercicio actual si hay APE, si no el anterior (mín. 01/01/26).
     *
     * @param  list<int>  $fechasSaldoPorEmpresa
     */
    public static function consolidarFechaSaldoDesde(array $fechasSaldoPorEmpresa): int
    {
        $fechas = array_values(array_filter(array_map('intval', $fechasSaldoPorEmpresa), fn (int $f) => $f > 0));
        if ($fechas === []) {
            return self::SALDO_ORIGEN_MINIMO_YMD;
        }

        return min($fechas);
    }

    /**
     * Filtro de moneda l-mayor.c: pesos (u origen = reporte) siempre visible aunque cotización 0;
     * otra moneda solo si trae cotización para convertir a pesos.
     */
    public static function movimientoVisibleMoneda(
        string $codMonMovimiento,
        float $cotizacionMovimiento,
        string $codMonReporte,
        bool $soloMonedaOrigen,
    ): bool {
        $codMov = trim($codMonMovimiento) !== '' ? trim($codMonMovimiento) : '1';

        if ($soloMonedaOrigen) {
            return $codMov === $codMonReporte;
        }

        if ($codMov === $codMonReporte) {
            return true;
        }

        return $cotizacionMovimiento >= 0.01;
    }

    /** OP*, OPP, OPA… — en un_mov() dispara busca_op() sobre che_ban.pago. */
    public static function esTipoOrdenPago(string $tipoComprobante): bool
    {
        $tipo = strtoupper(trim($tipoComprobante));

        return strlen($tipo) >= 2 && str_starts_with($tipo, 'OP');
    }

    /**
     * Descripción visible: subd_desc_mov / ctav_desc_mov (30) o pag_leyenda si es orden de pago.
     */
    public static function resolverDescripcionMovimiento(
        string $tipoComprobante,
        int $sucursal,
        int $nroComprobante,
        string $descripcionOrigen,
        ?MayorPlanoCuentaPagoLeyendaIndex $leyendasPago,
    ): string {
        $descripcion = trim(str_replace('*', ' ', $descripcionOrigen));

        if ($leyendasPago === null || ! self::esTipoOrdenPago($tipoComprobante) || $nroComprobante <= 0) {
            return $descripcion;
        }

        $leyenda = $leyendasPago->leyenda($tipoComprobante, $sucursal, $nroComprobante);

        return $leyenda ?? $descripcion;
    }

    /**
     * Emisor = código de proveedor (Anita: subd_emisor; COM/DEP en ctamov no lo traen).
     * Fallback l-mayor.c: primeros dígitos de la descripción (p. ej. "003615 EL SOL…" / "3980-MERCADO…").
     */
    public static function resolverEmisorProveedor(
        string $tipoComprobante,
        string $emisorOrigen,
        string $descripcionMovimiento = '',
    ): string {
        $emisor = trim($emisorOrigen);
        if ($emisor !== '') {
            return $emisor;
        }

        $tipo = strtoupper(trim($tipoComprobante));
        if (! in_array($tipo, ['COM', 'DEP'], true)) {
            return '';
        }

        if (preg_match('/^\s*(\d+)/', $descripcionMovimiento, $m) !== 1) {
            return '';
        }

        return ltrim($m[1], '0') !== '' ? $m[1] : '';
    }
}
