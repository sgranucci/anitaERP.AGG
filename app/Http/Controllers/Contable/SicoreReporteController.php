<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\Sicore\SicoreReporteService;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class SicoreReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'sicore';

    public function __construct(
        private readonly SicoreReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-sicore');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = SicoreListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;

        if ($request->boolean('consultar') && SicoreListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
            Cache::forever(
                generaKey(ReportePreferenciasUsuario::clave(self::PREFERENCIAS_CLAVE, 'criterio')),
                (string) ($filtros['criterio'] ?? ''),
            );

            $resultado = $this->reporteService->generar($filtros);
            $consultado = true;
        }

        $filtrosQuery = SicoreListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('contable.sicore.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'criterios_enum' => SicoreListadoFiltros::CRITERIOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => SicoreListadoFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportar(Request $request)
    {
        can('exportar-sicore');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SicoreListadoFiltros::resolverDesdeRequest($request);
        if (! SicoreListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('sicore');
        }

        $resultado = $this->reporteService->generar($filtros);
        $proceso = (string) ($filtros['criterio'] ?? 'sicore');
        $nombre = match ($proceso) {
            SicoreCriteriosSupport::VENTAS => 'vsicore.dat',
            SicoreCriteriosSupport::SUELDOS => 'ssicore.dat',
            default => 'csicore.dat',
        };

        return Response::make($resultado['archivo_v8'] ?? '', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return $filtros;
        }

        $prefs = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
        $empresaPref = $prefs;
        if ($empresaPref > 0 && $empresaQuery->contains('id', $empresaPref)) {
            $filtros['empresa_id'] = $empresaPref;
        } elseif ($empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        $criterioPref = cache()->get(generaKey(ReportePreferenciasUsuario::clave(self::PREFERENCIAS_CLAVE, 'criterio')));
        if (! $request->has('criterio') && is_string($criterioPref) && $criterioPref !== '') {
            $filtros['criterio'] = $criterioPref;
        }

        return $filtros;
    }
}
