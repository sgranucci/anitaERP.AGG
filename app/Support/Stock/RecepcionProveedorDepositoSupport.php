<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Traits\Stock\DepmaeTrait;
use Illuminate\Support\Collection;

class RecepcionProveedorDepositoSupport
{
    public const TIPO_DEPOSITO_FORMULAS = 'Formulas';

    /** @var Collection<int, Articulo|null> */
    private static Collection $cacheInsumoPorArticuloCompra;

    public static function reiniciarCache(): void
    {
        self::$cacheInsumoPorArticuloCompra = collect();
    }

    /**
     * Candidatos SKU del insumo a partir de skualternativo (ej. 193 → I0193).
     *
     * @return list<string>
     */
    public static function candidatosSkuInsumo(string $skuAlternativo): array
    {
        $alt = trim($skuAlternativo);
        if ($alt === '' || $alt === '0') {
            return [];
        }

        $candidatos = [$alt];
        $numerico = preg_replace('/\D+/', '', $alt) ?? '';
        if ($numerico !== '') {
            $candidatos[] = $numerico;
            $candidatos[] = 'I'.$numerico;
            $candidatos[] = 'I'.str_pad($numerico, 4, '0', STR_PAD_LEFT);
            $candidatos[] = 'I'.str_pad($numerico, 5, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($candidatos, static fn (string $sku): bool => $sku !== '')));
    }

    /**
     * Resuelve el artículo insumo/granel vinculado por skualternativo del artículo de compra.
     */
    public static function resolverArticuloInsumo(Articulo $articuloCompra, ?int $empresaId = null): ?Articulo
    {
        if (! isset(self::$cacheInsumoPorArticuloCompra)) {
            self::reiniciarCache();
        }

        $cacheKey = (int) $articuloCompra->id;
        if (self::$cacheInsumoPorArticuloCompra->has($cacheKey)) {
            return self::$cacheInsumoPorArticuloCompra->get($cacheKey);
        }

        $candidatos = self::candidatosSkuInsumo((string) ($articuloCompra->skualternativo ?? ''));
        if ($candidatos === []) {
            self::$cacheInsumoPorArticuloCompra->put($cacheKey, null);

            return null;
        }

        $query = Articulo::query()->whereIn('sku', $candidatos);
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $filas = $query->get(['id', 'sku', 'empresa_id']);
        $insumo = null;
        foreach ($candidatos as $sku) {
            $insumo = $filas->firstWhere('sku', $sku);
            if ($insumo !== null) {
                break;
            }
        }

        self::$cacheInsumoPorArticuloCompra->put($cacheKey, $insumo);

        return $insumo;
    }

    public static function depositoPermitidoUsuario(int $depositoId, int $empresaId): bool
    {
        return $depositoId > 0 && Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId);
    }

    /** ID del depósito si el usuario puede operarlo; null si no está autorizado o no hay restricción aplicable. */
    public static function depositoEntregaVisible(?int $depositoId, int $empresaId): ?int
    {
        if ($depositoId === null || $depositoId <= 0) {
            return null;
        }

        return self::depositoPermitidoUsuario($depositoId, $empresaId) ? $depositoId : null;
    }

    public static function esDepositoFormula(?Depmae $deposito): bool
    {
        if ($deposito === null) {
            return false;
        }

        $tipo = trim((string) ($deposito->tipodeposito ?? ''));

        if ($tipo === self::TIPO_DEPOSITO_FORMULAS) {
            return true;
        }

        foreach (DepmaeTrait::$enumTipoDeposito as $fila) {
            if (($fila['nombre'] ?? '') === self::TIPO_DEPOSITO_FORMULAS
                && in_array($tipo, [(string) ($fila['valor'] ?? ''), (string) ($fila['id'] ?? '')], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Depósito destino de la línea: cabecera (si existe) o depósito de entrega del artículo.
     */
    public static function resolverDepositoLinea(?int $depositoCabeceraId, Articulo $articulo): int
    {
        if ($depositoCabeceraId !== null && $depositoCabeceraId > 0) {
            return $depositoCabeceraId;
        }

        $depositoArticulo = (int) ($articulo->depositoentrega_id ?? 0);
        if ($depositoArticulo <= 0) {
            throw new \RuntimeException(
                'Artículo '.($articulo->sku ?? $articulo->id).': sin depósito de entrega configurado. '
                .'Indique un depósito general en la recepción o configure depositoentrega_id en el artículo.'
            );
        }

        return $depositoArticulo;
    }

    /**
     * Convierte cantidad/precio de UM compra a UM stock según depósito destino.
     *
     * @return array{
     *     cantidad_stock: float,
     *     precio_stock: float,
     *     coeficienteconversion: float,
     *     fl_conversion_formula: bool,
     *     usa_deposito_articulo: bool,
     *     articulo_stock_id: int|null,
     *     articulo_stock_sku: string|null
     * }
     */
    public static function calcularConversionStock(
        Articulo $articulo,
        Depmae $deposito,
        float $cantidadCompra,
        float $precioCompra,
        float $coefProveedor,
        bool $usaDepositoArticulo,
        ?int $empresaId = null
    ): array {
        $coefProv = $coefProveedor > 0 ? $coefProveedor : 1.0;
        $coefArt = (float) ($articulo->coeficienteconversion ?? 0);
        $coefArt = $coefArt > 0 ? $coefArt : 1.0;
        $esFormula = self::esDepositoFormula($deposito);

        if ($esFormula) {
            $insumo = self::resolverArticuloInsumo($articulo, $empresaId);
            if ($insumo === null) {
                throw new \RuntimeException(
                    'Artículo '.($articulo->sku ?? $articulo->id)
                    .': depósito fórmulas requiere insumo vía SKU alternativo ('
                    .trim((string) ($articulo->skualternativo ?? '')).'), no encontrado en el maestro.'
                );
            }

            return [
                'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadCompra, $coefArt),
                'precio_stock' => round($precioCompra / $coefArt, 6),
                'coeficienteconversion' => $coefArt,
                'fl_conversion_formula' => true,
                'usa_deposito_articulo' => true,
                'articulo_stock_id' => (int) $insumo->id,
                'articulo_stock_sku' => (string) $insumo->sku,
            ];
        }

        $precioStock = $coefProv !== 1.0 ? round($precioCompra / $coefProv, 6) : round($precioCompra, 6);

        return [
            'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadCompra, $coefProv),
            'precio_stock' => $precioStock,
            'coeficienteconversion' => $coefProv,
            'fl_conversion_formula' => false,
            'usa_deposito_articulo' => $usaDepositoArticulo,
            'articulo_stock_id' => null,
            'articulo_stock_sku' => null,
        ];
    }

    public static function coeficienteProveedor(int $articuloId, int $proveedorId): float
    {
        return RecepcionProveedorConversionSupport::resolverCoeficiente($articuloId, $proveedorId);
    }
}
