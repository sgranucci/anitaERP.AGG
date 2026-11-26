<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\AhorroReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedor;
use App\Models\Compras\TipoAhorro;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Usoarticulo;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\AhorroReporteService;
use App\Support\Compras\AhorroReporteFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Maatwebsite\Excel\Excel;

class AhorroReporteController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(
        private AhorroReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-ahorro-compras');

        $filtros = AhorroReporteFiltros::resolverDesdeRequest($request);
        $filtrosQuery = AhorroReporteFiltros::paraQueryString($filtros);
        $paginado = null;
        $resumen = null;

        if ($filtros['consultar']) {
            $datos = $this->service->generar($filtros);
            $resumen = $datos['resumen'];
            $pagina = Paginator::resolveCurrentPage();
            $paginado = new LengthAwarePaginator(
                $datos['filas']->forPage($pagina, self::POR_PAGINA)->values(),
                $datos['filas']->count(),
                self::POR_PAGINA,
                $pagina,
                ['path' => $request->url(), 'query' => $filtrosQuery],
            );
        }

        return view('compras.ahorro_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'filas' => $paginado,
            'resumen' => $resumen,
            'proveedor' => $filtros['proveedor_id'] > 0 ? Proveedor::query()->find($filtros['proveedor_id'], ['id', 'codigo', 'nombre']) : null,
            'centrocosto' => $filtros['centrocosto_id'] > 0 ? Centrocosto::query()->find($filtros['centrocosto_id'], ['id', 'codigo', 'nombre']) : null,
            'usoarticulo' => $filtros['usoarticulo_id'] > 0 ? Usoarticulo::query()->find($filtros['usoarticulo_id'], ['id', 'nombre']) : null,
            'tipoahorro' => $filtros['tipoahorro_id'] > 0 ? TipoAhorro::query()->find($filtros['tipoahorro_id'], ['id', 'nombre']) : null,
            'tiposAhorro' => TipoAhorro::query()->where('activo', true)->orderBy('orden')->get(['id', 'nombre'])
                ->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => (string) $t->nombre])->values(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'compradores' => $this->usuarioRepository->listadoOperativoParaSelector(
                null,
                null,
                ['id', 'nombre', 'usuario'],
            ),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-ahorro-compras');

        $filtros = AhorroReporteFiltros::resolverDesdeRequest($request);
        $formato = strtoupper((string) $formato);

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('reporte_ahorro_compras', AhorroReporteFiltros::paraQueryString($filtros));
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $datos = $this->service->generar($filtros);
        $titulo = 'Reporte de ahorros en compras';
        $subtitulo = $this->service->subtituloFiltros($filtros);
        $marca = date('Ymd_His');

        switch ($formato) {
            case 'PDF':
                $html = view('compras.ahorro_reporte.listado', [
                    'filas' => $datos['filas'],
                    'resumen' => $datos['resumen'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $ruta = storage_path('pdf/listados/ahorro_compras_'.$marca.'.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => $titulo,
                    'dompdf' => ['isFontSubsettingEnabled' => false, 'isJavascriptEnabled' => false],
                ]);

                return response()->download($ruta);

            case 'CSV':
                return (new AhorroReporteExport($datos['filas'], $datos['resumen'], $titulo, $subtitulo))
                    ->download('ahorro_compras_'.$marca.'.csv', Excel::CSV);

            default:
                return (new AhorroReporteExport($datos['filas'], $datos['resumen'], $titulo, $subtitulo))
                    ->download('ahorro_compras_'.$marca.'.xlsx');
        }
    }
}
