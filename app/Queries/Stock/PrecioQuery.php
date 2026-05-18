<?php

namespace App\Queries\Stock;

use App\Models\Stock\Combinacion;
use App\Models\Stock\Precio;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class PrecioQuery implements PrecioQueryInterface
{
    public function __construct(protected Precio $model) {}

    public function resolverFiltrosDesdeRequest(Request $request): array
    {
        $filtros = [];
        if ($request->url() != $request->fullUrl()) {
            $url = urldecode($request->fullUrl());
            $components = parse_url($url);
            parse_str($components['query'] ?? '', $filtros);

            session(['filtrosPrecios' => $filtros]);
        } else {
            $filtros = session('filtrosPrecios');
        }

        $fechaVigenciaFiltro = $request->filled('fecha_vigencia')
            ? Carbon::parse($request->fecha_vigencia)->format('Y-m-d')
            : (is_array($filtros) && ! empty($filtros['fecha_vigencia'] ?? null)
                ? Carbon::parse($filtros['fecha_vigencia'])->format('Y-m-d')
                : Carbon::today()->format('Y-m-d'));

        $listaprecioIdFiltro = $request->input('listaprecio_id');
        if (($listaprecioIdFiltro === null || $listaprecioIdFiltro === '')
            && is_array($filtros) && isset($filtros['listaprecio_id']) && $filtros['listaprecio_id'] !== null && $filtros['listaprecio_id'] !== '') {
            $listaprecioIdFiltro = (int) $filtros['listaprecio_id'];
        }
        if ($listaprecioIdFiltro !== null && $listaprecioIdFiltro !== '') {
            $listaprecioIdFiltro = (int) $listaprecioIdFiltro;
        } else {
            $listaprecioIdFiltro = null;
        }

        return [
            'fecha_vigencia' => $fechaVigenciaFiltro,
            'listaprecio_id' => $listaprecioIdFiltro,
            'filtros' => is_array($filtros) ? $filtros : [],
            'busqueda' => trim((string) $request->get('busqueda', '')),
        ];
    }

    public function leePrecios(
        string $fechaReferencia,
        ?int $listaprecioId,
        $filtros,
        ?string $busqueda,
        bool $flPaginando
    ) {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $descripcionVisible = DB::raw(
            "COALESCE(NULLIF(TRIM(articulo.descripcion), ''), NULLIF(TRIM(articulo.detalle), ''), TRIM(articulo.sku)) as articulo_descripcion"
        );

        $select = [
            'precio.id',
            'precio.fechavigencia',
            'precio.precio',
            'precio.precioanterior',
            'articulo.sku',
            $descripcionVisible,
            'listaprecio.nombre as listaprecio_nombre',
            'moneda.nombre as moneda_nombre',
            'usuario.nombre as usuario_nombre',
        ];

        $q = $this->model->newQuery()
            ->select($select)
            ->join('articulo', 'articulo.id', '=', 'precio.articulo_id')
            ->join('listaprecio', 'listaprecio.id', '=', 'precio.listaprecio_id')
            ->join('moneda', 'moneda.id', '=', 'precio.moneda_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'precio.usuarioultcambio_id')
            ->whereRaw(
                'precio.fechavigencia = (SELECT MAX(p3.fechavigencia) FROM precio AS p3 WHERE p3.articulo_id = precio.articulo_id AND p3.listaprecio_id = precio.listaprecio_id AND p3.fechavigencia <= ?)',
                [$fechaReferencia]
            );

        if ($listaprecioId !== null) {
            $q->where('precio.listaprecio_id', $listaprecioId);
        }

        $q = $this->aplicarFiltrosEstado($q, $filtros);
        $q = $this->aplicarFiltroCombinacion($q);

        if ($busqueda !== null && $busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $q->where(function ($w) use ($like, $busqueda) {
                $w->where('articulo.sku', 'LIKE', $like)
                    ->orWhere('articulo.descripcion', 'LIKE', $like)
                    ->orWhere('articulo.detalle', 'LIKE', $like)
                    ->orWhere('listaprecio.nombre', 'LIKE', $like)
                    ->orWhere('moneda.nombre', 'LIKE', $like);
                if (ctype_digit($busqueda)) {
                    $w->orWhere('precio.id', (int) $busqueda);
                }
            });
        }

        $q->orderByRaw(
            "CASE WHEN TRIM(COALESCE(articulo.descripcion, articulo.detalle, '')) = '' THEN 1 ELSE 0 END"
        )
            ->orderByRaw(
                "COALESCE(NULLIF(TRIM(articulo.descripcion), ''), NULLIF(TRIM(articulo.detalle), ''), TRIM(articulo.sku))"
            )
            ->orderBy('listaprecio.nombre')
            ->orderByDesc('precio.fechavigencia')
            ->orderBy('precio.id');

        if ($flPaginando) {
            return $q->paginate(15)->withQueryString();
        }

        return $q->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Precio>  $builder
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Precio>
     */
    private function aplicarFiltrosEstado($builder, $filtros)
    {
        if ($filtros == '' || empty($filtros['filter_column'] ?? null) || ! is_array($filtros['filter_column'])) {
            return $builder;
        }

        for ($ii = 0; $ii < count($filtros['filter_column']); $ii++) {
            if (($filtros['filter_column'][$ii]['type'] ?? '') == '') {
                continue;
            }
            if (($filtros['filter_column'][$ii]['column'] ?? '') == 'estado' &&
                ($filtros['filter_column'][$ii]['type'] ?? '') == '=') {
                switch ($filtros['filter_column'][$ii]['value'] ?? '') {
                    case 'F':
                        $builder->where('articulo.nofactura', '0');
                        break;
                    case 'N':
                        $builder->where('articulo.nofactura', '1');
                        break;
                }
            }
        }

        return $builder;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Precio>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Precio>
     */
    private function aplicarFiltroCombinacion($builder)
    {
        if (! Combinacion::query()->exists()) {
            return $builder;
        }

        return $builder->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('combinacion')
                ->whereRaw('combinacion.articulo_id = precio.articulo_id');
        });
    }

    public function leeHistorialPreciosArticulo(int $articuloId, ?string $fechaReferencia = null): array
    {
        $fechaRef = $fechaReferencia;
        if (! is_string($fechaRef) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRef)) {
            $fechaRef = Carbon::today()->format('Y-m-d');
        }

        $rows = $this->model->newQuery()
            ->select([
                'precio.id',
                'precio.listaprecio_id',
                'precio.fechavigencia',
                'precio.precio',
                'precio.precioanterior',
                'listaprecio.codigo as listaprecio_codigo',
                'listaprecio.nombre as listaprecio_nombre',
                'moneda.nombre as moneda_nombre',
                'usuario.nombre as usuario_nombre',
            ])
            ->join('listaprecio', 'listaprecio.id', '=', 'precio.listaprecio_id')
            ->join('moneda', 'moneda.id', '=', 'precio.moneda_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'precio.usuarioultcambio_id')
            ->where('precio.articulo_id', $articuloId)
            ->orderBy('listaprecio.codigo')
            ->orderByDesc('precio.fechavigencia')
            ->orderByDesc('precio.id')
            ->get();

        $vigentePorLista = [];
        foreach ($rows as $row) {
            $listaId = (int) $row->listaprecio_id;
            $fv = Carbon::parse($row->fechavigencia)->format('Y-m-d');
            if ($fv > $fechaRef) {
                continue;
            }
            if (! isset($vigentePorLista[$listaId]) || $fv > $vigentePorLista[$listaId]['fechavigencia']) {
                $vigentePorLista[$listaId] = ['fechavigencia' => $fv, 'id' => (int) $row->id];
            } elseif ($fv === $vigentePorLista[$listaId]['fechavigencia'] && (int) $row->id > $vigentePorLista[$listaId]['id']) {
                $vigentePorLista[$listaId]['id'] = (int) $row->id;
            }
        }

        $filas = [];
        foreach ($rows as $row) {
            $listaId = (int) $row->listaprecio_id;
            $fv = Carbon::parse($row->fechavigencia)->format('Y-m-d');
            $esVigente = isset($vigentePorLista[$listaId])
                && $vigentePorLista[$listaId]['id'] === (int) $row->id;

            $filas[] = [
                'id' => (int) $row->id,
                'listaprecio_id' => $listaId,
                'listaprecio_codigo' => (string) ($row->listaprecio_codigo ?? ''),
                'listaprecio_nombre' => (string) ($row->listaprecio_nombre ?? ''),
                'fechavigencia' => $fv,
                'fechavigencia_fmt' => Carbon::parse($fv)->format('d/m/Y'),
                'moneda_nombre' => (string) ($row->moneda_nombre ?? ''),
                'precio' => (float) $row->precio,
                'precioanterior' => (float) $row->precioanterior,
                'es_vigente' => $esVigente,
                'usuario_nombre' => (string) ($row->usuario_nombre ?? ''),
            ];
        }

        $articulo = DB::table('articulo')
            ->select('id', 'sku', 'descripcion', 'detalle')
            ->where('id', $articuloId)
            ->first();

        $descripcion = trim((string) ($articulo->descripcion ?? ''));
        if ($descripcion === '') {
            $descripcion = trim((string) ($articulo->detalle ?? ''));
        }

        return [
            'articulo' => [
                'id' => $articuloId,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => $descripcion,
            ],
            'fecha_referencia' => $fechaRef,
            'filas' => $filas,
        ];
    }
}
