<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\HistorialDevolucionExport;
use App\Http\Controllers\Controller;
use App\Services\Ventas\FacturacionLocal\DevolucionHistorialService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\FacturacionLocal\DevolucionHistorialListadoFiltros;
use App\Support\Ventas\FacturacionLocal\DevolucionHistorialOrigenSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HistorialDevolucionController extends Controller
{
    public function __construct(
        private readonly DevolucionHistorialService $service,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-historial-devolucion-facturacion-local');

        $filtros = DevolucionHistorialListadoFiltros::resolverDesdeRequest($request);
        $datas = null;
        $totales = ['cantidad' => 0, 'importe' => 0.0, 'sin_stock' => 0];
        if ((int) ($filtros['consultar'] ?? 0) === 1) {
            $datas = $this->service->leeListado($filtros, true);
            $totales = $this->service->totales($filtros);
        }

        return view('ventas.facturacion_local.historial_devolucion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => DevolucionHistorialListadoFiltros::paraQueryString($filtros),
            'totales' => $totales,
            'origenes' => DevolucionHistorialOrigenSupport::ETIQUETAS,
            'subtitulo' => DevolucionHistorialListadoFiltros::subtitulo($filtros),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        $this->assertFerli();
        can('listar-historial-devolucion-facturacion-local');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = DevolucionHistorialListadoFiltros::resolverDesdeRequest($request);
        if ((int) ($filtros['consultar'] ?? 0) !== 1) {
            $filtros['consultar'] = 1;
        }
        $datas = $this->service->leeListado($filtros, false);
        $totales = $this->service->totales($filtros);
        $subtitulo = DevolucionHistorialListadoFiltros::subtitulo($filtros);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('ventas.facturacion_local.historial_devolucion.listado', [
                'datas' => $datas,
                'subtitulo' => $subtitulo,
                'totales' => $totales,
                'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas),
                'totalFilas' => $datas->count(),
                'puede_ver' => false,
            ])->render();
            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ruta = $dir.'/listado_historial_devolucion.pdf';
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Historial de devoluciones',
            ]);

            return response()->download($ruta, 'historial_devoluciones.pdf')->deleteFileAfterSend(true);
        }

        if ($formato === 'EXCEL') {
            return (new HistorialDevolucionExport)->parametros($filtros, $subtitulo, $totales)->download('historial_devoluciones.xlsx');
        }
        if ($formato === 'CSV') {
            return (new HistorialDevolucionExport)->parametros($filtros, $subtitulo, $totales)->download('historial_devoluciones.csv', Excel::CSV);
        }

        return redirect()->route('facturacion_local_historial_devoluciones', DevolucionHistorialListadoFiltros::paraQueryString($filtros));
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
