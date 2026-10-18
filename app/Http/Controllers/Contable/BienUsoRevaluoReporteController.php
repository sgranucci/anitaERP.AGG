<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\BienUsoRevaluoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\BienUsoRevaluoInformeService;
use App\Support\Contable\BienUsoRevaluoListadoFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Informe de revalúo contable de bienes de uso (Anita l-biemae.c).
 * Módulo estándar ERP.
 */
class BienUsoRevaluoReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'bien_uso_revaluo';

    private const SESSION_CACHE = 'bien_uso_revaluo_resultado';

    public function __construct(
        private readonly BienUsoRevaluoInformeService $informeService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-bien-uso-revaluo');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = BienUsoRevaluoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;
        $filasPaginadas = null;

        if ($request->boolean('consultar') && BienUsoRevaluoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'empresa_ids' => $filtros['empresa_ids'] ?? [],
            ]);

            $firma = md5(json_encode(BienUsoRevaluoListadoFiltros::paraQueryString($filtros)));
            $cache = session(self::SESSION_CACHE);
            if (is_array($cache) && ($cache['firma'] ?? '') === $firma) {
                $resultado = $cache['resultado'];
            } else {
                $resultado = $this->informeService->generar($filtros);
                session([self::SESSION_CACHE => [
                    'firma' => $firma,
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                ]]);
            }
            $consultado = true;

            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $filas = collect($resultado['filas'] ?? []);
            $filasPaginadas = new LengthAwarePaginator(
                $filas->forPage($page, $perPage)->values(),
                $filas->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $filtrosQuery = BienUsoRevaluoListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('contable.bien_uso_revaluo.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'moneda_query' => $this->monedaRepository->all(),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filasPaginadas,
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-bien-uso-revaluo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = BienUsoRevaluoListadoFiltros::resolverDesdeRequest($request);
        $firma = md5(json_encode(BienUsoRevaluoListadoFiltros::paraQueryString($filtros)));
        $cache = session(self::SESSION_CACHE);
        if (is_array($cache) && ($cache['firma'] ?? '') === $firma) {
            $resultado = $cache['resultado'];
        } else {
            $resultado = $this->informeService->generar($filtros);
        }

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $html = view('contable.bien_uso_revaluo.listado', [
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                ])->render();
                $ruta = storage_path('pdf/listados/revaluo_bien_uso.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Revalúo bienes de uso',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new BienUsoRevaluoExport($resultado, $filtros))
                    ->download('revaluo_bien_uso.xlsx');

            case 'CSV':
                return (new BienUsoRevaluoExport($resultado, $filtros))
                    ->download('revaluo_bien_uso.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('bien_uso_revaluo', BienUsoRevaluoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery): array
    {
        if ($request->boolean('consultar')) {
            return $filtros;
        }

        if (empty($filtros['empresa_ids']) && empty($filtros['empresa_id'])) {
            $ids = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE) ?? [];
            if ($ids === []) {
                $eid = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($eid) {
                    $ids = [(int) $eid];
                }
            }
            $permitidas = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_intersect(array_map('intval', (array) $ids), $permitidas));
            if ($ids === [] && $empresaQuery->count() === 1) {
                $ids = [(int) $empresaQuery->first()->id];
            }
            $filtros['empresa_ids'] = $ids;
            $filtros['empresa_id'] = $ids[0] ?? null;
        }

        return $filtros;
    }
}
