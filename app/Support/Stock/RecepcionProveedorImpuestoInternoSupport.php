<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Models\Stock\Tipoarticulo;

/**
 * Impuesto interno en recepciones con artículos tipo CIGARRILLO (réplica carga_final / graba_stkmae en a-stock.c).
 */
final class RecepcionProveedorImpuestoInternoSupport
{
    private static ?int $tipoArticuloCigarrilloIdCache = null;

    private static bool $tipoArticuloCigarrilloIdResolved = false;

    public static function skuArticuloImpuestoInterno(): string
    {
        return strtoupper(trim((string) config('recepcion_proveedor.sku_articulo_impuesto_interno', 'IMPINTERNO')));
    }

    public static function tipoArticuloCigarrilloId(): ?int
    {
        if (self::$tipoArticuloCigarrilloIdResolved) {
            return self::$tipoArticuloCigarrilloIdCache;
        }

        self::$tipoArticuloCigarrilloIdResolved = true;
        $nombre = mb_strtoupper(trim((string) config('facturacion.IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO')));
        if ($nombre === '') {
            return self::$tipoArticuloCigarrilloIdCache = null;
        }

        $id = Tipoarticulo::query()->whereRaw('UPPER(nombre) = ?', [$nombre])->value('id');

        return self::$tipoArticuloCigarrilloIdCache = $id !== null ? (int) $id : null;
    }

    public static function articuloEsCigarrillo(?Articulo $articulo): bool
    {
        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();
        if ($tipoCigarrilloId === null || ! $articulo instanceof Articulo) {
            return false;
        }

        return (int) ($articulo->tipoarticulo_id ?? 0) === $tipoCigarrilloId;
    }

    public static function lineaEsCigarrilloRecibida(Recepcion_Proveedor_Articulo $linea): bool
    {
        if ((float) ($linea->cantidad ?? 0) <= 0.000001) {
            return false;
        }

        $linea->loadMissing('articulos');

        return self::articuloEsCigarrillo($linea->articulos);
    }

    public static function recepcionRequiereImpuestoInterno(Recepcion_Proveedor $recepcion): bool
    {
        if ($recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return false;
        }

        return self::totalCantidadCigarrillos($recepcion) > 0.000001;
    }

    public static function totalCantidadCigarrillos(Recepcion_Proveedor $recepcion): float
    {
        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos']);

        $total = 0.0;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if (! self::lineaEsCigarrilloRecibida($linea)) {
                continue;
            }

            $cantidad = (float) ($linea->cantidad_stock ?? 0);
            if ($cantidad <= 0.000001) {
                $cantidad = (float) ($linea->cantidad ?? 0);
            }

            $total += $cantidad;
        }

        return round($total, 6);
    }

    public static function impuestoInternoPorUnidad(Recepcion_Proveedor $recepcion): float
    {
        $totalCigarrillos = self::totalCantidadCigarrillos($recepcion);
        if ($totalCigarrillos <= 0.000001) {
            return 0.0;
        }

        $impuestoInterno = (float) ($recepcion->impuesto_interno ?? 0);
        if ($impuestoInterno <= 0.000001) {
            return 0.0;
        }

        return round($impuestoInterno / $totalCigarrillos, 6);
    }

    public static function importeImpuestoInternoContable(Recepcion_Proveedor $recepcion, float $cotizacionRecepcion): float
    {
        $importe = (float) ($recepcion->impuesto_interno ?? 0);
        if ($importe <= 0.000001) {
            return 0.0;
        }

        $monedaRecepcionId = (int) ($recepcion->moneda_id ?: 1);

        return RecepcionProveedorConversionSupport::importeEnMonedaReferencia(
            $monedaRecepcionId,
            $monedaRecepcionId,
            $importe,
            (float) ($recepcion->cotizacion ?: 1),
        );
    }

    public static function resolverArticuloImpuestoInterno(): ?Articulo
    {
        $sku = self::skuArticuloImpuestoInterno();
        if ($sku === '') {
            return null;
        }

        return Articulo::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first();
    }

    public static function resolverCuentaCompraImpuestoInterno(int $empresaId): int
    {
        $articulo = self::resolverArticuloImpuestoInterno();
        if ($articulo === null) {
            throw new \RuntimeException(
                'No existe el artículo '.self::skuArticuloImpuestoInterno().' para contabilizar el impuesto interno.'
            );
        }

        $articulo->loadMissing('articulo_cuentacontables');
        $cuentaGrid = $articulo->articulo_cuentacontables
            ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                && strtoupper((string) $row->tipoimputacion) === 'COMPRAS');

        if ($cuentaGrid && (int) $cuentaGrid->cuentacontable_id > 0) {
            return (int) $cuentaGrid->cuentacontable_id;
        }

        $ctaId = (int) ($articulo->cuentacontablecompra_id ?? 0);
        if ($ctaId <= 0) {
            throw new \RuntimeException(
                'El artículo '.self::skuArticuloImpuestoInterno().' no tiene cuenta contable de compra configurada.'
            );
        }

        return $ctaId;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function itemsRequierenImpuestoInterno(array $items): bool
    {
        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();
        if ($tipoCigarrilloId === null || $items === []) {
            return false;
        }

        $articuloIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['articulo_id'] ?? 0),
            $items
        ))));

        if ($articuloIds === []) {
            return false;
        }

        $tipoPorArticulo = Articulo::query()
            ->whereIn('id', $articuloIds)
            ->pluck('tipoarticulo_id', 'id');

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                continue;
            }
            if ((float) ($item['cantidad'] ?? 0) <= 0.000001) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            if ((int) ($tipoPorArticulo[$articuloId] ?? 0) === $tipoCigarrilloId) {
                return true;
            }
        }

        return false;
    }

    public static function normalizarImpuestoInternoGuardado(?float $impuestoInterno, bool $requiere): ?float
    {
        if (! $requiere) {
            return null;
        }

        return round(max(0.0, (float) ($impuestoInterno ?? 0)), 2);
    }

    public static function assertImpuestoInternoCumplido(Recepcion_Proveedor $recepcion): void
    {
        if (! self::recepcionRequiereImpuestoInterno($recepcion)) {
            return;
        }

        if ($recepcion->impuesto_interno === null) {
            throw new \RuntimeException(
                'Indique el impuesto interno de la factura (líneas con cigarrillos) y guarde la recepción antes de confirmar.'
            );
        }
    }
}
