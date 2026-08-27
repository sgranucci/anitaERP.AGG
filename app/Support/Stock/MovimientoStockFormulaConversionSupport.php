<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;

/**
 * Vista previa de conversión compra ↔ insumo en depósitos tipo Fórmulas (formulario mov. stock).
 */
final class MovimientoStockFormulaConversionSupport
{
    public const SENTIDO_ENTRADA = 'entrada';

    public const SENTIDO_SALIDA = 'salida';

    /**
     * @return array<string, mixed>
     */
    public static function preview(
        int $articuloLineaId,
        int $depositoFormulaId,
        string $sentido,
        float $cantidadLinea,
        ?int $empresaId = null,
        ?int $articuloCompraElegidoId = null
    ): array {
        $cantidadLinea = max(0.0, $cantidadLinea);
        if ($articuloLineaId <= 0 || $depositoFormulaId <= 0 || $cantidadLinea <= 0) {
            return ['ok' => true, 'activo' => false];
        }

        $deposito = Depmae::query()->find($depositoFormulaId);
        if ($deposito === null || ! RecepcionProveedorDepositoSupport::esDepositoFormula($deposito)) {
            return ['ok' => true, 'activo' => false];
        }

        $articuloLinea = Articulo::query()
            ->with(['unidadesdemedidas', 'unidadesdemedidasalternativas'])
            ->find($articuloLineaId);
        if ($articuloLinea === null) {
            return ['ok' => false, 'activo' => false, 'mensaje' => 'Artículo no encontrado.'];
        }

        if ($sentido === self::SENTIDO_ENTRADA) {
            return self::previewEntrada($articuloLinea, $deposito, $cantidadLinea, $empresaId);
        }

        if ($sentido === self::SENTIDO_SALIDA) {
            return self::previewSalida($articuloLinea, $cantidadLinea, $empresaId, $articuloCompraElegidoId);
        }

        return ['ok' => false, 'mensaje' => 'Sentido de conversión inválido.'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function previewEntrada(
        Articulo $articuloCompra,
        Depmae $deposito,
        float $cantidadLinea,
        ?int $empresaId
    ): array {
        try {
            $conv = RecepcionProveedorDepositoSupport::calcularConversionStock(
                $articuloCompra,
                $deposito,
                $cantidadLinea,
                (float) ($articuloCompra->costo ?? $articuloCompra->precio ?? 0),
                1.0,
                true,
                $empresaId
            );
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'activo' => true,
                'sentido' => self::SENTIDO_ENTRADA,
                'mensaje' => $e->getMessage(),
            ];
        }

        if (! ($conv['fl_conversion_formula'] ?? false)) {
            return ['ok' => true, 'activo' => false];
        }

        $insumo = Articulo::query()
            ->with(['unidadesdemedidas', 'unidadesdemedidasalternativas'])
            ->find((int) ($conv['articulo_stock_id'] ?? 0));

        $umLinea = (string) (optional($articuloCompra->unidadesdemedidas)->abreviatura ?? '');
        $umDestino = (string) (optional($insumo?->unidadesdemedidas)->abreviatura ?? $umLinea);

        return [
            'ok' => true,
            'activo' => true,
            'fl_conversion_formula' => true,
            'sentido' => self::SENTIDO_ENTRADA,
            'articulo_linea_sku' => (string) $articuloCompra->sku,
            'articulo_convertido_sku' => (string) ($conv['articulo_stock_sku'] ?? ''),
            'articulo_convertido_id' => (int) ($conv['articulo_stock_id'] ?? 0),
            'articulo_convertido_descripcion' => (string) ($insumo?->descripcion ?? $insumo?->nombre ?? ''),
            'cantidad_linea' => $cantidadLinea,
            'cantidad_convertida' => (float) ($conv['cantidad_stock'] ?? 0),
            'coeficienteconversion' => (float) ($conv['coeficienteconversion'] ?? 1),
            'um_linea' => $umLinea,
            'um_convertida' => $umDestino,
            'texto' => 'Destino: '.($conv['articulo_stock_sku'] ?? '')
                .' · '.self::formatearCantidad((float) ($conv['cantidad_stock'] ?? 0))
                .($umDestino !== '' ? ' '.$umDestino : ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function previewSalida(
        Articulo $articuloInsumo,
        float $cantidadLinea,
        ?int $empresaId,
        ?int $articuloCompraElegidoId
    ): array {
        $compras = RecepcionProveedorDepositoSupport::resolverArticulosCompraDesdeInsumo($articuloInsumo, $empresaId);
        if ($compras->isEmpty()) {
            return [
                'ok' => false,
                'activo' => true,
                'sentido' => self::SENTIDO_SALIDA,
                'mensaje' => 'Artículo '.($articuloInsumo->sku ?? $articuloInsumo->id)
                    .': no hay artículo de compra vinculado por SKU alternativo.',
            ];
        }

        if ($compras->count() > 1 && (int) ($articuloCompraElegidoId ?? 0) <= 0) {
            return [
                'ok' => false,
                'activo' => true,
                'sentido' => self::SENTIDO_SALIDA,
                'requiere_elegir_compra' => true,
                'opciones_compra' => $compras->map(static function (Articulo $compra): array {
                    return [
                        'id' => (int) $compra->id,
                        'sku' => (string) $compra->sku,
                        'descripcion' => (string) ($compra->descripcion ?? $compra->nombre ?? ''),
                    ];
                })->values()->all(),
            ];
        }

        $compra = (int) ($articuloCompraElegidoId ?? 0) > 0
            ? $compras->firstWhere('id', (int) $articuloCompraElegidoId)
            : $compras->first();

        if ($compra === null) {
            $compra = $compras->first();
        }

        $coef = (float) ($compra->coeficienteconversion ?? 0);
        $coef = $coef > 0 ? $coef : 1.0;
        $cantCompra = $coef > 0 ? round($cantidadLinea / $coef, 6) : $cantidadLinea;

        $umInsumo = (string) (optional($articuloInsumo->unidadesdemedidas)->abreviatura ?? '');
        $umCompra = (string) (optional($compra->unidadesdemedidas)->abreviatura ?? $umInsumo);

        return [
            'ok' => true,
            'activo' => true,
            'fl_conversion_formula' => true,
            'sentido' => self::SENTIDO_SALIDA,
            'articulo_linea_sku' => (string) $articuloInsumo->sku,
            'articulo_convertido_sku' => (string) $compra->sku,
            'articulo_convertido_id' => (int) $compra->id,
            'articulo_compra_id' => (int) $compra->id,
            'articulo_convertido_descripcion' => (string) ($compra->descripcion ?? $compra->nombre ?? ''),
            'cantidad_linea' => $cantidadLinea,
            'cantidad_convertida' => $cantCompra,
            'coeficienteconversion' => $coef,
            'um_linea' => $umInsumo,
            'um_convertida' => $umCompra,
            'texto' => 'Equiv. compra: '.($compra->sku ?? '')
                .' · '.self::formatearCantidad($cantCompra)
                .($umCompra !== '' ? ' '.$umCompra : ''),
        ];
    }

    private static function formatearCantidad(float $cantidad): string
    {
        $texto = rtrim(rtrim(number_format($cantidad, 6, '.', ''), '0'), '.');

        return $texto !== '' ? $texto : '0';
    }
}
