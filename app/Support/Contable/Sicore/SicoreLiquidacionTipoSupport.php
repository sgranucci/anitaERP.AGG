<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Tipo de liquidación SICORE (1Q / DJ) y cálculo de saldo a pagar ARCA (punto C+D).
 */
final class SicoreLiquidacionTipoSupport
{
    public const TIPO_1Q = '1q';

    public const TIPO_DJ = 'dj';

    /** @var list<string> */
    public const TIPOS = [self::TIPO_1Q, self::TIPO_DJ];

    /** @var list<string> */
    public const CODIGOS = ['217', '767', '787'];

    public static function normalizarTipo(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));

        return in_array($tipo, self::TIPOS, true) ? $tipo : self::TIPO_1Q;
    }

    public static function esDj(string $tipo): bool
    {
        return self::normalizarTipo($tipo) === self::TIPO_DJ;
    }

    /**
     * Mes de referencia (día 1) a partir de una fecha de pantalla o hoy.
     */
    public static function mesReferencia(?string $fechaIso): \Carbon\Carbon
    {
        if ($fechaIso !== null && $fechaIso !== '') {
            try {
                return \Carbon\Carbon::parse($fechaIso)->startOfMonth();
            } catch (\Throwable) {
            }
        }

        return \Carbon\Carbon::today()->startOfMonth();
    }

    /**
     * Rangos sugeridos (editables) según tipo.
     *
     * @return array{
     *   compras_desde: string,
     *   compras_hasta: string,
     *   sueldos_desde: string,
     *   sueldos_hasta: string
     * }
     */
    public static function sugerirRangos(string $tipo, ?string $fechaReferenciaIso = null): array
    {
        $mes = self::mesReferencia($fechaReferenciaIso);
        $mesAnt = $mes->copy()->subMonth();
        $tipo = self::normalizarTipo($tipo);

        if ($tipo === self::TIPO_1Q) {
            return [
                'compras_desde' => $mes->toDateString(),
                'compras_hasta' => $mes->copy()->day(15)->toDateString(),
                'sueldos_desde' => $mesAnt->toDateString(),
                // Hasta de sueldos: a mano; se sugiere el día 2 del mes de compras (fecha retención 4ta).
                'sueldos_hasta' => $mes->copy()->day(2)->toDateString(),
            ];
        }

        return [
            'compras_desde' => $mes->toDateString(),
            'compras_hasta' => $mes->copy()->endOfMonth()->toDateString(),
            'sueldos_desde' => $mesAnt->toDateString(),
            'sueldos_hasta' => $mes->copy()->day(2)->toDateString(),
        ];
    }

    /**
     * Parsea SAF e ingresos a cuenta del request (solo DJ; en 1Q quedan en cero).
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *   saf: array<string, float>,
     *   ingresos: array<string, array{fecha: string, importe: float}>
     * }
     */
    public static function parsearSafIngresos(string $tipo, array $input): array
    {
        $saf = [];
        $ingresos = [];
        foreach (self::CODIGOS as $cod) {
            $saf[$cod] = 0.0;
            $ingresos[$cod] = ['fecha' => '', 'importe' => 0.0];
        }

        if (! self::esDj($tipo)) {
            return ['saf' => $saf, 'ingresos' => $ingresos];
        }

        foreach (self::CODIGOS as $cod) {
            $saf[$cod] = self::parseImporte($input['saf'][$cod] ?? $input['saf_'.$cod] ?? 0);
            $importe = self::parseImporte($input['ingreso_importe'][$cod] ?? $input['ingreso_importe_'.$cod] ?? 0);
            $fecha = trim((string) ($input['ingreso_fecha'][$cod] ?? $input['ingreso_fecha_'.$cod] ?? ''));
            if ($importe > 0.0005 && $fecha === '') {
                throw new \InvalidArgumentException(
                    'Si hay ingreso a cuenta en el código '.$cod.', la fecha es obligatoria.'
                );
            }
            $ingresos[$cod] = [
                'fecha' => $fecha,
                'importe' => $importe,
            ];
        }

        return ['saf' => $saf, 'ingresos' => $ingresos];
    }

    /**
     * Arma el cuadro de liquidación (1Q o DJ) a partir de los totales por código.
     *
     * @param  array<string, array{q1: float, q2: float, total: float}>  $porCodigo
     * @param  array<string, float>  $saf
     * @param  array<string, array{fecha: string, importe: float}>  $ingresos
     * @return array<string, mixed>
     */
    public static function armarCuadro(
        string $tipo,
        array $porCodigo,
        array $saf,
        array $ingresos,
        string $comprasDesde,
        string $comprasHasta,
        string $sueldosDesde,
        string $sueldosHasta,
    ): array {
        $tipo = self::normalizarTipo($tipo);
        $nombres = [
            '217' => 'Ganancias',
            '767' => 'IVA',
            '787' => 'Ganancias 4ta cat.',
        ];

        if ($tipo === self::TIPO_1Q) {
            return self::armarCuadro1q($porCodigo, $nombres, $comprasDesde, $comprasHasta, $sueldosDesde, $sueldosHasta);
        }

        return self::armarCuadroDj($porCodigo, $saf, $ingresos, $nombres);
    }

    /**
     * @param  array<string, array{q1: float, q2: float, total: float}>  $porCodigo
     * @param  array<string, string>  $nombres
     * @return array<string, mixed>
     */
    private static function armarCuadro1q(
        array $porCodigo,
        array $nombres,
        string $comprasDesde,
        string $comprasHasta,
        string $sueldosDesde,
        string $sueldosHasta,
    ): array {
        $origenCompras = 'Compras '.self::fmtRangoCorto($comprasDesde, $comprasHasta);
        $origenSueldos = 'Sueldos '.self::fmtMesAnio($sueldosDesde !== '' ? $sueldosDesde : $sueldosHasta);
        $filas = [];
        $saldoPagar = [];
        $totalRet = 0.0;
        $totalPagar = 0.0;

        foreach (self::CODIGOS as $cod) {
            $part = $porCodigo[$cod] ?? ['q1' => 0.0, 'q2' => 0.0, 'total' => 0.0];
            $ret = round((float) ($part['total'] ?? 0), 2);
            $filas[] = [
                'codigo' => $cod,
                'impuesto' => $nombres[$cod],
                'origen' => $cod === '787' ? $origenSueldos : $origenCompras,
                'retenciones' => $ret,
                'a_pagar' => $ret,
            ];
            $saldoPagar[$cod] = $ret;
            $totalRet = round($totalRet + $ret, 2);
            $totalPagar = round($totalPagar + $ret, 2);
        }

        return [
            'tipo' => self::TIPO_1Q,
            'filas_1q' => $filas,
            'total_retenciones' => $totalRet,
            'total_a_pagar' => $totalPagar,
            'saldo_pagar' => $saldoPagar,
            'saldo_favor_siguiente' => array_fill_keys(self::CODIGOS, 0.0),
            'leyenda' => 'Pago a cuenta de la DJ '.self::fmtMesAnio($comprasDesde)
                .'. Se descuenta como ingreso a cuenta en la DJ mensual.',
        ];
    }

    /**
     * @param  array<string, array{q1: float, q2: float, total: float}>  $porCodigo
     * @param  array<string, float>  $saf
     * @param  array<string, array{fecha: string, importe: float}>  $ingresos
     * @param  array<string, string>  $nombres
     * @return array<string, mixed>
     */
    private static function armarCuadroDj(
        array $porCodigo,
        array $saf,
        array $ingresos,
        array $nombres,
    ): array {
        $totalesMes = [];
        $saldos = [];
        $ingresosDetalle = [];
        $saldoPagar = [];
        $saldoFavorSig = [];
        $sumQ1 = 0.0;
        $sumQ2 = 0.0;
        $sumSueldos = 0.0;
        $sumTotal = 0.0;
        $sumSaf = 0.0;
        $sumIng = 0.0;
        $sumPagar = 0.0;
        $sumFavor = 0.0;

        foreach (self::CODIGOS as $cod) {
            $part = $porCodigo[$cod] ?? ['q1' => 0.0, 'q2' => 0.0, 'total' => 0.0];
            if ($cod === '787') {
                $q1 = 0.0;
                $q2 = 0.0;
                $sueldos = round((float) ($part['total'] ?? 0), 2);
                $total = $sueldos;
            } else {
                $q1 = round((float) ($part['q1'] ?? 0), 2);
                $q2 = round((float) ($part['q2'] ?? 0), 2);
                $sueldos = null;
                $total = round($q1 + $q2, 2);
            }

            $safCod = round((float) ($saf[$cod] ?? 0), 2);
            $ing = $ingresos[$cod] ?? ['fecha' => '', 'importe' => 0.0];
            $ingImporte = round((float) ($ing['importe'] ?? 0), 2);
            $neto = round($total - $safCod - $ingImporte, 2);
            if ($neto >= -0.0005) {
                $aPagar = max(0.0, $neto);
                $aFavor = 0.0;
            } else {
                $aPagar = 0.0;
                $aFavor = round(abs($neto), 2);
            }

            $totalesMes[] = [
                'codigo' => $cod,
                'impuesto' => $nombres[$cod],
                'q1' => $q1,
                'q2' => $q2,
                'sueldos' => $sueldos,
                'total' => $total,
            ];
            $saldos[] = [
                'codigo' => $cod,
                'total' => $total,
                'saf' => $safCod,
                'ingresos' => $ingImporte,
                'a_pagar' => $aPagar,
                'a_favor_sig' => $aFavor,
            ];
            if ($ingImporte > 0.0005) {
                $ingresosDetalle[] = [
                    'codigo' => $cod,
                    'fecha' => (string) ($ing['fecha'] ?? ''),
                    'importe' => $ingImporte,
                ];
            }
            $saldoPagar[$cod] = $aPagar;
            $saldoFavorSig[$cod] = $aFavor;

            $sumQ1 = round($sumQ1 + $q1, 2);
            $sumQ2 = round($sumQ2 + $q2, 2);
            $sumSueldos = round($sumSueldos + (float) ($sueldos ?? 0), 2);
            $sumTotal = round($sumTotal + $total, 2);
            $sumSaf = round($sumSaf + $safCod, 2);
            $sumIng = round($sumIng + $ingImporte, 2);
            $sumPagar = round($sumPagar + $aPagar, 2);
            $sumFavor = round($sumFavor + $aFavor, 2);
        }

        return [
            'tipo' => self::TIPO_DJ,
            'totales_mes' => $totalesMes,
            'totales_mes_sumas' => [
                'q1' => $sumQ1,
                'q2' => $sumQ2,
                'sueldos' => $sumSueldos,
                'total' => $sumTotal,
            ],
            'saldos' => $saldos,
            'saldos_sumas' => [
                'total' => $sumTotal,
                'saf' => $sumSaf,
                'ingresos' => $sumIng,
                'a_pagar' => $sumPagar,
                'a_favor_sig' => $sumFavor,
            ],
            'ingresos_detalle' => $ingresosDetalle,
            'saldo_pagar' => $saldoPagar,
            'saldo_favor_siguiente' => $saldoFavorSig,
            'total_a_pagar' => $sumPagar,
        ];
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return self::esDj($tipo) ? 'DJ' : '1Q';
    }

    public static function nombreZip(string $empresaCorta, string $tipo, string $comprasDesde): string
    {
        $mes = self::mesReferencia($comprasDesde);
        $tag = self::etiquetaTipo($tipo);
        $emp = preg_replace('/[^A-Za-z0-9_-]+/', '', $empresaCorta) ?: 'EMPRESA';

        return sprintf('SICORE %s %s %s.zip', $emp, $tag, $mes->format('m-Y'));
    }

    public static function empresaCorta(?string $nombre): string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return 'EMPRESA';
        }
        $parts = preg_split('/\s+/', $nombre) ?: [];

        return strtoupper((string) ($parts[0] ?? 'EMPRESA'));
    }

    public static function parseImporte(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }
        $s = trim((string) $valor);
        if ($s === '' || $s === '—' || $s === '-') {
            return 0.0;
        }
        // 1.234.567,89 → 1234567.89
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return round((float) $s, 2);
    }

    private static function fmtRangoCorto(string $desde, string $hasta): string
    {
        if ($desde === '' || $hasta === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($desde)->format('d/m')
                .' al '
                .\Carbon\Carbon::parse($hasta)->format('d/m');
        } catch (\Throwable) {
            return $desde.' — '.$hasta;
        }
    }

    private static function fmtMesAnio(string $fechaIso): string
    {
        if ($fechaIso === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($fechaIso)->format('m/Y');
        } catch (\Throwable) {
            return $fechaIso;
        }
    }
}
