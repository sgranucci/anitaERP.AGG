<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CcVsMayorAnitaExport;
use App\Http\Controllers\Controller;
use App\Services\Contable\CcVsMayorAnitaReporteService;
use App\Support\Contable\CcVsMayorAnita\CcVsMayorAnitaListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Excel;
use PDF;

class CcVsMayorAnitaController extends Controller
{
    private const SESSION_CACHE_KEY = 'cc_vs_mayor_anita_resultado_cache';

    public function __construct(
        private readonly CcVsMayorAnitaReporteService $reporteService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cc-vs-mayor-anita');

        $filtros = CcVsMayorAnitaListadoFiltros::resolverDesdeRequest($request);
        $consultado = false;
        $resultado = null;
        $filas = null;

        if ($request->boolean('consultar') && CcVsMayorAnitaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->generarYCachear($filtros);
            $consultado = true;
        } elseif (CcVsMayorAnitaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            $resultado = $this->leerCache($filtros);
            $consultado = $resultado !== null;
        }

        $filtrosQuery = CcVsMayorAnitaListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }

        if ($consultado && $resultado !== null) {
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas($resultado['filas'] ?? [], $perPage, (int) $request->input('page', 1));
            $filas->appends($filtrosQuery);
        }

        return view('contable.cc_vs_mayor_anita.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'resumen' => $resultado['resumen'] ?? [],
            'titulo' => CcVsMayorAnitaReporteService::titulo($filtros),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-cc-vs-mayor-anita');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CcVsMayorAnitaListadoFiltros::resolverDesdeRequest($request);
        if (! CcVsMayorAnitaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('cc_vs_mayor_anita', CcVsMayorAnitaListadoFiltros::paraQueryString($filtros));
        }

        $resultado = $this->leerCache($filtros) ?? $this->generarYCachear($filtros);
        $formato = strtoupper(trim($formato));

        return match ($formato) {
            'PDF' => $this->exportarPdf($resultado, $filtros),
            'EXCEL' => (new CcVsMayorAnitaExport())->parametros($resultado, $filtros)->download('cc_vs_mayor_anita.xlsx'),
            'CSV' => (new CcVsMayorAnitaExport())->parametros($resultado, $filtros)->download('cc_vs_mayor_anita.csv', Excel::CSV),
            default => redirect()->route('cc_vs_mayor_anita', CcVsMayorAnitaListadoFiltros::paraQueryString($filtros)),
        };
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    private function exportarPdf(array $resultado, array $filtros)
    {
        $pdf = PDF::loadView('contable.cc_vs_mayor_anita.listado', [
            'resultado' => $resultado,
            'filtros' => $filtros,
            'filas' => $resultado['filas'] ?? [],
            'resumen' => $resultado['resumen'] ?? [],
            'titulo' => CcVsMayorAnitaReporteService::titulo($filtros),
            'mostrarCabecera' => true,
        ])->setPaper('legal', 'landscape');

        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/listado_cc_vs_mayor_anita.pdf';
        $pdf->save($path);

        return response()->file($path);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarYCachear(array $filtros): array
    {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $firma = CcVsMayorAnitaReporteService::firmaCache($filtros);
        Cache::put($this->cacheKey($firma), $resultado, now()->addMinutes(30));
        session([self::SESSION_CACHE_KEY => $firma]);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    private function leerCache(array $filtros): ?array
    {
        $firma = CcVsMayorAnitaReporteService::firmaCache($filtros);
        $cached = Cache::get($this->cacheKey($firma));

        return is_array($cached) ? $cached : null;
    }

    private function cacheKey(string $firma): string
    {
        return self::SESSION_CACHE_KEY.':'.auth()->id().':'.$firma;
    }
}
