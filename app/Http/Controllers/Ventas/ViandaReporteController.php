<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ViandaConsumoListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaConsumo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\Vianda\ViandaConsumoListadoFiltros;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ViandaReporteController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-vianda-gastronomia');

        $filtros = ViandaConsumoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $filas = $this->baseQuery($filtros)->paginate(15);

        $filtrosQuery = ViandaConsumoListadoFiltros::paraQueryString($filtros);
        $filas->appends($filtrosQuery);

        $resumenCentroCosto = $this->resumenPorCentroCosto($filtros);
        $totales = $this->totalesGenerales($filtros);

        return view('ventas.vianda.reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'filas' => $filas,
            'resumen_centrocosto' => $resumenCentroCosto,
            'totales' => $totales,
            'empresa_query' => ViandaEmpresaSupport::empresasSeleccionables(),
            'centrocosto_query' => $this->centrocostoQuery(),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-reporte-vianda-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ViandaConsumoListadoFiltros::resolverDesdeRequest($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $formato = strtoupper($formato);

        switch ($formato) {
            case 'PDF':
                return $this->exportarPdf($filtros);
            case 'EXCEL':
                return (new ViandaConsumoListadoExport())
                    ->parametros($filtros)
                    ->download('viandas.xlsx');
            case 'CSV':
                return (new ViandaConsumoListadoExport())
                    ->parametros($filtros)
                    ->download('viandas.csv', Excel::CSV);
        }

        return redirect()->route('consultar_reporte_vianda_gastronomia', ViandaConsumoListadoFiltros::paraQueryString($filtros));
    }

    private function exportarPdf(array $filtros)
    {
        $filas = $this->baseQuery($filtros)->get();
        $resumenCentroCosto = $this->resumenPorCentroCosto($filtros);
        $totales = $this->totalesGenerales($filtros);

        $view = \View::make('ventas.vianda.reporte.listado', [
            'filas' => $filas,
            'resumen_centrocosto' => $resumenCentroCosto,
            'totales' => $totales,
            'filtros' => $filtros,
            'subtitulo' => ViandaConsumoListadoFiltros::subtitulo(
                $filtros,
                $this->nombreEmpresa($filtros),
                $this->nombreCentrocosto($filtros),
            ),
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $nombrePdf = 'listado_viandas_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<ViandaConsumo>
     */
    private function baseQuery(array $filtros)
    {
        $query = ViandaConsumo::query()
            ->with(['centrocosto', 'empresa', 'terminal']);

        $query = ViandaConsumoListadoFiltros::aplicar($query, $filtros);

        return ViandaConsumoListadoFiltros::aplicarOrden($query, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{centrocosto:string,consumos:int,items:int,costo:float,venta:float}>
     */
    private function resumenPorCentroCosto(array $filtros): array
    {
        $query = ViandaConsumo::query();
        ViandaConsumoListadoFiltros::aplicar($query, $filtros);

        $filas = $query->selectRaw('centrocosto_id, count(*) as consumos, sum(cantidad_items) as items, sum(total_costo) as costo, sum(total_venta) as venta')
            ->groupBy('centrocosto_id')
            ->orderByDesc('items')
            ->get();

        $ids = $filas->pluck('centrocosto_id')->filter()->map(fn ($v) => (int) $v)->all();
        $nombres = $ids === []
            ? collect()
            : Centrocosto::query()->whereIn('id', $ids)->pluck('nombre', 'id');

        return $filas->map(fn ($f) => [
            'centrocosto' => $f->centrocosto_id ? (string) ($nombres[(int) $f->centrocosto_id] ?? ('C.C. '.$f->centrocosto_id)) : 'Sin centro de costo',
            'consumos' => (int) $f->consumos,
            'items' => (int) $f->items,
            'costo' => (float) $f->costo,
            'venta' => (float) $f->venta,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{consumos:int,items:int,costo:float,venta:float}
     */
    private function totalesGenerales(array $filtros): array
    {
        $query = ViandaConsumo::query();
        ViandaConsumoListadoFiltros::aplicar($query, $filtros);

        $row = $query->selectRaw('count(*) as consumos, sum(cantidad_items) as items, sum(total_costo) as costo, sum(total_venta) as venta')->first();

        return [
            'consumos' => (int) ($row->consumos ?? 0),
            'items' => (int) ($row->items ?? 0),
            'costo' => (float) ($row->costo ?? 0),
            'venta' => (float) ($row->venta ?? 0),
        ];
    }

    private function centrocostoQuery()
    {
        return Centrocosto::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    private function nombreEmpresa(array $filtros): ?string
    {
        $id = (int) ($filtros['empresa_id'] ?? 0);

        return $id > 0 ? $this->empresaRepository->find($id)?->nombre : null;
    }

    private function nombreCentrocosto(array $filtros): ?string
    {
        $id = (int) ($filtros['centrocosto_id'] ?? 0);

        return $id > 0 ? Centrocosto::query()->where('id', $id)->value('nombre') : null;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }
}
