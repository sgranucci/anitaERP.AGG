<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CanonMunicipal\CanonMunicipalReporteService;
use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalFichaSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;

class CanonMunicipalReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'canon_municipal';

    public function __construct(
        private readonly CanonMunicipalReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-canon-municipal');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $mapaConfig = CanonMunicipalFichaSupport::mapaEmpresasActivas();
        // Solo empresas con config activa y asignadas al usuario.
        $empresaQuery = $empresaQuery->filter(
            static fn ($e) => isset($mapaConfig[(int) $e->id])
        )->values();

        $periodicidadHint = null;
        $empresaPrefId = (int) $request->input('empresa_id', 0);
        if ($empresaPrefId <= 0 && ! $request->boolean('consultar')) {
            $empresaPrefId = (int) (ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE) ?? 0);
        }
        if ($empresaPrefId > 0 && isset($mapaConfig[$empresaPrefId])) {
            $periodicidadHint = $mapaConfig[$empresaPrefId]['periodicidad'];
        } elseif ($empresaQuery->count() === 1) {
            $periodicidadHint = $mapaConfig[(int) $empresaQuery->first()->id]['periodicidad'] ?? 'semanal';
        }

        $filtros = CanonMunicipalListadoFiltros::resolverDesdeRequest($request, $periodicidadHint);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery, $mapaConfig);

        // Recalcular rango si la periodicidad real de la empresa difiere del hint.
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && isset($mapaConfig[$empresaId])) {
            $filtros['periodicidad'] = $mapaConfig[$empresaId]['periodicidad'];
            [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango(
                $filtros['periodicidad'],
                (string) $filtros['periodo'],
                (int) $filtros['liquidacion'],
            );
            $filtros['fecha_desde'] = $desde;
            $filtros['fecha_hasta'] = $hasta;
        }

        $consultado = false;
        $resultado = null;

        if ($request->boolean('consultar') && CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);

            $resultado = $this->reporteService->generarOCache($filtros);
            $consultado = true;
        }

        $filtrosQuery = CanonMunicipalListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        $liquidacionesEnum = CanonMunicipalCalendarioSupport::opcionesLiquidacion(
            (string) ($filtros['periodicidad'] ?? 'semanal'),
            (string) ($filtros['periodo'] ?? date('Ym')),
        );

        return view('contable.canon_municipal.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'liquidaciones_enum' => $liquidacionesEnum,
            'mapa_config_empresas' => $mapaConfig,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => CanonMunicipalListadoFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportarNota(Request $request)
    {
        can('exportar-canon-municipal');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $mapaConfig = CanonMunicipalFichaSupport::mapaEmpresasActivas();
        $empresaId = (int) $request->input('empresa_id', 0);
        $periodicidad = $mapaConfig[$empresaId]['periodicidad'] ?? 'semanal';
        $filtros = CanonMunicipalListadoFiltros::resolverDesdeRequest($request, $periodicidad);
        if ($empresaId > 0 && isset($mapaConfig[$empresaId])) {
            $filtros['periodicidad'] = $periodicidad;
            [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango(
                $periodicidad,
                (string) $filtros['periodo'],
                (int) $filtros['liquidacion'],
            );
            $filtros['fecha_desde'] = $desde;
            $filtros['fecha_hasta'] = $hasta;
        }

        if (! CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('canon_municipal');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        if (empty($resultado['puede_emitir_nota'])) {
            return redirect()
                ->route('canon_municipal', array_merge(
                    CanonMunicipalListadoFiltros::paraQueryString($filtros),
                    ['consultar' => 1]
                ))
                ->with('mensaje_error', 'La nota está bloqueada: el cruce Flash × Posición no cuadra.');
        }

        $ficha = $resultado['ficha'] ?? [];
        $view = \View::make('contable.canon_municipal.nota', [
            'resultado' => $resultado,
            'ficha' => $ficha,
            'fecha_nota' => now(),
            'filtros' => $filtros,
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $slug = preg_replace('/\W+/', '_', (string) ($ficha['pie_razon_social'] ?? 'canon')) ?: 'canon';
        $nombrePdf = 'nota_canon_municipal_'.$slug.'_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('exportar-canon-municipal');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $mapaConfig = CanonMunicipalFichaSupport::mapaEmpresasActivas();
        $empresaId = (int) $request->input('empresa_id', 0);
        $periodicidad = $mapaConfig[$empresaId]['periodicidad'] ?? 'semanal';
        $filtros = CanonMunicipalListadoFiltros::resolverDesdeRequest($request, $periodicidad);
        if ($empresaId > 0 && isset($mapaConfig[$empresaId])) {
            $filtros['periodicidad'] = $periodicidad;
            [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango(
                $periodicidad,
                (string) $filtros['periodo'],
                (int) $filtros['liquidacion'],
            );
            $filtros['fecha_desde'] = $desde;
            $filtros['fecha_hasta'] = $hasta;
        }

        if (! CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('canon_municipal');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $ficha = $resultado['ficha'] ?? [];
        $nombreEmpresa = (string) ($ficha['nombre'] ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];
        $periodo = CanonMunicipalListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = 'Canon municipal bingo — conciliación';
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('contable.canon_municipal.listado', [
                    'filasParaLogo' => $filasParaLogo,
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_canon_municipal_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            default:
                return redirect()->route('canon_municipal', CanonMunicipalListadoFiltros::paraQueryString($filtros));
        }
    }

    /**
     * Endpoint JSON: opciones de liquidación según empresa + período.
     */
    public function liquidaciones(Request $request)
    {
        can('listar-canon-municipal');

        $empresaId = (int) $request->input('empresa_id', 0);
        $mapa = CanonMunicipalFichaSupport::mapaEmpresasActivas();
        $periodicidad = $mapa[$empresaId]['periodicidad'] ?? 'semanal';
        $periodoRaw = (string) $request->input('periodo', date('Ym'));
        $periodo = preg_replace('/\D/', '', $periodoRaw) ?? '';
        if (strlen($periodo) !== 6) {
            $periodo = date('Ym');
        }

        $opciones = CanonMunicipalCalendarioSupport::opcionesLiquidacion($periodicidad, $periodo);
        $liquidacion = max(1, (int) $request->input('liquidacion', 1));
        if (! isset($opciones[$liquidacion])) {
            $liquidacion = (int) (array_key_first($opciones) ?? 1);
        }
        [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango($periodicidad, $periodo, $liquidacion);

        return response()->json([
            'periodicidad' => $periodicidad,
            'opciones' => $opciones,
            'liquidacion' => $liquidacion,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<int, array<string, string>>  $mapaConfig
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery, array $mapaConfig): array
    {
        if (! $request->boolean('consultar')) {
            if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
                $empresaPref = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($empresaPref && $empresaQuery->contains('id', $empresaPref) && isset($mapaConfig[$empresaPref])) {
                    $filtros['empresa_id'] = $empresaPref;
                } elseif ($empresaQuery->count() === 1) {
                    $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
                }
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        return $filtros;
    }
}
