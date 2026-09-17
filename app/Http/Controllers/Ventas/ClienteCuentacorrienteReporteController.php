<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ClienteCuentacorrienteReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\ClienteCuentacorrienteReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\ClienteCuentacorrienteReporteClienteSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteFiltros;
use App\Support\Ventas\ClienteCuentacorrienteReporteVendedorSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ClienteCuentacorrienteReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'cliente_cuentacorriente_reporte';

    public function __construct(
        private readonly ClienteCuentacorrienteReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cliente-cuentacorriente-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ClienteCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($request->boolean('consultar') && ClienteCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
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

        $filtrosQuery = ClienteCuentacorrienteReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $clientesIniciales = $this->clientesInicialesParaVista($filtros, $resultado);
        $vendedoresIniciales = $this->vendedoresInicialesParaVista($filtros, $resultado);

        return view('ventas.cliente_cuentacorriente_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'clientes_iniciales' => $clientesIniciales,
            'vendedores_iniciales' => $vendedoresIniciales,
            'subtitulo' => ClienteCuentacorrienteReporteFiltros::armarSubtitulo(
                $filtros,
                $this->nombreEmpresa($filtros['empresa_id'] ?? null)
            ),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_factura' => can('listar-factura', false) || can('editar-factura', false),
            'puede_ver_cobranza' => can('listar-cobranza', false) || can('editar-cobranza', false),
            'mostrarLinks' => true,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-cliente-cuentacorriente-reporte');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ClienteCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if (! ClienteCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('cliente_cuentacorriente_reporte');
        }

        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'] ?? [];
        $titulo = (($filtros['modo'] ?? '') === ClienteCuentacorrienteReporteFiltros::MODO_FICHA)
            ? 'Ficha cuenta corriente de clientes'
            : 'Deuda de clientes';
        $subtitulo = ClienteCuentacorrienteReporteFiltros::armarSubtitulo(
            $filtros,
            $this->nombreEmpresa($filtros['empresa_id'] ?? null)
        );

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.cliente_cuentacorriente_reporte.listado', [
                    'filas' => $filas,
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'mostrarLinks' => false,
                    'para_pdf' => true,
                    'puede_ver_cliente' => false,
                    'puede_ver_factura' => false,
                    'puede_ver_cobranza' => false,
                ])->render();

                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'cliente_cc_reporte_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ClienteCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('cliente_cuentacorriente_reporte.xlsx');

            case 'CSV':
                return (new ClienteCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('cliente_cuentacorriente_reporte.csv', Excel::CSV);
        }

        return redirect()->route(
            'cliente_cuentacorriente_reporte',
            array_merge(ClienteCuentacorrienteReporteFiltros::paraQueryString($filtros), ['consultar' => 1])
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
    private function clientesInicialesParaVista(array $filtros, ?array $resultado): array
    {
        if (! empty($resultado['clientes_resueltos'])) {
            return $resultado['clientes_resueltos'];
        }

        if (($filtros['alcance_clientes'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES) {
            return ClienteCuentacorrienteReporteClienteSupport::etiquetasPorIds($filtros['cliente_ids'] ?? []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function vendedoresInicialesParaVista(array $filtros, ?array $resultado): array
    {
        if (! empty($resultado['vendedores_resueltos'])) {
            return $resultado['vendedores_resueltos'];
        }

        if (($filtros['alcance_vendedores'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES) {
            return ClienteCuentacorrienteReporteVendedorSupport::etiquetasPorIds($filtros['vendedor_ids'] ?? []);
        }

        return [];
    }
}
