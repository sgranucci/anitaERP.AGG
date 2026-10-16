<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProveedorControlGananciasReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorControlGananciasReporteService;
use App\Support\Compras\ProveedorControlGananciasLoteSupport;
use App\Support\Compras\ProveedorControlGananciasReporteFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ProveedorControlGananciasReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'proveedor_control_ganancias_reporte';

    public function __construct(
        private readonly ProveedorControlGananciasReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-proveedor-control-ganancias-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProveedorControlGananciasReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);
        $filtros = $this->enriquecerProveedor($filtros);

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];
        $lote = null;

        if ($request->boolean('consultar') && ProveedorControlGananciasReporteFiltros::tieneCriteriosAplicados($filtros)) {
            if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
                ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                    'empresa_id' => (int) $filtros['empresa_id'],
                ]);
            }

            if (ProveedorControlGananciasLoteSupport::debeSegundoPlano($filtros)) {
                $jobId = ProveedorControlGananciasLoteSupport::id($filtros, (int) auth()->id());
                $lote = ProveedorControlGananciasLoteSupport::asegurar($jobId, $filtros);
                $lote['id'] = $jobId;
                if (($lote['estado'] ?? '') === 'listo' && is_array($lote['resultado'] ?? null)) {
                    $resultado = $lote['resultado'];
                }
            } else {
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '0');
                set_time_limit(0);
                $resultado = $this->reporteService->generar($filtros);
            }

            if (is_array($resultado)) {
                $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
                $page = max(1, (int) $request->input('page', 1));
                $filas = $this->reporteService->paginarFilas($resultado['filas'] ?? [], $perPage, $page);
                $filasVista = $filas->items();
                $consultado = true;
            }
        }

        $filtrosQuery = ProveedorControlGananciasReporteFiltros::paraQueryString($filtros);
        if ($consultado || $lote !== null) {
            $filtrosQuery['consultar'] = 1;
        }
        if (is_array($lote) && ! empty($lote['id'])) {
            $filtrosQuery['lote'] = $lote['id'];
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.proveedor_control_ganancias_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'segmento_enum' => ProveedorControlGananciasReporteFiltros::SEGMENTOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'subtitulo' => ProveedorControlGananciasReporteFiltros::formatearSubtitulo($filtros),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'lote' => $lote,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-proveedor-control-ganancias-reporte');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProveedorControlGananciasReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if (! ProveedorControlGananciasReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('proveedor_control_ganancias_reporte');
        }

        $resultado = $this->resultadoParaExportar($filtros);
        if ($resultado === null) {
            return redirect()->route(
                'proveedor_control_ganancias_reporte',
                array_merge(ProveedorControlGananciasReporteFiltros::paraQueryString($filtros), ['consultar' => 1])
            )->with('mensaje-aviso', 'El control todavía se está generando. Esperá a que termine y volvé a exportar.');
        }

        $titulo = 'Control de ganancias — proveedores';
        $subtitulo = ProveedorControlGananciasReporteFiltros::formatearSubtitulo($filtros);
        $filas = $resultado['filas'] ?? [];

        return match (strtoupper($formato)) {
            'EXCEL' => (new ProveedorControlGananciasReporteExport($filas, $titulo, $subtitulo, $resultado['stats'] ?? []))
                ->download('control_ganancias_proveedores.xlsx'),
            'CSV' => (new ProveedorControlGananciasReporteExport($filas, $titulo, $subtitulo, $resultado['stats'] ?? []))
                ->download('control_ganancias_proveedores.csv', Excel::CSV),
            'PDF' => $this->exportarPdf($filas, $titulo, $subtitulo, $resultado['stats'] ?? []),
            default => redirect()->route(
                'proveedor_control_ganancias_reporte',
                ProveedorControlGananciasReporteFiltros::paraQueryString($filtros)
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function resultadoParaExportar(array $filtros): ?array
    {
        if (ProveedorControlGananciasLoteSupport::debeSegundoPlano($filtros)) {
            $job = ProveedorControlGananciasLoteSupport::leer(
                ProveedorControlGananciasLoteSupport::id($filtros, (int) auth()->id())
            );
            if (($job['estado'] ?? '') === 'listo' && is_array($job['resultado'] ?? null)) {
                return $job['resultado'];
            }

            return null;
        }

        set_time_limit(0);

        return $this->reporteService->generar($filtros);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $stats
     */
    private function exportarPdf(array $filas, string $titulo, string $subtitulo, array $stats)
    {
        $view = \View::make('compras.proveedor_control_ganancias_reporte.listado', [
            'filas' => $filas,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'stats' => $stats,
            'para_pdf' => true,
            'puede_ver_proveedor' => false,
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
        $nombrePdf = 'control_ganancias_proveedores_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $preferida = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($preferida !== null && in_array($preferida, $permitidos, true)) {
                $filtros['empresa_id'] = $preferida;
            } elseif ($empresaQuery->count() === 1) {
                $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) > 0 && ! in_array((int) $filtros['empresa_id'], $permitidos, true)) {
            $filtros['empresa_id'] = 0;
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function enriquecerProveedor(array $filtros): array
    {
        $id = (int) ($filtros['proveedor_id'] ?? 0);
        if ($id <= 0) {
            return $filtros;
        }

        $prov = Proveedor::query()->whereKey($id)->first(['id', 'codigo', 'nombre']);
        if ($prov) {
            $filtros['proveedor_codigo'] = (string) $prov->codigo;
            $filtros['proveedor_nombre'] = (string) $prov->nombre;
        }

        return $filtros;
    }
}
