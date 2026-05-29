<?php

namespace App\Http\Controllers\Presupuesto;

use App\Exports\Presupuesto\CapexReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Services\Presupuesto\CapexReporteService;
use Illuminate\Http\Request;

class CapexReporteController extends Controller
{
    public function __construct(
        private readonly CapexReporteService $capexReporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly PresupuestoRepositoryInterface $presupuestoRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-capex-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $presupuestoQuery = $this->presupuestoRepository->all();
        $centrocostoQuery = $this->centrocostoRepository->all();

        $filtros = $this->filtrosDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $resultado = null;

        if ($consultado) {
            $resultado = $this->capexReporteService->generar($filtros);
        }

        $capexOpciones = $this->capexReporteService
            ->consultarCapex($filtros)
            ->map(fn ($row) => [
                'id' => $row->id,
                'label' => $row->codigo.' — '.$row->nombre,
            ]);

        return view('presupuesto.capex_reporte.index', [
            'empresa_query' => $empresaQuery,
            'presupuesto_query' => $presupuestoQuery,
            'centrocosto_query' => $centrocostoQuery,
            'capex_opciones' => $capexOpciones,
            'filtros' => $filtros,
            'consultado' => $consultado,
            'resultado' => $resultado,
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-capex-reporte');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosDesdeRequest($request);
        $resultado = $this->capexReporteService->generar($filtros);
        $filas = $resultado['filas'] ?? [];
        $titulo = 'Reporte CAPEX';
        $subtitulo = $this->armarSubtituloFiltros($filtros);
        $totalFilas = $resultado['total'] ?? count($filas);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('presupuesto.capex_reporte.listado', compact(
                    'filas',
                    'titulo',
                    'subtitulo',
                    'totalFilas',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'reporte_capex_'.date('Ymd_His');

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CapexReporteExport($filas, $titulo, $subtitulo))
                    ->download('reporte_capex.xlsx');

            case 'CSV':
                return (new CapexReporteExport($filas, $titulo, $subtitulo))
                    ->download('reporte_capex.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('capex_reporte', array_merge($filtros, ['consultar' => 1]));
    }

    /**
     * @return array{empresa_id:?int, presupuesto_id:?int, centrocosto_id:?int, capex_id:?int}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'empresa_id' => $this->enteroOpcional($request->input('empresa_id')),
            'presupuesto_id' => $this->enteroOpcional($request->input('presupuesto_id')),
            'centrocosto_id' => $this->enteroOpcional($request->input('centrocosto_id')),
            'capex_id' => $this->enteroOpcional($request->input('capex_id')),
        ];
    }

    private function enteroOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }

    /**
     * @param  array{empresa_id:?int, presupuesto_id:?int, centrocosto_id:?int, capex_id:?int}  $filtros
     */
    private function armarSubtituloFiltros(array $filtros): string
    {
        $partes = [];

        if (! empty($filtros['empresa_id'])) {
            $nombre = $this->empresaRepository->findPorId($filtros['empresa_id'])?->nombre;
            if ($nombre) {
                $partes[] = 'Empresa: '.$nombre;
            }
        }
        if (! empty($filtros['presupuesto_id'])) {
            $nombre = $this->presupuestoRepository->find($filtros['presupuesto_id'])?->nombre;
            if ($nombre) {
                $partes[] = 'Presupuesto: '.$nombre;
            }
        }
        if (! empty($filtros['centrocosto_id'])) {
            $nombre = $this->centrocostoRepository->findPorId($filtros['centrocosto_id'])?->nombre;
            if ($nombre) {
                $partes[] = 'Centro de costo: '.$nombre;
            }
        }
        if (! empty($filtros['capex_id'])) {
            $capex = $this->capexReporteService->consultarCapex(['capex_id' => $filtros['capex_id']])->first();
            if ($capex) {
                $partes[] = 'CAPEX: '.$capex->codigo.' — '.$capex->nombre;
            }
        }

        return implode(' · ', $partes);
    }
}
