<?php

namespace App\Support\Ventas\Gastronomia;

use InvalidArgumentException;

/**
 * Agrupa comandas atómicas de facturación en lotes CF (monto fijo o ~% del tope ARCA).
 */
final class CierreJornadaProcesoFacturaLotesSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *   lotes: list<array{
     *     numero:int,
     *     comandas:list<array<string,mixed>>,
     *     total:float,
     *     cantidad_comandas:int,
     *     waitry_order_ids:list<int>
     *   }>,
     *   comandas_ajuste: list<array<string,mixed>>,
     *   total_factura: float,
     *   total_ajuste: float,
     *   total_grupo: float,
     *   tope_cf: float,
     *   objetivo_lote: float,
     *   porcentaje_lote: float,
     *   cuadre_ok: bool,
     *   cantidad_comandas_factura: int,
     *   cantidad_comandas_ajuste: int
     * }
     */
    public static function armarPlanDesdeMovimientos(
        array $movimientos,
        ?float $topeCf = null,
        ?float $pctLote = null,
        ?float $montoLote = null,
    ): array {
        $clasificacion = CierreJornadaProcesoFacturaComandasSupport::clasificar($movimientos);
        $comandasFactura = $clasificacion['facturar'];
        $comandasAjuste = $clasificacion['ajuste'];

        $topeCf = $topeCf ?? (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 0);
        $pctLote = $pctLote ?? (float) config('gastronomia.cierre_jornada_cf_lote_porcentaje_tope', 20);
        $montoLote = $montoLote ?? (float) config('gastronomia.cierre_jornada_cf_lote_monto', 0);
        $objetivoLote = self::objetivoLote($topeCf, $pctLote, $montoLote);

        $lotes = self::armarLotes($comandasFactura, $topeCf, $objetivoLote);

        $totalFactura = CierreJornadaProcesoFacturaComandasSupport::totalComandas($comandasFactura);
        $totalAjuste = CierreJornadaProcesoFacturaComandasSupport::totalComandas($comandasAjuste);
        $totalGrupo = round($totalFactura + $totalAjuste, 2);
        $totalGrupoEsperado = CierreJornadaProcesoFacturaComandasSupport::totalComandas(
            CierreJornadaProcesoFacturaComandasSupport::movimientosGrupoSinFacturar($movimientos),
        );

        return [
            'lotes' => $lotes,
            'comandas_ajuste' => $comandasAjuste,
            'total_factura' => $totalFactura,
            'total_ajuste' => $totalAjuste,
            'total_grupo' => $totalGrupo,
            'tope_cf' => round($topeCf, 2),
            'objetivo_lote' => $objetivoLote,
            'porcentaje_lote' => round($pctLote, 4),
            'cuadre_ok' => abs($totalGrupo - $totalGrupoEsperado) <= 0.02,
            'cantidad_comandas_factura' => count($comandasFactura),
            'cantidad_comandas_ajuste' => count($comandasAjuste),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $comandasFactura
     * @return list<array{
     *   numero:int,
     *   comandas:list<array<string,mixed>>,
     *   total:float,
     *   cantidad_comandas:int,
     *   waitry_order_ids:list<int>
     * }>
     */
    public static function armarLotes(array $comandasFactura, float $topeCf, float $objetivoLote): array
    {
        if ($comandasFactura === []) {
            return [];
        }

        if ($topeCf <= 0.) {
            throw new InvalidArgumentException(
                'Configure ARca consumidor final umbral (ARCA_WSFE_RECEPTOR_CF_UMBRAL_MONTO) para armar lotes CF.',
            );
        }

        $ordenadas = $comandasFactura;
        usort($ordenadas, static function (array $a, array $b): int {
            $ta = CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($a);
            $tb = CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($b);
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            return (int) ($a['waitry_order_id'] ?? 0) <=> (int) ($b['waitry_order_id'] ?? 0);
        });

        $lotes = [];
        /** @var list<array<string, mixed>> $actual */
        $actual = [];
        $totalActual = 0.;

        foreach ($ordenadas as $comanda) {
            $monto = CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($comanda);
            if ($monto <= 0.0001) {
                continue;
            }

            if ($monto > $topeCf + 0.0001) {
                $orderId = (int) ($comanda['waitry_order_id'] ?? 0);
                throw new InvalidArgumentException(
                    'La comanda Waitry #'.$orderId.' supera el tope CF ('.round($topeCf, 2).').',
                );
            }

            if ($actual !== [] && $totalActual + $monto > $topeCf + 0.0001) {
                $lotes[] = self::cerrarLote($actual, $totalActual, count($lotes) + 1);
                $actual = [];
                $totalActual = 0.;
            }

            if ($actual !== [] && $objetivoLote > 0. && $totalActual >= $objetivoLote - 0.0001) {
                $lotes[] = self::cerrarLote($actual, $totalActual, count($lotes) + 1);
                $actual = [];
                $totalActual = 0.;
            }

            $actual[] = $comanda;
            $totalActual = round($totalActual + $monto, 2);
        }

        if ($actual !== []) {
            $lotes[] = self::cerrarLote($actual, $totalActual, count($lotes) + 1);
        }

        return $lotes;
    }

    public static function objetivoLote(float $topeCf, float $pctLote, float $montoLote): float
    {
        if ($montoLote > 0.) {
            $objetivo = round($montoLote, 2);

            return $topeCf > 0. ? min($objetivo, round($topeCf, 2)) : $objetivo;
        }

        return $topeCf > 0. ? round($topeCf * $pctLote / 100., 2) : 0.;
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     * @return array{
     *   numero:int,
     *   comandas:list<array<string,mixed>>,
     *   total:float,
     *   cantidad_comandas:int,
     *   waitry_order_ids:list<int>
     * }
     */
    private static function cerrarLote(array $comandas, float $total, int $numero): array
    {
        $waitryIds = [];
        foreach ($comandas as $comanda) {
            $id = (int) ($comanda['waitry_order_id'] ?? 0);
            if ($id > 0) {
                $waitryIds[] = $id;
            }
        }

        return [
            'numero' => $numero,
            'comandas' => $comandas,
            'total' => round($total, 2),
            'cantidad_comandas' => count($comandas),
            'waitry_order_ids' => array_values(array_unique($waitryIds)),
        ];
    }
}
