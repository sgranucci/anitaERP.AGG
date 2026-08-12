<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Tramos de vencimiento del informe de proyección de pagos.
 *
 * Reproduce la apertura de columnas de Anita l-proy.c: tramos por días o por mes,
 * saldo anterior opcional y columna posterior. En «A vencer» los límites avanzan
 * desde la fecha base; en «Vencidos» retroceden.
 */
final class ProyeccionPagosTramosSupport
{
    public const CLAVE_SALDO_ANTERIOR = 'saldo_anterior';

    public const CLAVE_POSTERIOR = 'posterior';

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     a_vencer: bool,
     *     fecha_base: string,
     *     fecha_anterior: ?string,
     *     abre_anterior: bool,
     *     tramos: list<array{clave: string, etiqueta: string, limite: string, desde: ?string, valor: int}>
     * }
     */
    public static function construir(array $filtros): array
    {
        $aVencer = ($filtros['tipo_informe'] ?? ProyeccionPagosReporteFiltros::INFORME_A_VENCER)
            === ProyeccionPagosReporteFiltros::INFORME_A_VENCER;
        $porMes = ($filtros['tipo_vencimiento'] ?? ProyeccionPagosReporteFiltros::VENCIMIENTO_DIAS)
            === ProyeccionPagosReporteFiltros::VENCIMIENTO_MES;
        $fechaBase = Carbon::parse((string) ($filtros['fecha_base'] ?? Carbon::now()->format('Y-m-d')))->startOfDay();
        $valores = ProyeccionPagosReporteFiltros::tramos($filtros);

        $abreAnterior = ! empty($filtros['abre_anterior']);
        $diasAnterior = max(0, (int) ($filtros['dias_anterior'] ?? 0));
        $fechaAnterior = null;
        if ($abreAnterior && $diasAnterior > 0) {
            $fechaAnterior = $aVencer
                ? $fechaBase->copy()->subDays($diasAnterior)->format('Y-m-d')
                : $fechaBase->copy()->addDays($diasAnterior)->format('Y-m-d');
        }

        $tramos = $porMes
            ? self::tramosPorMes($valores, $fechaBase, $aVencer)
            : self::tramosPorDias($valores, $fechaBase, $aVencer);

        return [
            'a_vencer' => $aVencer,
            'fecha_base' => $fechaBase->format('Y-m-d'),
            'fecha_anterior' => $fechaAnterior,
            'abre_anterior' => $abreAnterior && $fechaAnterior !== null,
            'tramos' => $tramos,
        ];
    }

    /**
     * Clave de la columna de vencimiento que corresponde a la fecha del movimiento.
     *
     * Los movimientos ya vencidos (o aún no vencidos, en el informe de vencidos) caen en
     * el primer tramo, salvo que se abra el saldo anterior y queden fuera de esa ventana.
     *
     * @param  array<string, mixed>  $definicion  Resultado de construir()
     */
    public static function claveTramo(array $definicion, ?string $fechaVencimiento): string
    {
        $tramos = $definicion['tramos'] ?? [];
        $primera = $tramos[0]['clave'] ?? self::CLAVE_POSTERIOR;

        if ($fechaVencimiento === null || trim($fechaVencimiento) === '') {
            return $primera;
        }

        $vto = substr($fechaVencimiento, 0, 10);
        $base = (string) $definicion['fecha_base'];
        $aVencer = (bool) $definicion['a_vencer'];
        $anterior = $definicion['fecha_anterior'] ?? null;

        if ($aVencer) {
            if ($vto <= $base) {
                return ($definicion['abre_anterior'] && $anterior !== null && $vto <= $anterior)
                    ? self::CLAVE_SALDO_ANTERIOR
                    : $primera;
            }

            $limiteAnterior = $base;
            foreach ($tramos as $tramo) {
                if ($vto <= $tramo['limite'] && $vto > $limiteAnterior) {
                    return $tramo['clave'];
                }
                $limiteAnterior = $tramo['limite'];
            }

            return self::CLAVE_POSTERIOR;
        }

        if ($vto >= $base) {
            return ($definicion['abre_anterior'] && $anterior !== null && $vto >= $anterior)
                ? self::CLAVE_SALDO_ANTERIOR
                : $primera;
        }

        $limiteAnterior = $base;
        foreach ($tramos as $tramo) {
            if ($vto >= $tramo['limite'] && $vto < $limiteAnterior) {
                return $tramo['clave'];
            }
            $limiteAnterior = $tramo['limite'];
        }

        return self::CLAVE_POSTERIOR;
    }

    /** @param array<string, mixed> $definicion */
    public static function etiquetaTramo(array $definicion, string $clave): string
    {
        if ($clave === self::CLAVE_SALDO_ANTERIOR) {
            return self::etiquetaSaldoAnterior($definicion);
        }

        if ($clave === self::CLAVE_POSTERIOR) {
            return 'Posterior';
        }

        foreach ($definicion['tramos'] ?? [] as $tramo) {
            if ($tramo['clave'] === $clave) {
                return $tramo['etiqueta'];
            }
        }

        return $clave;
    }

    /** @param array<string, mixed> $definicion */
    public static function etiquetaSaldoAnterior(array $definicion): string
    {
        $dias = 0;
        $base = Carbon::parse((string) $definicion['fecha_base']);
        if (! empty($definicion['fecha_anterior'])) {
            $dias = (int) abs($base->diffInDays(Carbon::parse((string) $definicion['fecha_anterior'])));
        }

        return ((bool) $definicion['a_vencer'] ? 'Vencidos +' : 'A vencer +').$dias.'d';
    }

    /**
     * @param  list<int>  $dias
     * @return list<array{clave: string, etiqueta: string, limite: string, desde: ?string, valor: int}>
     */
    private static function tramosPorDias(array $dias, Carbon $fechaBase, bool $aVencer): array
    {
        $tramos = [];
        $indice = 0;
        $limiteAnterior = $fechaBase->format('Y-m-d');

        foreach ($dias as $cantidad) {
            $indice++;
            $limite = $aVencer
                ? $fechaBase->copy()->addDays($cantidad)
                : $fechaBase->copy()->subDays($cantidad);

            $tramos[] = [
                'clave' => 'tramo_'.$indice,
                'etiqueta' => $limite->format('d/m/y').' ('.str_pad((string) $cantidad, 3, '0', STR_PAD_LEFT).')',
                'limite' => $limite->format('Y-m-d'),
                'desde' => $limiteAnterior,
                'valor' => $cantidad,
            ];
            $limiteAnterior = $limite->format('Y-m-d');
        }

        return $tramos;
    }

    /**
     * @param  list<int>  $meses
     * @return list<array{clave: string, etiqueta: string, limite: string, desde: ?string, valor: int}>
     */
    private static function tramosPorMes(array $meses, Carbon $fechaBase, bool $aVencer): array
    {
        $tramos = [];
        $indice = 0;
        $anio = (int) $fechaBase->format('Y');
        $mesPrevio = null;
        $limiteAnterior = $fechaBase->format('Y-m-d');

        foreach ($meses as $mes) {
            $indice++;

            if ($mesPrevio !== null) {
                if ($aVencer && $mes < $mesPrevio) {
                    $anio++;
                } elseif (! $aVencer && $mes > $mesPrevio) {
                    $anio--;
                }
            }
            $mesPrevio = $mes;

            $referencia = Carbon::create($anio, $mes, 1)->startOfDay();
            $limite = $aVencer ? $referencia->copy()->endOfMonth() : $referencia->copy()->startOfMonth();

            $tramos[] = [
                'clave' => 'tramo_'.$indice,
                'etiqueta' => self::nombreMes($mes).' '.$referencia->format('y'),
                'limite' => $limite->format('Y-m-d'),
                'desde' => $limiteAnterior,
                'valor' => $mes,
            ];
            $limiteAnterior = $limite->format('Y-m-d');
        }

        return $tramos;
    }

    private static function nombreMes(int $mes): string
    {
        $nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $nombres[$mes] ?? (string) $mes;
    }
}
