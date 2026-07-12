<?php

namespace App\Support\Stock;

/**
 * Invierte líneas de transferencia para reverso (incluye conversión fórmulas: insumo ↔ compra).
 */
final class TransferenciaMercaderiaLineaReversoSupport
{
    /**
     * @param  list<array<string, mixed>|\App\Models\Stock\Transferencia_Mercaderia_Articulo>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function invertirLineas(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            $isModel = is_object($linea);
            $out[] = [
                'item' => (int) ($isModel ? $linea->item : ($linea['item'] ?? 0)),
                'articulo_origen_id' => (int) ($isModel ? $linea->articulo_destino_id : ($linea['articulo_destino_id'] ?? 0)),
                'articulo_destino_id' => (int) ($isModel ? $linea->articulo_origen_id : ($linea['articulo_origen_id'] ?? 0)),
                'cantidad_origen' => (float) ($isModel ? $linea->cantidad_destino : ($linea['cantidad_destino'] ?? 0)),
                'cantidad_destino' => (float) ($isModel ? $linea->cantidad_origen : ($linea['cantidad_origen'] ?? 0)),
                'precio_costo_origen' => (float) ($isModel ? $linea->precio_costo_destino : ($linea['precio_costo_destino'] ?? 0)),
                'precio_costo_destino' => (float) ($isModel ? $linea->precio_costo_origen : ($linea['precio_costo_origen'] ?? 0)),
                'coeficienteconversion' => (float) ($isModel ? $linea->coeficienteconversion : ($linea['coeficienteconversion'] ?? 1)),
                'fl_conversion_formula' => (bool) ($isModel ? $linea->fl_conversion_formula : ($linea['fl_conversion_formula'] ?? false)),
            ];
        }

        return $out;
    }
}
