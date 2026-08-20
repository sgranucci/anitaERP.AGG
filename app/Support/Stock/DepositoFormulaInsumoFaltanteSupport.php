<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Mensajes de falta de SKU alternativo al operar contra depósito Fórmulas.
 */
final class DepositoFormulaInsumoFaltanteSupport
{
    public static function mensajeArticulo(Articulo $articulo): string
    {
        $sku = trim((string) ($articulo->sku ?? ''));
        $desc = trim((string) ($articulo->descripcion ?? ''));
        $alt = trim((string) ($articulo->skualternativo ?? ''));
        $etiqueta = $sku !== '' ? $sku : (string) $articulo->id;
        if ($desc !== '') {
            $etiqueta .= ' — '.$desc;
        }

        if ($alt === '' || $alt === '0') {
            return $etiqueta.': falta SKU alternativo (insumo). Sin ese dato no se puede operar contra depósito Fórmulas.';
        }

        return $etiqueta.': el SKU alternativo «'.$alt.'» no existe como insumo en el maestro. Corrija el vínculo antes de operar contra depósito Fórmulas.';
    }

    /**
     * @param  list<string>  $lineas
     */
    public static function mensajeListado(array $lineas): string
    {
        $lineas = array_values(array_filter(array_map('trim', $lineas), static fn (string $l): bool => $l !== ''));
        if ($lineas === []) {
            return 'Hay artículos sin SKU alternativo (insumo) para el depósito Fórmulas.';
        }

        $cabecera = count($lineas) === 1
            ? 'No se puede grabar: falta SKU alternativo (insumo) para el depósito Fórmulas.'
            : 'No se puede grabar: '.count($lineas).' artículos sin SKU alternativo (insumo) para el depósito Fórmulas.';

        return $cabecera."\n- ".implode("\n- ", $lineas);
    }
}
