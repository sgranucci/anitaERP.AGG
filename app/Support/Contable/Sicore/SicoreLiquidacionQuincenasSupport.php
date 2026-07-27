<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Partición de importes SICORE en 1ra / 2da quincena (reglas de liquidación BSA).
 */
final class SicoreLiquidacionQuincenasSupport
{
    public const CODIGO_IVA = 767;

    public const CODIGO_GANANCIAS = 217;

    public const CODIGO_SUELDOS = 787;

    /** @var list<int> */
    public const CODIGOS = [self::CODIGO_IVA, self::CODIGO_GANANCIAS, self::CODIGO_SUELDOS];

    /**
     * Códigos cuyo importe completo va a 1ra quincena (sueldos: mes anterior).
     *
     * @var array<int, true>
     */
    private const SOLO_1Q = [
        self::CODIGO_SUELDOS => true,
    ];

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array{q1: float, q2: float, total: float, por_fecha: array<string, float>}
     */
    public static function repartirCodigo(array $registros, int $codigoImpuesto): array
    {
        $porFecha = [];
        foreach ($registros as $reg) {
            if ((int) ($reg['cod_impuesto'] ?? 0) !== $codigoImpuesto) {
                continue;
            }
            $fecha = (string) ($reg['fecha_retencion'] ?? $reg['fecha_comp'] ?? '');
            if ($fecha === '') {
                continue;
            }
            $importe = (float) ($reg['importe'] ?? 0);
            $porFecha[$fecha] = round(($porFecha[$fecha] ?? 0) + $importe, 2);
        }

        return self::quincenasDesdePorFecha($porFecha, isset(self::SOLO_1Q[$codigoImpuesto]));
    }

    /**
     * @param  array<string, float>  $porFecha  fecha ISO => importe
     * @return array{q1: float, q2: float, total: float, por_fecha: array<string, float>}
     */
    public static function quincenasDesdePorFecha(array $porFecha, bool $solo1q): array
    {
        $q1 = 0.0;
        $q2 = 0.0;
        ksort($porFecha);

        foreach ($porFecha as $fecha => $importe) {
            $importe = round((float) $importe, 2);
            if ($solo1q) {
                $q1 += $importe;
                continue;
            }
            $dia = (int) substr((string) $fecha, 8, 2);
            if ($dia <= 15) {
                $q1 += $importe;
            } else {
                $q2 += $importe;
            }
        }

        $q1 = round($q1, 2);
        $q2 = round($q2, 2);

        return [
            'q1' => $q1,
            'q2' => $q2,
            'total' => round($q1 + $q2, 2),
            'por_fecha' => $porFecha,
        ];
    }

    /**
     * Mes calendario completo de la fecha de referencia (día 1 → fin de mes).
     *
     * @return array{0: string, 1: string}
     */
    public static function rangoMismoMes(string $fechaReferenciaIso): array
    {
        $ref = \Carbon\Carbon::parse($fechaReferenciaIso)->startOfMonth();

        return [
            $ref->toDateString(),
            $ref->copy()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Mes calendario anterior completo al mes de $fechaHastaIso.
     *
     * @return array{0: string, 1: string} [desde, hasta] ISO
     */
    public static function rangoMesAnterior(string $fechaHastaIso): array
    {
        $ref = \Carbon\Carbon::parse($fechaHastaIso)->startOfMonth()->subMonth();

        return [
            $ref->toDateString(),
            $ref->copy()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * 1ra quincena del mes siguiente al de $fechaHastaIso.
     *
     * @return array{0: string, 1: string}
     */
    public static function rangoMesSiguientePrimeraQuincena(string $fechaHastaIso): array
    {
        $ref = \Carbon\Carbon::parse($fechaHastaIso)->startOfMonth()->addMonth();

        return [
            $ref->toDateString(),
            $ref->copy()->day(15)->toDateString(),
        ];
    }

    /**
     * Resuelve rangos compras/sueldos según lo consultado en pantalla + lo pedido en el modal.
     * Si falta un rango, sugiere el mismo mes calendario del "desde" ya consultado.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   compras_desde: string,
     *   compras_hasta: string,
     *   sueldos_desde: string,
     *   sueldos_hasta: string,
     *   criterio_pantalla: string
     * }
     */
    public static function resolverRangosLiquidacion(array $filtros): array
    {
        $criterio = (string) ($filtros['criterio'] ?? '');
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $comprasDesde = (string) ($filtros['compras_fecha_desde'] ?? '');
        $comprasHasta = (string) ($filtros['compras_fecha_hasta'] ?? '');
        $sueldosDesde = (string) ($filtros['sueldos_fecha_desde'] ?? '');
        $sueldosHasta = (string) ($filtros['sueldos_fecha_hasta'] ?? '');

        if ($criterio === SicoreCriteriosSupport::COMPRAS || $criterio === SicoreCriteriosSupport::VENTAS) {
            if ($comprasDesde === '' || $comprasHasta === '') {
                $comprasDesde = $desde;
                $comprasHasta = $hasta;
            }
            if ($sueldosDesde === '' || $sueldosHasta === '') {
                $ref = $comprasDesde !== '' ? $comprasDesde : ($desde !== '' ? $desde : $hasta);
                [$sueldosDesde, $sueldosHasta] = self::rangoMismoMes($ref);
            }
        } elseif ($criterio === SicoreCriteriosSupport::SUELDOS) {
            if ($sueldosDesde === '' || $sueldosHasta === '') {
                $sueldosDesde = $desde;
                $sueldosHasta = $hasta;
            }
            if ($comprasDesde === '' || $comprasHasta === '') {
                $ref = $sueldosDesde !== '' ? $sueldosDesde : ($desde !== '' ? $desde : $hasta);
                [$comprasDesde, $comprasHasta] = self::rangoMismoMes($ref);
            }
        } else {
            if ($comprasDesde === '' || $comprasHasta === '') {
                $comprasDesde = $desde;
                $comprasHasta = $hasta;
            }
            if ($sueldosDesde === '' || $sueldosHasta === '') {
                $ref = $comprasDesde !== '' ? $comprasDesde : ($desde !== '' ? $desde : $hasta);
                [$sueldosDesde, $sueldosHasta] = self::rangoMismoMes($ref);
            }
        }

        return [
            'compras_desde' => $comprasDesde,
            'compras_hasta' => $comprasHasta,
            'sueldos_desde' => $sueldosDesde,
            'sueldos_hasta' => $sueldosHasta,
            'criterio_pantalla' => $criterio,
        ];
    }

    public static function etiquetaPeriodo(string $fechaDesdeIso, string $fechaHastaIso): string
    {
        $desde = \Carbon\Carbon::parse($fechaDesdeIso);
        $hasta = \Carbon\Carbon::parse($fechaHastaIso);
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes = $meses[(int) $hasta->month] ?? $hasta->format('m');
        $anio = (int) $hasta->year;

        if ((int) $desde->day === 1 && (int) $hasta->day === 15
            && $desde->isSameMonth($hasta)) {
            return $mes.' '.$anio.'  ·  1ra Quincena';
        }
        if ((int) $desde->day === 16 && $desde->isSameMonth($hasta)
            && (int) $hasta->day === (int) $hasta->copy()->endOfMonth()->day) {
            return $mes.' '.$anio.'  ·  2da Quincena';
        }
        if ($desde->isSameMonth($hasta) && (int) $desde->day === 1
            && (int) $hasta->day === (int) $hasta->copy()->endOfMonth()->day) {
            return $mes.' '.$anio.'  ·  Mes completo';
        }

        return $desde->format('d/m/Y').' — '.$hasta->format('d/m/Y');
    }
}
