<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use Illuminate\Support\Collection;

/**
 * Totales y métricas del reporte flash (día único e histórico multi-día).
 */
final class FlashCajaReporteSupport
{
    /** @var list<string> */
    private const CAMPOS_DECIMAL = [
        'ayb', 'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count',
        'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul',
        'bingo_total_venta', 'bingo_resultado',
        'win_ol_slot', 'win_ol_rul', 'estac', 'show',
    ];

    /** @var list<string> */
    private const CAMPOS_ENTERO = [
        'att', 'cant_slots', 'cant_rul', 'bingo_cant_carton', 'pos_online', 'cant_vehic',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function armar(FlashCaja $flash): array
    {
        return self::armarDesdeRegistro($flash, $flash->fecha?->format('d/m/Y') ?? '');
    }

    /**
     * @param  Collection<int, FlashCaja>  $filas
     * @return array<string, mixed>
     */
    public static function armarHistorico(
        Collection $filas,
        ?\App\Models\Configuracion\Empresa $empresa,
        string $fechaDesde,
        string $fechaHasta,
    ): array {
        $consolidado = self::consolidar($filas);
        $periodo = self::formatearPeriodo($fechaDesde, $fechaHasta);

        $filasDiarias = $filas->map(function (FlashCaja $flash) {
            $item = self::armar($flash);
            $item['fecha_iso'] = $flash->fecha?->format('Y-m-d') ?? '';
            $item['id'] = $flash->id;

            return $item;
        })->values()->all();

        $reporteConsolidado = self::armarDesdeRegistro($consolidado, $periodo);
        $reporteConsolidado['es_historico'] = true;
        $reporteConsolidado['cantidad_dias'] = $filas->count();
        $reporteConsolidado['filas_diarias'] = $filasDiarias;
        $reporteConsolidado['fecha_desde'] = $fechaDesde;
        $reporteConsolidado['fecha_hasta'] = $fechaHasta;
        $reporteConsolidado['periodo'] = $periodo;
        $reporteConsolidado['empresa'] = $empresa;

        return $reporteConsolidado;
    }

    /**
     * @param  Collection<int, FlashCaja>  $filas
     */
    public static function consolidar(Collection $filas): FlashCaja
    {
        $base = new FlashCaja();
        foreach (self::CAMPOS_DECIMAL as $campo) {
            $base->{$campo} = 0.0;
        }
        foreach (self::CAMPOS_ENTERO as $campo) {
            $base->{$campo} = 0;
        }

        foreach ($filas as $fila) {
            foreach (self::CAMPOS_DECIMAL as $campo) {
                $base->{$campo} = round((float) $base->{$campo} + (float) ($fila->{$campo} ?? 0), 2);
            }
            foreach (self::CAMPOS_ENTERO as $campo) {
                $base->{$campo} = (int) $base->{$campo} + (int) ($fila->{$campo} ?? 0);
            }
        }

        if ($filas->isNotEmpty()) {
            $base->setRelation('empresa', $filas->first()->empresa);
            $base->empresa_id = (int) $filas->first()->empresa_id;
        }

        return $base;
    }

    public static function totalGamingDesdeRegistro(FlashCaja $flash): float
    {
        return round(
            (float) $flash->win_ol_slot
            + (float) $flash->win_ol_rul
            + (float) $flash->bingo_resultado,
            2
        );
    }

    public static function totalRevenuesDesdeRegistro(FlashCaja $flash): float
    {
        return round(
            self::totalGamingDesdeRegistro($flash)
            + (float) $flash->ayb
            + (float) $flash->estac
            + (float) $flash->show,
            2
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarDesdeRegistro(FlashCaja $flash, string $fechaEtiqueta): array
    {
        $slotWin = (float) $flash->slot_r;
        $rulWin = (float) $flash->rul_r;
        $bingoWin = (float) $flash->bingo_resultado;
        $gaming = self::totalGamingDesdeRegistro($flash);
        $revenues = self::totalRevenuesDesdeRegistro($flash);

        return [
            'flash' => $flash,
            'empresa' => $flash->empresa,
            'fecha' => $fechaEtiqueta,
            'slot_drop' => (float) $flash->slot_d,
            'slot_win' => $slotWin,
            'slot_coin_in' => (float) $flash->slot_coin_in,
            'rul_drop' => (float) $flash->rul_d,
            'rul_win' => $rulWin,
            'bingo_venta' => (float) $flash->bingo_total_venta,
            'bingo_win' => $bingoWin,
            'total_gaming' => $gaming,
            'total_revenues' => $revenues,
            'attendance' => $flash->att,
            'es_historico' => false,
        ];
    }

    public static function formatearPeriodo(string $fechaDesde, string $fechaHasta): string
    {
        $desde = self::formatearFecha($fechaDesde);
        $hasta = self::formatearFecha($fechaHasta);

        if ($desde === $hasta) {
            return $desde;
        }

        return $desde.' - '.$hasta;
    }

    private static function formatearFecha(string $fecha): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $fecha;
    }
}
