<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Canon_Municipal_Config;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CanonMunicipal\CanonMunicipalReporteService;
use App\Services\Contable\CanonMunicipal\CanonMunicipalSolicitudpagoService;
use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalFichaSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use RuntimeException;

class CanonMunicipalReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'canon_municipal';

    public function __construct(
        private readonly CanonMunicipalReporteService $reporteService,
        private readonly CanonMunicipalSolicitudpagoService $solicitudpagoService,
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

            $resultado = $this->reporteService->generar($filtros);
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

        $configSolicitudCompleta = false;
        $mensajeConfigSolicitud = null;
        if ($consultado && $resultado && empty($resultado['mensaje_config'])) {
            $empresaIdCfg = (int) ($filtros['empresa_id'] ?? 0);
            $cfgSp = Canon_Municipal_Config::query()
                ->where('empresa_id', $empresaIdCfg)
                ->where('activo', true)
                ->first();
            $configSolicitudCompleta = $cfgSp !== null && $cfgSp->configCompletaParaSolicitud();
            if (! $configSolicitudCompleta) {
                $nombreEmp = (string) ($resultado['ficha']['nombre'] ?? 'la empresa');
                $mensajeConfigSolicitud = 'Falta configurar el canon de '.$nombreEmp;
            }
        }

        return view('contable.canon_municipal.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'liquidaciones_enum' => $liquidacionesEnum,
            'mapa_config_empresas' => $mapaConfig,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => CanonMunicipalListadoFiltros::formatearPeriodoTexto($filtros),
            'config_solicitud_completa' => $configSolicitudCompleta,
            'mensaje_config_solicitud' => $mensajeConfigSolicitud,
        ]);
    }

    public function previewSolicitudPago(Request $request)
    {
        if (! can('exportar-canon-municipal', false) || ! can('crear-solicitud-pago', false)) {
            return response()->json(['error' => 'Sin permiso.'], 403);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        try {
            $filtros = $this->resolverFiltrosCanon($request);
            $preview = $this->solicitudpagoService->preview($filtros);

            return response()->json($preview);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'puede_generar' => false], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo armar la vista previa.', 'puede_generar' => false], 500);
        }
    }

    public function generarSolicitudPago(Request $request)
    {
        can('exportar-canon-municipal');
        if (! can('crear-solicitud-pago', false)) {
            can('crear-solicitud-pago');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $validated = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'periodo' => ['required', 'string'],
            'liquidacion' => ['required', 'integer', 'min:1'],
            'fecha_vencimiento' => ['required', 'string'],
            'observacion' => ['nullable', 'string', 'max:160'],
            'generar_igual' => ['nullable', 'boolean'],
            'confirmo_dias' => ['nullable', 'boolean'],
        ], [], [
            'fecha_vencimiento' => 'vencimiento',
        ]);

        $filtros = $this->resolverFiltrosCanon($request);
        $redirectQuery = array_merge(
            CanonMunicipalListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1]
        );

        try {
            $resultado = $this->solicitudpagoService->confirmar(
                $filtros,
                (string) $validated['fecha_vencimiento'],
                isset($validated['observacion']) ? (string) $validated['observacion'] : null,
                $request->boolean('generar_igual'),
                $request->boolean('confirmo_dias'),
            );
            $codigo = (int) $resultado['codigo'];

            return redirect()
                ->route('canon_municipal', $redirectQuery)
                ->with(
                    'mensaje',
                    'Solicitud de pago #'.$codigo.' generada, con la nota adjunta y enviada a aprobación'
                );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('canon_municipal', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('canon_municipal', $redirectQuery)
                ->with('mensaje_error', 'No se pudo generar la solicitud de pago.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosCanon(Request $request): array
    {
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

        return $filtros;
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

        $resultado = $this->reporteService->generar($filtros);
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

        $resultado = $this->reporteService->generar($filtros);
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
