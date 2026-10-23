<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Proveedores y precios de compra del artículo, una fila de grilla.
 *
 * articulo_proveedor es N filas. El nombre y el precio se listan en el mismo
 * orden (por proveedor). El filtro de precio mira cada precio, no el texto.
 */
final class ArticuloProveedorRelacion
{
    /** @var list<string> */
    public const CLAVES = ['proveedor_art', 'precio_proveedor'];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'proveedor_art' => [
                'column' => 'art_prov.proveedor_nombre',
                'type' => 'texto',
                'label' => 'Proveedor',
            ],
            'precio_proveedor' => [
                'column' => 'art_prov.precio_min',
                'type' => 'decimal',
                'label' => 'Precio proveedor',
            ],
        ];
    }

    public static function esCampo(string $campoKey): bool
    {
        return in_array($campoKey, self::CLAVES, true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function debeAplicarColumnas(array $filtros): bool
    {
        $claves = self::CLAVES;
        foreach ((array) ($filtros['_columnas'] ?? []) as $key) {
            if (in_array((string) $key, $claves, true)) {
                return true;
            }
        }
        if (is_string($filtros['columnas'] ?? null)) {
            foreach (explode(',', (string) $filtros['columnas']) as $key) {
                if (in_array(trim($key), $claves, true)) {
                    return true;
                }
            }
        }
        foreach (['sort', 'agrupar'] as $bloque) {
            foreach ((array) ($filtros[$bloque] ?? []) as $fila) {
                $campo = is_array($fila) ? (string) ($fila['campo'] ?? '') : (string) $fila;
                if (in_array($campo, $claves, true)) {
                    return true;
                }
            }
        }
        $graficos = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        if ($graficos === [] && is_array($filtros['grafico'] ?? null)) {
            $graficos = [$filtros['grafico']];
        }
        foreach ($graficos as $grafico) {
            if (is_array($grafico) && in_array((string) ($grafico['dimension'] ?? ''), $claves, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicarColumnas($query): void
    {
        $query->leftJoinSub(self::subconsulta(), 'art_prov', 'art_prov.articulo_id', '=', 'articulo.id');
        $query->addSelect([
            'art_prov.proveedor_nombre as proveedor_art',
            'art_prov.proveedor_ids as proveedor_ids',
            'art_prov.precio_lista as precio_proveedor',
            'art_prov.precio_min as precio_min',
        ]);
    }

    public static function aplicarFiltro(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        if ($operador === 'vacio') {
            $query->whereNotExists(function (Builder $sub): void {
                $sub->selectRaw('1')
                    ->from('articulo_proveedor as ap')
                    ->whereColumn('ap.articulo_id', 'articulo.id');
            });

            return;
        }

        $query->whereExists(function (Builder $sub) use ($campoKey, $operador, $valor, $valorHasta): void {
            $sub->selectRaw('1')
                ->from('articulo_proveedor as ap')
                ->join('proveedor as prov_art', 'prov_art.id', '=', 'ap.proveedor_id')
                ->whereColumn('ap.articulo_id', 'articulo.id');

            if ($campoKey === 'precio_proveedor') {
                $sub->joinSub(self::precioVigente(), 'precio_vig', function ($join): void {
                    $join->on('precio_vig.articulo_id', '=', 'ap.articulo_id')
                        ->on('precio_vig.proveedor_id', '=', 'ap.proveedor_id');
                });
                ListadoQbeSupport::aplicarDecimal($sub, 'precio_vig.precio', $operador, $valor, $valorHasta);

                return;
            }

            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', 'prov_art.nombre', $operador, $valor, $valorHasta);
        });
    }

    private static function subconsulta(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('articulo_proveedor as ap')
            ->join('proveedor as prov_art', 'prov_art.id', '=', 'ap.proveedor_id')
            ->leftJoinSub(self::precioVigente(), 'precio_vig', function ($join): void {
                $join->on('precio_vig.articulo_id', '=', 'ap.articulo_id')
                    ->on('precio_vig.proveedor_id', '=', 'ap.proveedor_id');
            })
            ->select([
                'ap.articulo_id',
                'ap.proveedor_id',
                'prov_art.nombre as proveedor_nombre',
                'precio_vig.precio as precio',
            ])
            ->distinct();

        $nombre = SqlDialectSupport::castTexto('proveedor_nombre');
        $ids = SqlDialectSupport::castTexto('proveedor_id');
        $precio = 'COALESCE('.SqlDialectSupport::castTexto('precio').", '')";

        return DB::query()
            ->fromSub($base, 'ap_fila')
            ->groupBy('articulo_id')
            ->select('articulo_id')
            ->selectRaw(SqlDialectSupport::textosEnOrden($nombre, 'proveedor_id').' as proveedor_nombre')
            ->selectRaw(SqlDialectSupport::textosEnOrden($ids, 'proveedor_id').' as proveedor_ids')
            ->selectRaw(SqlDialectSupport::textosEnOrden($precio, 'proveedor_id').' as precio_lista')
            ->selectRaw('MIN(precio) as precio_min');
    }

    /**
     * Precio de la lista activa vigente, el mismo criterio que la compra:
     * última fecha de vigencia hasta hoy y, si hay varias listas, la más nueva.
     */
    private static function precioVigente(): \Illuminate\Database\Query\Builder
    {
        $hoy = date('Y-m-d');
        $maxFv = DB::table('listaprecio_proveedor_articulo')
            ->select('listaprecio_proveedor_id', 'articulo_id')
            ->selectRaw('MAX(fechavigencia) as max_fv')
            ->whereDate('fechavigencia', '<=', $hoy)
            ->groupBy('listaprecio_proveedor_id', 'articulo_id');

        $lineas = DB::table('listaprecio_proveedor_articulo as lpa')
            ->joinSub($maxFv, 'mx', function ($join): void {
                $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                    ->on('lpa.articulo_id', '=', 'mx.articulo_id')
                    ->on('lpa.fechavigencia', '=', 'mx.max_fv');
            })
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->where('lp.estado', 'ACTIVA')
            ->groupBy('lpa.articulo_id', 'lp.proveedor_id', 'lp.id', 'lp.fecha')
            ->select('lpa.articulo_id', 'lp.proveedor_id', 'lp.fecha', 'lp.id as lista_id')
            ->selectRaw('MAX(lpa.id) as linea_id');

        $maxFecha = DB::query()
            ->fromSub($lineas, 'lin')
            ->groupBy('articulo_id', 'proveedor_id')
            ->select('articulo_id', 'proveedor_id')
            ->selectRaw('MAX(fecha) as max_fecha');

        $listaElegida = DB::query()
            ->fromSub($lineas, 'lin')
            ->joinSub($maxFecha, 'mf', function ($join): void {
                $join->on('lin.articulo_id', '=', 'mf.articulo_id')
                    ->on('lin.proveedor_id', '=', 'mf.proveedor_id')
                    ->on('lin.fecha', '=', 'mf.max_fecha');
            })
            ->groupBy('lin.articulo_id', 'lin.proveedor_id')
            ->select('lin.articulo_id', 'lin.proveedor_id')
            ->selectRaw('MAX(lin.lista_id) as lista_id');

        return DB::query()
            ->fromSub($lineas, 'lin')
            ->joinSub($listaElegida, 'elegida', function ($join): void {
                $join->on('lin.articulo_id', '=', 'elegida.articulo_id')
                    ->on('lin.proveedor_id', '=', 'elegida.proveedor_id')
                    ->on('lin.lista_id', '=', 'elegida.lista_id');
            })
            ->join('listaprecio_proveedor_articulo as lpa_precio', 'lpa_precio.id', '=', 'lin.linea_id')
            ->select('lin.articulo_id', 'lin.proveedor_id', 'lpa_precio.precio');
    }
}
