<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Support\Collection;

/**
 * Detalle calzado (color/combinación + talles) de una TRA, tomado del movimiento de salida.
 */
final class TransferenciaMercaderiaDetalleFerliSupport
{
    /**
     * @return array<int, array{
     *     combinacion_id:?int,
     *     combinacion_etiqueta:string,
     *     modulo_id:?int,
     *     medidas:list<array{medida:string,cantidad:float,talle_id:int}>,
     *     medidas_txt:string
     * }>
     */
    public static function porItemDesdeSalida(Transferencia_Mercaderia $transferencia): array
    {
        if (! MovimientoStockFerliSupport::esCalzadosFerli()) {
            return [];
        }

        $salidaId = (int) ($transferencia->movimientostock_salida_id ?? 0);
        if ($salidaId <= 0) {
            return [];
        }

        $mov = MovimientoStock::query()
            ->with([
                'articulos_movimiento.combinaciones',
                'articulos_movimiento.articulo_movimiento_talles.talles',
            ])
            ->find($salidaId);

        if ($mov === null) {
            return [];
        }

        $out = [];
        $idx = 0;
        foreach ($mov->articulos_movimiento as $am) {
            $idx++;
            $out[$idx] = self::desdeArticuloMovimiento($am);
        }

        return $out;
    }

    /**
     * Payload de líneas para armar entrada/copia de un movimiento de stock ya grabado.
     *
     * @return list<array<string, mixed>>
     */
    public static function lineasPayloadDesdeMovimiento(?MovimientoStock $movimiento): array
    {
        if ($movimiento === null) {
            return [];
        }

        $movimiento->loadMissing([
            'articulos_movimiento.articulo_movimiento_talles.talles',
        ]);

        $lineas = [];
        foreach ($movimiento->articulos_movimiento as $am) {
            $medidas = [];
            foreach ($am->articulo_movimiento_talles ?? [] as $talle) {
                $medidas[] = [
                    'medida' => (string) (optional($talle->talles)->nombre ?? ''),
                    'cantidad' => abs((float) ($talle->cantidad ?? 0)),
                    'precio' => (float) ($talle->precio ?? 0),
                    'listaprecio' => null,
                    'incluyeimpuesto' => null,
                    'moneda' => null,
                    'talle_id' => (int) ($talle->talle_id ?? 0),
                ];
            }

            $lineas[] = [
                'articulo_id' => (int) $am->articulo_id,
                'cantidad' => abs((float) $am->cantidad),
                'combinacion_id' => $am->combinacion_id ? (int) $am->combinacion_id : null,
                'modulo_id' => $am->modulo_id ? (int) $am->modulo_id : null,
                'medidas' => $medidas !== [] ? json_encode($medidas, JSON_UNESCAPED_UNICODE) : '',
                'numeroparte' => (string) ($am->numeroparte ?? ''),
                'precio' => (float) ($am->precio ?? $am->costo ?? 0),
            ];
        }

        return $lineas;
    }

    /**
     * @return array{
     *     combinacion_id:?int,
     *     combinacion_etiqueta:string,
     *     modulo_id:?int,
     *     medidas:list<array{medida:string,cantidad:float,talle_id:int}>,
     *     medidas_txt:string
     * }
     */
    public static function desdeArticuloMovimiento(Articulo_Movimiento $am): array
    {
        $comb = $am->combinaciones;
        $etiqueta = '';
        if ($comb) {
            $etiqueta = trim((string) ($comb->codigo ?? '').'-'.(string) ($comb->nombre ?? ''), '-');
        }

        $medidas = [];
        foreach ($am->articulo_movimiento_talles ?? [] as $talle) {
            $cant = abs((float) ($talle->cantidad ?? 0));
            if ($cant <= 0) {
                continue;
            }
            $label = (string) (optional($talle->talles)->nombre ?? '');
            $medidas[] = [
                'medida' => $label,
                'cantidad' => $cant,
                'talle_id' => (int) ($talle->talle_id ?? 0),
            ];
        }

        $partes = [];
        foreach ($medidas as $m) {
            $partes[] = $m['medida'].':'.rtrim(rtrim(number_format($m['cantidad'], 2, '.', ''), '0'), '.');
        }

        return [
            'combinacion_id' => $am->combinacion_id ? (int) $am->combinacion_id : null,
            'combinacion_etiqueta' => $etiqueta,
            'modulo_id' => $am->modulo_id ? (int) $am->modulo_id : null,
            'medidas' => $medidas,
            'medidas_txt' => implode(' · ', $partes),
        ];
    }

    /**
     * @param  Collection<int, Articulo_Movimiento>|null  $movimientos
     * @return array<int, array<string, mixed>>
     */
    public static function indexarPorArticuloId(?Collection $movimientos): array
    {
        if ($movimientos === null || $movimientos->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($movimientos as $am) {
            $map[(int) $am->articulo_id] = self::desdeArticuloMovimiento($am);
        }

        return $map;
    }
}
