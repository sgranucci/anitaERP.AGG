<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Stock\Articulo;
use App\Support\Stock\FormulaArticuloSubformulaPosSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Preparar / No preparar para KDS Crown (misma idea que AGG waitry_preparar).
 *
 * Prioridad de lectura en maestro artículo:
 * 1) kds_preparar (Crown / unificado)
 * 2) waitry_preparar (AGG legacy)
 * 3) true
 */
final class GastronomiaCrownKdsPreparaSupport
{
    public static function preparaDesdeArticulo(?Articulo $articulo): bool
    {
        if ($articulo === null) {
            return true;
        }

        if (Schema::hasColumn('articulo', 'kds_preparar')) {
            return (bool) ($articulo->kds_preparar ?? true);
        }

        if (Schema::hasColumn('articulo', 'waitry_preparar')) {
            return (bool) ($articulo->waitry_preparar ?? true);
        }

        return true;
    }

    /**
     * Payload futuro para pantalla KDS: foto del plato + insumos con foto.
     * (Stub de contrato; el módulo KDS lo consumirá.)
     *
     * @return array{
     *   articulo_id: int,
     *   sku: string,
     *   descripcion: string,
     *   prepara: bool,
     *   foto_url: ?string,
     *   insumos: list<array{articulo_id:?int,sku:?string,descripcion:string,cantidad:float,foto_url:?string}>
     * }
     */
    public static function payloadVisualKds(Articulo $articulo, float $cantidadPlato = 1.0): array
    {
        $articulo->loadMissing([
            'formula_articulo.formula_articulo_hijos.articulos',
            'formula_articulo.formula_articulo_hijos.formula_hija.articulos',
        ]);

        $insumos = [];
        $formula = $articulo->formula_articulo;
        if ($formula) {
            foreach ($formula->formula_articulo_hijos as $hijo) {
                // Los opcionales elegidos se muestran aparte (selección de la línea), no como insumo fijo.
                if ($hijo->esopcional) {
                    continue;
                }

                $ins = $hijo->articulos;
                $cant = (float) ($hijo->cantidad ?? 0) * $cantidadPlato;

                if (! $ins && $hijo->formula_hija_id) {
                    $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional(
                        $hijo->formula_hija,
                        (int) $hijo->formula_hija_id,
                    );
                    $insumos[] = [
                        'articulo_id' => null,
                        'sku' => $etiqueta['sku'],
                        'descripcion' => $etiqueta['descripcion'],
                        'cantidad' => $cant,
                        'foto_url' => self::urlFotoArticulo($hijo->formula_hija?->articulos),
                    ];

                    continue;
                }

                $insumos[] = [
                    'articulo_id' => $ins?->id,
                    'sku' => $ins?->sku,
                    'descripcion' => $ins?->descripcion ?? 'Insumo',
                    'cantidad' => $cant,
                    'foto_url' => self::urlFotoArticulo($ins),
                ];
            }
        }

        return [
            'articulo_id' => (int) $articulo->id,
            'sku' => (string) $articulo->sku,
            'descripcion' => (string) $articulo->descripcion,
            'prepara' => self::preparaDesdeArticulo($articulo),
            'foto_url' => self::urlFotoArticulo($articulo),
            'insumos' => $insumos,
        ];
    }

    public static function urlFotoArticulo(?Articulo $articulo): ?string
    {
        if (! $articulo || empty($articulo->foto)) {
            return null;
        }
        $nombre = (string) $articulo->foto;
        if (! str_contains($nombre, '.')) {
            $nombre .= '.jpg';
        }

        return asset('storage/imagenes/fotos_articulos/'.$nombre);
    }
}
