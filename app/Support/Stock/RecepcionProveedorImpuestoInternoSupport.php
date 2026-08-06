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
        // Recepción y devolución: ambas deben llevar II si hay cigarrillos,
        // para que el asiento COM (y recepmov IMPINTERNO) revierta la pata contable.
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

    /**
     * Prorratea el impuesto interno de la recepción origen según cigarrillos devueltos.
     *
     * @param  list<array<string, mixed>>  $itemsDevolucion
     */
    public static function calcularImpuestoInternoProporcionalDesdeOrigen(
        Recepcion_Proveedor $origen,
        array $itemsDevolucion,
    ): ?float {
        $iiOrigen = (float) ($origen->impuesto_interno ?? 0);
        if ($iiOrigen <= 0.000001) {
            return null;
        }

        $qtyOrigen = self::totalCantidadCigarrillos($origen);
        if ($qtyOrigen <= 0.000001) {
            return null;
        }

        $qtyDev = self::totalCantidadCigarrillosDesdeItems($itemsDevolucion);
        if ($qtyDev <= 0.000001) {
            return null;
        }

        return round($iiOrigen * ($qtyDev / $qtyOrigen), 2);
    }

    public static function calcularImpuestoInternoProporcionalEntreRecepciones(
        Recepcion_Proveedor $origen,
        Recepcion_Proveedor $devolucion,
    ): ?float {
        $iiOrigen = (float) ($origen->impuesto_interno ?? 0);
        if ($iiOrigen <= 0.000001) {
            return null;
        }

        $qtyOrigen = self::totalCantidadCigarrillos($origen);
        if ($qtyOrigen <= 0.000001) {
            return null;
        }

        $qtyDev = self::totalCantidadCigarrillos($devolucion);
        if ($qtyDev <= 0.000001) {
            return null;
        }

        return round($iiOrigen * ($qtyDev / $qtyOrigen), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function totalCantidadCigarrillosDesdeItems(array $items): float
    {
        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();
        if ($tipoCigarrilloId === null || $items === []) {
            return 0.0;
        }

        $articuloIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['articulo_id'] ?? 0),
            $items
        ))));

        if ($articuloIds === []) {
            return 0.0;
        }

        $tipoPorArticulo = Articulo::query()
            ->whereIn('id', $articuloIds)
            ->pluck('tipoarticulo_id', 'id');

        $total = 0.0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
                continue;
            }
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0.000001) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            if ((int) ($tipoPorArticulo[$articuloId] ?? 0) !== $tipoCigarrilloId) {
                continue;
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

        $importe = $recepcion->impuesto_interno;
        if ($importe === null || (float) $importe <= 0.000001) {
            $msg = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION
                ? 'La devolución con cigarrillos debe tener impuesto interno (se prorratea desde la recepción origen) antes de confirmar.'
                : 'Indique el impuesto interno de la factura (líneas con cigarrillos) y guarde la recepción antes de confirmar.';

            throw new \RuntimeException($msg);
        }
    }

    /**
     * Auditoría: devolución cuya recepción origen tiene II debe cargar el II proporcional.
     * Si no, el asiento COM omite la pata de impuesto interno (falso OK vs preview).
     *
     * @return array{
     *     ii_actual: float,
     *     ii_esperado: float,
     *     ii_origen: float,
     *     origen_id: int,
     *     origen_nro: int,
     *     mensaje: string
     * }|null
     */
    public static function diagnosticoImpuestoInternoDevolucion(
        Recepcion_Proveedor $devolucion,
        float $tolerancia = 0.02,
    ): ?array {
        if ($devolucion->tipo !== Recepcion_Proveedor::TIPO_DEVOLUCION) {
            return null;
        }

        $devolucion->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'recepcion_referencia.recepcion_proveedor_articulos.articulos',
        ]);

        $origen = $devolucion->recepcion_referencia;
        if (! $origen instanceof Recepcion_Proveedor) {
            return null;
        }

        $iiEsperado = self::calcularImpuestoInternoProporcionalEntreRecepciones($origen, $devolucion);
        if ($iiEsperado === null || $iiEsperado <= 0.000001) {
            return null;
        }

        $iiActual = round((float) ($devolucion->impuesto_interno ?? 0), 2);
        if (abs($iiActual - $iiEsperado) < max(0.0, $tolerancia)) {
            return null;
        }

        $origenNro = (int) ($origen->numerorecepcion ?? 0);
        $iiOrigen = round((float) ($origen->impuesto_interno ?? 0), 2);

        return [
            'ii_actual' => $iiActual,
            'ii_esperado' => $iiEsperado,
            'ii_origen' => $iiOrigen,
            'origen_id' => (int) $origen->id,
            'origen_nro' => $origenNro,
            'mensaje' => sprintf(
                'Devolución sin impuesto interno contable: origen COM %d tiene II %s; esperado en devolución %s (actual %s).',
                $origenNro,
                number_format($iiOrigen, 2, ',', '.'),
                number_format($iiEsperado, 2, ',', '.'),
                number_format($iiActual, 2, ',', '.'),
            ),
        ];
    }
}
