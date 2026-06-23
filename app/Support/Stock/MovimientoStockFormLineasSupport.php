<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\MovimientoStock;
use Illuminate\Support\Collection;

final class MovimientoStockFormLineasSupport
{
    /**
     * @return Collection<int, \App\Models\Stock\Articulo_Movimiento|object>
     */
    public static function lineasParaFormulario(MovimientoStock $movimientostock): Collection
    {
        $articulosOld = old('articulos_id');
        if (is_array($articulosOld) && count(array_filter($articulosOld, static fn ($id) => (int) $id > 0)) > 0) {
            return self::desdeOldInput();
        }

        if ($movimientostock->exists) {
            $movimientostock->loadMissing([
                'articulos_movimiento.articulos.unidadesdemedidas',
                'articulos_movimiento.articulos.unidadesdemedidasalternativas',
                'articulos_movimiento.combinaciones',
                'articulos_movimiento.articulo_movimiento_talles.talles',
            ]);
        }

        return $movimientostock->articulos_movimiento ?? collect();
    }

    public static function valorLinea(int $index, string $campoOld, mixed $fallback = ''): string
    {
        $valor = old($campoOld.'.'.$index, $fallback);

        if (is_array($valor)) {
            return '';
        }

        if ($valor === null) {
            return '';
        }

        return (string) $valor;
    }

    public static function medidasHidden(int $index, mixed $pedidoitem): string
    {
        $old = old('medidas.'.$index);
        if ($old !== null) {
            return is_string($old) ? $old : (is_array($old) ? json_encode($old) : (string) $old);
        }

        $talles = $pedidoitem->articulo_movimiento_talles ?? null;
        if ($talles instanceof Collection && $talles->isNotEmpty()) {
            $payload = $talles->map(static function ($talle) {
                return [
                    'medida' => optional($talle->talles)->nombre ?? '',
                    'cantidad' => abs((float) ($talle->cantidad ?? 0)),
                    'precio' => (float) ($talle->precio ?? 0),
                    'listaprecio' => null,
                    'incluyeimpuesto' => null,
                    'moneda' => null,
                    'talle_id' => (int) ($talle->talle_id ?? 0),
                ];
            })->values()->all();

            return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_string($talles)) {
            return $talles;
        }

        return '';
    }

    /**
     * @return Collection<int, object>
     */
    private static function desdeOldInput(): Collection
    {
        $articulosIds = old('articulos_id', []);
        $cantidades = old('cantidades', []);
        $piezas = old('piezas', []);
        $precios = old('precios', []);

        $idsValidos = array_values(array_filter(array_map(static fn ($id) => (int) $id, $articulosIds)));
        $articuloModels = $idsValidos === []
            ? collect()
            : Articulo::query()
                ->with(['unidadesdemedidas', 'unidadesdemedidasalternativas'])
                ->whereIn('id', $idsValidos)
                ->get()
                ->keyBy('id');

        $lineas = collect();
        foreach ($articulosIds as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = (float) ($cantidades[$i] ?? 0);
            if ($articuloId <= 0 && abs($cantidad) <= 0) {
                continue;
            }

            $art = $articuloModels->get($articuloId);

            $lineas->push((object) [
                'id' => old('ids.'.$i, ''),
                'articulo_id' => $articuloId,
                'sku' => old('sku.'.$i, $art?->sku ?? ''),
                'articulos' => $art,
                'cantidad' => $cantidad,
                'pieza' => (float) ($piezas[$i] ?? 0),
                'precio' => (float) ($precios[$i] ?? 0),
                'combinacion_id' => old('combinaciones_id.'.$i, ''),
                'modulo_id' => old('modulos_id.'.$i, ''),
                'combinaciones' => null,
                'listaprecio_id' => old('listasprecios_id.'.$i, ''),
                'moneda_id' => old('monedas_id.'.$i, ''),
                'incluyeimpuesto' => old('incluyeimpuestos.'.$i, ''),
                'descuento' => old('descuentos.'.$i, ''),
                'lote' => old('loteids.'.$i, 0),
                'estado' => old('estados.'.$i, ''),
                'articulo_movimiento_talles' => old('medidas.'.$i, ''),
            ]);
        }

        return $lineas;
    }
}
