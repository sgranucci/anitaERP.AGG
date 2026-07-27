<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\IngresosBrutosListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Provincia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\IngresosBrutos\IngresosBrutosReporteService;
use App\Support\Contable\IngresosBrutos\IngresosBrutosListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class IngresosBrutosReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'ingresos_brutos';

    public function __construct(
        private readonly IngresosBrutosReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-ingresos-brutos');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = IngresosBrutosListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;

        if ($request->boolean('consultar') && IngresosBrutosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
            \Illuminate\Support\Facades\Cache::forever(
                generaKey(ReportePreferenciasUsuario::clave(self::PREFERENCIAS_CLAVE, 'provincia_id')),
                (string) ((int) ($filtros['provincia_id'] ?? 0)),
            );
            \Illuminate\Support\Facades\Cache::forever(
                generaKey(ReportePreferenciasUsuario::clave(self::PREFERENCIAS_CLAVE, 'tipo')),
                (string) ($filtros['tipo'] ?? ''),
            );

            $resultado = $this->reporteService->generarOCache($filtros);
            $consultado = true;
        }

        $filtrosQuery = IngresosBrutosListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        $provincia = null;
        if ((int) ($filtros['provincia_id'] ?? 0) > 0) {
            $provincia = Provincia::query()->find((int) $filtros['provincia_id']);
        }

        return view('contable.ingresos_brutos.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'tipos_enum' => IngresosBrutosListadoFiltros::TIPOS,
            'liquidaciones_enum' => IngresosBrutosListadoFiltros::LIQUIDACIONES,
            'provincia' => $provincia,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => IngresosBrutosListadoFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportar(Request $request)
    {
        can('exportar-ingresos-brutos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IngresosBrutosListadoFiltros::resolverDesdeRequest($request);
        if (! IngresosBrutosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('ingresos_brutos');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $nombre = (string) ($resultado['nombre_archivo'] ?? 'iibb_arba.txt');

        return Response::make($resultado['archivo_arba'] ?? '', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('exportar-ingresos-brutos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IngresosBrutosListadoFiltros::resolverDesdeRequest($request);
        if (! IngresosBrutosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('ingresos_brutos');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $registros = $resultado['registros'] ?? [];
        $totales = $resultado['totales'] ?? [];
        $conciliacion = $resultado['conciliacion'] ?? [];

        $empresa = $this->empresaRepository->allFiltrado()
            ->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];

        $tipoLabel = IngresosBrutosListadoFiltros::TIPOS[(string) ($filtros['tipo'] ?? '')] ?? 'IIBB';
        $periodo = IngresosBrutosListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = 'Ingresos Brutos — '.$tipoLabel;
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('contable.ingresos_brutos.listado', [
                    'filasParaLogo' => $filasParaLogo,
                    'registros' => $registros,
                    'totales' => $totales,
                    'conciliacion' => $conciliacion,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_ingresos_brutos_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new IngresosBrutosListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_ingresos_brutos_'.date('Ymd_His').'.xlsx');

            case 'CSV':
                return (new IngresosBrutosListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_ingresos_brutos_'.date('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);

            default:
                return redirect()->route('ingresos_brutos', IngresosBrutosListadoFiltros::paraQueryString($filtros));
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery): array
    {
        if (! $request->boolean('consultar')) {
            if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
                $empresaPref = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($empresaPref && $empresaQuery->contains('id', $empresaPref)) {
                    $filtros['empresa_id'] = $empresaPref;
                } elseif ($empresaQuery->count() === 1) {
                    $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
                }
            }
            if ((int) ($filtros['provincia_id'] ?? 0) <= 0) {
                $provPref = (int) ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'provincia_id', '0');
                if ($provPref > 0) {
                    $filtros['provincia_id'] = $provPref;
                }
            }
            $tipoPref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'tipo', '');
            if ($tipoPref !== '' && array_key_exists($tipoPref, IngresosBrutosListadoFiltros::TIPOS)) {
                if (! $request->filled('tipo')) {
                    $filtros['tipo'] = $tipoPref;
                }
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        // Default Buenos Aires si no hay provincia.
        if ((int) ($filtros['provincia_id'] ?? 0) <= 0) {
            $ba = Provincia::query()
                ->where('nombre', 'Buenos Aires')
                ->orWhere('codigoexterno', '2')
                ->orderBy('id')
                ->first();
            if ($ba !== null) {
                $filtros['provincia_id'] = (int) $ba->id;
            }
        }

        return $filtros;
    }
}
