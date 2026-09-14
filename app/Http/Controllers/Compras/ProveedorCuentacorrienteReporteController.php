<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProveedorCuentacorrienteReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorCuentacorrienteReporteService;
use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
use App\Support\Compras\ProveedorCuentacorrienteReporteProveedorSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProveedorCuentacorrienteReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'proveedor_cuentacorriente_reporte';

    public function __construct(
        private readonly ProveedorCuentacorrienteReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-proveedor-cuentacorriente-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProveedorCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($request->boolean('consultar') && ProveedorCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);

            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
            $page = max(1, (int) $request->input('page', 1));
            $filas = $this->reporteService->paginarFilas($resultado['filas'] ?? [], $perPage, $page);
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = ProveedorCuentacorrienteReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $proveedoresIniciales = $this->proveedoresInicialesParaVista($filtros, $resultado);

        return view('compras.proveedor_cuentacorriente_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'proveedores_iniciales' => $proveedoresIniciales,
            'subtitulo' => ProveedorCuentacorrienteReporteFiltros::armarSubtitulo(
                $filtros,
                $this->nombreEmpresa($filtros['empresa_id'] ?? null)
            ),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_pago' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
            'mostrarLinks' => true,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-proveedor-cuentacorriente-reporte');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProveedorCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if (! ProveedorCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('proveedor_cuentacorriente_reporte');
        }

        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'] ?? [];
        $titulo = (($filtros['modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA)
            ? 'Ficha cuenta corriente de proveedores'
            : 'Deuda de proveedores';
        $subtitulo = ProveedorCuentacorrienteReporteFiltros::armarSubtitulo(
            $filtros,
            $this->nombreEmpresa($filtros['empresa_id'] ?? null)
        );

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('compras.proveedor_cuentacorriente_reporte.listado', [
                    'filas' => $filas,
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'mostrarLinks' => false,
                    'para_pdf' => true,
                    'puede_ver_proveedor' => false,
                    'puede_ver_comprobante' => false,
                    'puede_ver_pago' => false,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'proveedor_cc_reporte_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ProveedorCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('proveedor_cuentacorriente_reporte.xlsx');

            case 'CSV':
                return (new ProveedorCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('proveedor_cuentacorriente_reporte.csv', Excel::CSV);
        }

        return redirect()->route(
            'proveedor_cuentacorriente_reporte',
            array_merge(ProveedorCuentacorrienteReporteFiltros::paraQueryString($filtros), ['consultar' => 1])
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        if (! empty($filtros['empresa_id'])) {
            return $filtros;
        }

        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cached = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
        if ($cached !== null && in_array($cached, $permitidos, true)) {
            $filtros['empresa_id'] = $cached;
        } elseif ($empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        return $filtros;
    }

    private function nombreEmpresa(mixed $empresaId): ?string
    {
        $id = (int) $empresaId;
        if ($id <= 0) {
            return null;
        }

        return $this->empresaRepository->findPorId($id)?->nombre;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function proveedoresInicialesParaVista(array $filtros, ?array $resultado): array
    {
        if (! empty($resultado['proveedores_resueltos'])) {
            return $resultado['proveedores_resueltos'];
        }

        if (($filtros['alcance_proveedores'] ?? '') === ProveedorCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES) {
            return ProveedorCuentacorrienteReporteProveedorSupport::etiquetasPorIds($filtros['proveedor_ids'] ?? []);
        }

        return [];
    }
}
