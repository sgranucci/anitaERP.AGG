<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\SuitecrmNotaAuditoriaExport;
use App\Http\Controllers\Controller;
use App\Services\Crm\SuitecrmNotaAuditoriaService;
use App\Support\SuitecrmPermiso;
use App\Support\Ventas\SuitecrmNotaAuditoriaListadoFiltros;
use Illuminate\Http\Request;

class SuitecrmNotaAuditoriaController extends Controller
{
    public function __construct(
        private readonly SuitecrmNotaAuditoriaService $auditoriaService,
    ) {}

    public function index(Request $request)
    {
        can('listar-auditoria-notas-suitecrm');

        if (! SuitecrmPermiso::integracionActiva()) {
            return view('ventas.suitecrm_nota_auditoria.index', [
                'integracionActiva' => false,
                'filtros' => SuitecrmNotaAuditoriaListadoFiltros::filtrosVacios(),
                'filtrosQuery' => [],
                'consultado' => false,
                'vendedores' => collect(),
                'tipos' => SuitecrmNotaAuditoriaListadoFiltros::TIPOS,
                'resultado' => null,
                'paginator' => null,
                'subtitulo' => '',
                'mostrarLinks' => true,
                'puede_ver_cliente' => false,
            ]);
        }

        $filtros = SuitecrmNotaAuditoriaListadoFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $vendedores = $this->auditoriaService->opcionesVendedor();
        $resultado = null;
        $paginator = null;

        if ($consultado) {
            $resultado = $this->auditoriaService->generar($filtros);
            $paginator = $this->auditoriaService->paginar(
                $filtros,
                max(1, (int) $request->input('page', 1)),
                50
            );
        }

        $filtrosQuery = array_merge(
            SuitecrmNotaAuditoriaListadoFiltros::paraQueryString($filtros),
            $consultado ? ['consultar' => 1] : []
        );

        return view('ventas.suitecrm_nota_auditoria.index', [
            'integracionActiva' => true,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'vendedores' => $vendedores,
            'tipos' => SuitecrmNotaAuditoriaListadoFiltros::TIPOS,
            'resultado' => $resultado,
            'paginator' => $paginator,
            'subtitulo' => $this->auditoriaService->armarSubtitulo(
                $filtros,
                $vendedores,
                $resultado['filas'] ?? []
            ),
            'mostrarLinks' => true,
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-auditoria-notas-suitecrm');

        if (! SuitecrmPermiso::integracionActiva()) {
            return redirect()->route('auditoria_notas_suitecrm')
                ->with('error', 'La integración SuiteCRM no está habilitada.');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SuitecrmNotaAuditoriaListadoFiltros::resolverDesdeRequest($request);
        $resultado = $this->auditoriaService->generar($filtros);
        $filas = $resultado['filas'];
        $agrupadas = $resultado['agrupadas_por_fecha'];
        $titulo = 'Auditoría de notas CRM';
        $subtitulo = $this->auditoriaService->armarRangoFechasSubtitulo($filtros, $filas);
        $totalFilas = $resultado['total'];

        switch ($formato) {
            case 'PDF':
                $view = \View::make('ventas.suitecrm_nota_auditoria.listado', compact(
                    'filas',
                    'agrupadas',
                    'titulo',
                    'subtitulo',
                    'totalFilas',
                ))->render();
                $nombrePdf = 'auditoria_notas_crm_'.date('Ymd_His').'.pdf';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('a4', 'landscape');
                $pdf->loadHTML($view);

                // Descarga en memoria: evita Permission denied si storage/pdf/listados
                // quedó con dueño distinto de www-data (p. ej. smoke CLI).
                return $pdf->download($nombrePdf);
            case 'EXCEL':
                return (new SuitecrmNotaAuditoriaExport($filas, $titulo, $subtitulo, $totalFilas))
                    ->download('auditoria_notas_crm.xlsx');

            case 'CSV':
                return (new SuitecrmNotaAuditoriaExport($filas, $titulo, $subtitulo, $totalFilas))
                    ->download('auditoria_notas_crm.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'auditoria_notas_suitecrm',
            array_merge(SuitecrmNotaAuditoriaListadoFiltros::paraQueryString($filtros), ['consultar' => 1])
        );
    }
}
