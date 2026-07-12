<?php

namespace App\Queries\Stock;

use App\Models\Stock\Precio;
use App\Support\Stock\PrecioListadoFiltros;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Carbon\Carbon;
use DB;

class PrecioQuery implements PrecioQueryInterface
{
    public function __construct(protected Precio $model) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, object>
     */
    public function leePrecios(array $filtros, bool $flPaginando)
    {
        if (is_string($filtros)) {
            $filtros = ['busqueda' => $filtros];
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $fechaReferencia = (string) ($filtros['fecha_vigencia'] ?? Carbon::today()->format('Y-m-d'));
        $listaprecioId = $filtros['listaprecio_id'] ?? null;
        if ($listaprecioId !== null && $listaprecioId !== '') {
            $listaprecioId = (int) $listaprecioId;
        } else {
            $listaprecioId = null;
        }
        $ocultarPrecioCero = (bool) ($filtros['ocultar_precio_cero'] ?? true);

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
            'categoria.nombre as categoria_nombre',
            'listaprecio.nombre as listaprecio_nombre',
            'moneda.nombre as moneda_nombre',
            'usuario.nombre as usuario_nombre',
        ];

        $q = $this->model->newQuery()
            ->select($select)
            ->join('articulo', 'articulo.id', '=', 'precio.articulo_id')
            ->leftJoin('categoria', 'categoria.id', '=', 'articulo.categoria_id')
            ->join('listaprecio', 'listaprecio.id', '=', 'precio.listaprecio_id')
            ->join('moneda', 'moneda.id', '=', 'precio.moneda_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'precio.usuarioultcambio_id');
        PrecioListaVigenteSupport::aplicarFiltroVigenteEnQuery($q, $fechaReferencia, 'precio', null, $listaprecioId);

        $q = PrecioSoloFacturableSupport::aplicarFiltroQuery($q);

        if ($ocultarPrecioCero) {
            $q->where('precio.precio', '>', 0);
        }

        PrecioListadoFiltros::aplicar($q, $filtros);

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

    public function leeHistorialPreciosArticulo(int $articuloId, ?string $fechaReferencia = null, ?int $listaprecioId = null): array
    {
        $fechaRef = $fechaReferencia;
        if (! is_string($fechaRef) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRef)) {
            $fechaRef = Carbon::today()->format('Y-m-d');
        }

        $q = $this->model->newQuery()
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
            ->where('precio.articulo_id', $articuloId);

        if ($listaprecioId !== null && $listaprecioId > 0) {
            $q->where('precio.listaprecio_id', $listaprecioId);
        }

        $rows = $q->orderBy('listaprecio.codigo')
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
