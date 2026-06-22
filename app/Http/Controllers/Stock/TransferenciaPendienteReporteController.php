<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\TransferenciaPendienteReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Contable\BienUso;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\TransferenciaPendienteReporteService;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaPendienteListadoFiltros;
use Illuminate\Http\Request;

class TransferenciaPendienteReporteController extends Controller
{
    public function __construct(
        private TransferenciaPendienteReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-reporte-transferencias-pendientes');

        $filtros = TransferenciaPendienteListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = TransferenciaPendienteListadoFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');

        $filas = null;
        $totales = null;

        if ($consultado) {
            ini_set('memory_limit', '512M');
            $filas = $this->service->consultar($filtros, true, 25);
            $totales = $this->service->totales($filtros);
        }

        return view('stock.transferencia_pendiente_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'bienesUso' => BienUso::query()->where('estado', 'A')->orderBy('hostname')->get(['id', 'codigo_inventario', 'hostname']),
            'estados' => TransferenciaMercaderiaEstados::etiquetas(),
            'puede_aprobar' => can('aprobar-transferencia-mercaderia', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-transferencias-pendientes');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TransferenciaPendienteListadoFiltros::resolverDesdeRequest($request);
        $filas = $this->service->consultar($filtros, false);
        $totales = $this->service->totales($filtros);
        $titulo = 'Transferencias pendientes de aprobación';
        $subtitulo = $this->service->subtituloFiltros($filtros);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('stock.transferencia_pendiente_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                    'estados',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'transferencias_pendientes_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new TransferenciaPendienteReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('transferencias_pendientes.xlsx');

            case 'CSV':
                return (new TransferenciaPendienteReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('transferencias_pendientes.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_transferencias_pendientes', TransferenciaPendienteListadoFiltros::paraQueryString($filtros));
    }
}
