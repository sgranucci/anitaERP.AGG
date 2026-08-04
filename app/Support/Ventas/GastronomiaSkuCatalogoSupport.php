<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;

/**
 * SKU catálogo gastronomía: prefijo (ej. V) + N dígitos de sufijo (ej. 5 → V00123).
 */
final class GastronomiaSkuCatalogoSupport
{
    public static function prefijo(): string
    {
        return mb_strtoupper(trim((string) config('gastronomia.sku_catalogo_prefijo', 'V')), 'UTF-8');
    }

    public static function digitosSufijo(): int
    {
        return max(0, (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0));
    }

    public static function longitudSkuCompleto(?string $prefijo = null, ?int $digitosSufijo = null): int
    {
        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();

        return strlen($prefijo) + $digitos;
    }

    /**
     * Arma SKU completo desde dígitos ingresados en el POS (sufijo con ceros a la izquierda).
     */
    public static function skuDesdeSufijoDigitos(string $sufijoRaw, ?string $prefijo = null, ?int $digitosSufijo = null): string
    {
        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();
        $digits = preg_replace('/\D/', '', $sufijoRaw) ?? '';

        if ($digits === '' || $digitos <= 0) {
            return '';
        }

        $padded = str_pad($digits, $digitos, '0', STR_PAD_LEFT);

        return $prefijo.substr($padded, -$digitos);
    }

    public static function skuPermitido(string $sku, ?string $prefijo = null, ?int $digitosSufijo = null): bool
    {
        $sku = mb_strtoupper(trim($sku), 'UTF-8');
        if ($sku === '') {
            return false;
        }

        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();

        if ($digitos > 0) {
            return (bool) preg_match('/'.self::patronRegexCatalogo($prefijo, $digitos).'/', $sku);
        }

        return str_starts_with($sku, $prefijo);
    }

    /** Patrón ancla para REGEXP SQL / validación PHP (ej. ^V[0-9]{4}$). */
    public static function patronRegexCatalogo(?string $prefijo = null, ?int $digitosSufijo = null): string
    {
        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();

        if ($digitos <= 0) {
            return '^'.preg_quote($prefijo, '/').'.*$';
        }

        return '^'.preg_quote($prefijo, '/').'[0-9]{'.$digitos.'}$';
    }

    /**
     * Restringe el query al catálogo gastronomía: solo SKU prefijo + N dígitos (ej. V0123), no VIRWS…
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarScopeFormatoCatalogo(
        Builder $query,
        ?string $prefijo = null,
        ?int $digitosSufijo = null,
        string $columnaSku = 'sku',
    ): void {
        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();

        if ($digitos <= 0) {
            $query->whereRaw('UPPER('.$columnaSku.') LIKE ?', [$prefijo.'%']);

            return;
        }

        $query->whereRaw('UPPER('.$columnaSku.') REGEXP ?', [self::patronRegexCatalogo($prefijo, $digitos)]);
    }

    /**
     * Filtro de catálogo / consulta modal: término numérico = sufijo con padding; texto = descripción o SKU parcial.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFiltroTerminoCatalogo(
        Builder $query,
        string $termino,
        ?string $prefijo = null,
        ?int $digitosSufijo = null,
        string $columnaSku = 'sku',
    ): void {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $prefijo = $prefijo ?? self::prefijo();
        $digitos = $digitosSufijo ?? self::digitosSufijo();
        $longitudSku = strlen($prefijo) + $digitos;
        $col = $columnaSku;

        if ($digitos > 0 && preg_match('/^\d+$/', $termino)) {
            $skuExacto = self::skuDesdeSufijoDigitos($termino, $prefijo, $digitos);

            $query->where(function ($qq) use ($termino, $skuExacto, $prefijo, $longitudSku, $col) {
                if ($skuExacto !== '') {
                    $qq->whereRaw('UPPER('.$col.') = ?', [$skuExacto]);
                }
                $qq->orWhere(function ($q2) use ($prefijo, $termino, $longitudSku, $col) {
                    $q2->whereRaw('UPPER('.$col.') LIKE ?', [$prefijo.'%'])
                        ->whereRaw('CHAR_LENGTH('.$col.') = ?', [$longitudSku])
                        ->whereRaw('SUBSTRING(UPPER('.$col.'), ?) LIKE ?', [strlen($prefijo) + 1, '%'.$termino.'%']);
                });
            });

            return;
        }

        $like = '%'.$termino.'%';
        if ($digitos > 0) {
            $query->where(function ($qq) use ($like, $col, $termino, $prefijo, $digitos) {
                $qq->where('descripcion', 'like', $like);
                if (preg_match('/^\d+$/', $termino)) {
                    $skuExacto = self::skuDesdeSufijoDigitos($termino, $prefijo, $digitos);
                    if ($skuExacto !== '') {
                        $qq->orWhereRaw('UPPER('.$col.') = ?', [$skuExacto]);
                    }
                }
            });

            return;
        }

        $query->where(function ($qq) use ($like, $col) {
            $qq->where($col, 'like', $like)
                ->orWhere('descripcion', 'like', $like);
        });
    }

    /**
     * Longitud mínima de búsqueda en consulta modal (1 dígito si hay sufijo numérico configurado).
     */
    public static function longitudMinimaBusqueda(string $termino, ?int $digitosSufijo = null): int
    {
        $digitos = $digitosSufijo ?? self::digitosSufijo();
        $termino = trim($termino);

        if ($digitos > 0 && $termino !== '' && preg_match('/^\d+$/', $termino)) {
            return 1;
        }

        return 2;
    }
}
