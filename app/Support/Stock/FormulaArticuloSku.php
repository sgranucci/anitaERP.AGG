<?php

namespace App\Support\Stock;

use App\Models\Stock\Formula_Articulo;

/**
 * Convierte código de fórmula Anita / ERP en SKU de venta (ej. 365 → V0365).
 */
final class FormulaArticuloSku
{
    public static function prefijo(): string
    {
        return (string) config('formula_articulo.sku_prefijo', 'V');
    }

    public static function digitosSufijo(): int
    {
        return max(1, (int) config('formula_articulo.sku_digitos', 4));
    }

    public static function skuDesdeCodigo(int $codigoNumerico): string
    {
        if ($codigoNumerico < 0) {
            $codigoNumerico = 0;
        }

        return self::prefijo().str_pad((string) $codigoNumerico, self::digitosSufijo(), '0', STR_PAD_LEFT);
    }

    public static function codigoDesdeSku(string $sku): ?int
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $pref = preg_quote(self::prefijo(), '/');
        $dig = self::digitosSufijo();

        if (preg_match('/^'.$pref.'(\d{'.$dig.'})$/i', $sku, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^'.$pref.'(\d+)$/i', $sku, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function codigoNumericoDesdeFormula(Formula_Articulo $formula): ?int
    {
        $anita = (int) ($formula->anita_stkcm_formula ?? 0);
        if ($anita > 0) {
            return $anita;
        }

        $codigo = trim((string) ($formula->codigo ?? ''));
        if ($codigo !== '' && ctype_digit($codigo)) {
            return (int) $codigo;
        }

        return null;
    }
}
