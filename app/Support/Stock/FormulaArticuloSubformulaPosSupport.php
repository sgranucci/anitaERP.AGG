<?php

namespace App\Support\Stock;

use App\Models\Stock\Formula_Articulo;

/**
 * SKU y descripción de subfórmulas en el POS de gastronomía (modal de opcionales y línea de cuenta).
 * Prioriza artículo vinculado; si no hay, usa código/número y detalle de la fórmula (como el ABM de fórmulas).
 */
final class FormulaArticuloSubformulaPosSupport
{
    /**
     * @return array{sku: string, descripcion: string}
     */
    public static function etiquetaOpcional(?Formula_Articulo $sub, ?int $formulaHijaId = null): array
    {
        $formulaHijaId = (int) ($formulaHijaId ?? $sub?->id ?? 0);

        if ($sub === null) {
            return [
                'sku' => $formulaHijaId > 0 ? 'F#'.$formulaHijaId : '',
                'descripcion' => 'Subfórmula',
            ];
        }

        $art = (int) ($sub->articulo_id ?? 0) > 0 ? $sub->articulos : null;
        $skuArt = trim((string) ($art->sku ?? ''));
        $descArt = trim((string) ($art->descripcion ?? ''));
        $detalle = trim((string) ($sub->detalle ?? ''));

        $sku = $skuArt !== ''
            ? $skuArt
            : (FormulaArticuloNumero::paraFormula($sub) ?: ($formulaHijaId > 0 ? 'F#'.$formulaHijaId : ''));

        $descripcion = $descArt !== ''
            ? $descArt
            : ($detalle !== '' ? $detalle : 'Subfórmula');

        return [
            'sku' => $sku,
            'descripcion' => $descripcion,
        ];
    }
}
