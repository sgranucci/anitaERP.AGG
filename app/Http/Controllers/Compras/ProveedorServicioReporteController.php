<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProveedorServicioReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorServicioReporteService;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\ProveedorServicioReporteFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProveedorServicioReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'proveedor_servicio_reporte';

    private const TITULO = 'Proveedores con clientes de servicios';

    public function __construct(
        private ProveedorServicioReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-proveedor-servicio');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);
        $consultado = $request->boolean('consultar')
            && ProveedorServicioReporteFiltros::tieneCriteriosAplicados($filtros);

        $filas = null;
        $totales = null;
        $filtrosQuery = ProveedorServicioReporteFiltros::paraQueryString($filtros);

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);

            $perPage = max(25, min(500, (int) $request->input('per_page', 100)));
            if ($request->has('per_page')) {
                $filtrosQuery['per_page'] = $perPage;
            }
            $filas = $this->service
                ->paginar($filtros, $perPage, max(1, (int) $request->input('page', 1)))
                ->appends($filtrosQuery);
            $totales = $this->service->totales($filtros);
        }

        return view('compras.proveedor_servicio_reporte.index', [
            'empresa_query' => $empresaQuery,
            'estados' => ProveedorServicioReporteFiltros::ESTADOS,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filas ? $filas->items() : [],
            'totales' => $totales,
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores(
                (string) ($filtros['proveedores'] ?? ''),
            ),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-proveedor-servicio');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);

        if (! ProveedorServicioReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_proveedor_servicio');
        }

        $resultado = $this->service->generar($filtros);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $titulo = self::TITULO;
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $html = \View::make('compras.proveedor_servicio_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $ruta = storage_path('pdf/listados/listado_proveedor_servicio.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Proveedores con servicios',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new ProveedorServicioReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('proveedores_servicios.xlsx');

            case 'CSV':
                return (new ProveedorServicioReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('proveedores_servicios.csv', Excel::CSV);
        }

        return redirect()->route('reporte_proveedor_servicio', ProveedorServicioReporteFiltros::paraQueryString($filtros));
    }

    /**
     * Empresas: lo pedido, si no la última consulta del usuario, si no todas las autorizadas.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request, $empresaQuery): array
    {
        $filtros = ProveedorServicioReporteFiltros::resolverDesdeRequest($request);
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($filtros['empresa_ids'] === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        } else {
            $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
                $filtros['empresa_ids'],
                $permitidos,
            );
        }

        if ($filtros['empresa_ids'] === []) {
            $filtros['empresa_ids'] = $permitidos;
        }

        return $filtros;
    }
}
