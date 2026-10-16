<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\DiferenciaCambioFacturaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\DiferenciaCambioFacturaListadoFiltros;
use App\Support\Contable\DiferenciaCambioFacturaReporteSupport;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DiferenciaCambioFacturaController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-diferencia-cambio-factura');

        $filtros = DiferenciaCambioFacturaListadoFiltros::resolverDesdeRequest($request);
        $filas = ($request->boolean('consultar'))
            ? DiferenciaCambioFacturaReporteSupport::paginar($filtros)
            : null;

        return view('contable.diferencia_cambio_factura.index', [
            'filtros' => $filtros,
            'filtrosQuery' => DiferenciaCambioFacturaListadoFiltros::paraQueryString($filtros),
            'filas' => $filas,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'consulto' => $request->boolean('consultar'),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-diferencia-cambio-factura');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $filtros = DiferenciaCambioFacturaListadoFiltros::resolverDesdeRequest($request);
        $formato = strtoupper($formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('diferencia_cambio_factura', DiferenciaCambioFacturaListadoFiltros::paraQueryString($filtros));
        }

        $filas = DiferenciaCambioFacturaReporteSupport::listar($filtros);
        if ($formato === 'PDF') {
            $html = view('contable.diferencia_cambio_factura.listado', [
                'filas' => $filas,
                'filtros' => $filtros,
                'titulo' => 'Diferencia de cambio en facturas de proveedor',
            ])->render();
            $ruta = storage_path('pdf/listados/diferencia_cambio_factura.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Dif. cambio facturas',
            ]);

            return response()->download($ruta);
        }

        $export = new DiferenciaCambioFacturaExport($filas, $filtros);

        return Excel::download(
            $export,
            'diferencia_cambio_factura.'.($formato === 'CSV' ? 'csv' : 'xlsx'),
            $formato === 'CSV' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
