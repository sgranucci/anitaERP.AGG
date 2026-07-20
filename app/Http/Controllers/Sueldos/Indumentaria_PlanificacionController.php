<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\PlanificacionIndumentariaExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\PlanificacionIndumentariaConsulta;
use App\Support\Sueldos\PlanificacionIndumentariaFiltros;
use Illuminate\Http\Request;

class Indumentaria_PlanificacionController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('ver-planificacion-indumentaria');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = PlanificacionIndumentariaFiltros::resolverDesdeRequest($request, $empresaDefault ? (int) $empresaDefault : null);

        $filas = [];
        $totales = ['prendas' => 0, 'cupo' => 0, 'entregado' => 0, 'pendiente' => 0, 'stock' => 0, 'sugerido' => 0];
        if ($request->boolean('consultar')) {
            $filas = PlanificacionIndumentariaConsulta::filas($filtros, $this->empresaRepository);
            $totales = PlanificacionIndumentariaConsulta::totales($filas);
        }

        return view('sueldos.indumentaria_planificacion.index', [
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => $filtros,
            'filtrosQuery' => array_merge(PlanificacionIndumentariaFiltros::paraQueryString($filtros), ['consultar' => 1]),
            'consultado' => $request->boolean('consultar'),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'prendas' => Prenda_Sueldos::query()->where('activo', true)->orderBy('orden')->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('ver-planificacion-indumentaria');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = PlanificacionIndumentariaFiltros::resolverDesdeRequest($request, $empresaDefault ? (int) $empresaDefault : null);

        switch ($formato) {
            case 'PDF':
                $filas = PlanificacionIndumentariaConsulta::filas($filtros, $this->empresaRepository);
                $totales = PlanificacionIndumentariaConsulta::totales($filas);

                $view = \View::make('sueldos.indumentaria_planificacion.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/planificacion_indumentaria.pdf');

                return response()->download($path.'/planificacion_indumentaria.pdf');

            case 'EXCEL':
                return app(PlanificacionIndumentariaExport::class)
                    ->parametros($filtros)
                    ->download('planificacion_indumentaria.xlsx');

            case 'CSV':
                return app(PlanificacionIndumentariaExport::class)
                    ->parametros($filtros)
                    ->download('planificacion_indumentaria.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('planificacion_indumentaria', array_merge(
            PlanificacionIndumentariaFiltros::paraQueryString($filtros),
            ['consultar' => 1]
        ));
    }
}
