<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Ventas\CuentaGastronomia;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Valida que los artículos de una cuenta gastronomía tengan impuesto configurado antes de
 * calcular totales o agregar consumos.
 */
final class GastronomiaArticuloImpuestoValidacion
{
    /**
     * @param  Collection<int, Articulo>|iterable<int, Articulo|null>  $articulos
     */
    public static function validarColeccion(iterable $articulos): void
    {
        $vistos = [];
        foreach ($articulos as $articulo) {
            if (! $articulo instanceof Articulo) {
                continue;
            }
            if (isset($vistos[$articulo->id])) {
                continue;
            }
            $vistos[$articulo->id] = true;
            self::validarArticulo($articulo);
        }
    }

    public static function validarArticulo(?Articulo $articulo): void
    {
        if (! $articulo) {
            throw new InvalidArgumentException('Artículo inexistente.');
        }

        $impuestoId = (int) ($articulo->impuesto_id ?? 0);
        if ($impuestoId <= 0) {
            throw new InvalidArgumentException(self::mensajeSinImpuesto($articulo));
        }

        if (! Impuesto::query()->whereKey($impuestoId)->exists()) {
            throw new InvalidArgumentException(
                'El artículo '.$articulo->sku.' — '.$articulo->descripcion
                .' tiene impuesto id '.$impuestoId.' que no existe en el sistema.'
                .' Revise el maestro de artículos.'
            );
        }
    }

    public static function validarCuentaConLineas(CuentaGastronomia $cuenta, ?Articulo $articuloAdicional = null): void
    {
        $cuenta->loadMissing('lineas.articulo');

        $articulos = $cuenta->lineas
            ->map(fn ($linea) => $linea->articulo)
            ->filter()
            ->values();

        if ($articuloAdicional instanceof Articulo) {
            $articulos->push($articuloAdicional);
        }

        self::validarColeccion($articulos);
    }

    public static function mensajeSinImpuesto(Articulo $articulo): string
    {
        return 'El artículo '.$articulo->sku.' — '.$articulo->descripcion
            .' no tiene impuesto configurado.'
            .' Asigne IVA u otro impuesto en el maestro de artículos antes de cargar consumos o facturar.';
    }
}
