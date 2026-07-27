<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\SicoreListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\Sicore\SicoreLiquidacionService;
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
        private readonly SicoreLiquidacionService $liquidacionService,
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

            $resultado = $this->reporteService->generarOCache($filtros);
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

        $resultado = $this->reporteService->generarOCache($filtros);
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

    public function listar(Request $request, ?string $formato = null)
    {
        can('exportar-sicore');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SicoreListadoFiltros::resolverDesdeRequest($request);
        if (! SicoreListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('sicore');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $registros = $resultado['registros'] ?? [];
        $totales = $resultado['totales'] ?? [];
        $conciliacion = $resultado['conciliacion'] ?? [];

        $empresa = $this->empresaRepository->allFiltrado()
            ->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];

        $procesoLabel = SicoreListadoFiltros::CRITERIOS[(string) ($filtros['criterio'] ?? '')] ?? 'SICORE';
        $periodo = SicoreListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = 'SICORE — '.$procesoLabel;
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('contable.sicore.listado', [
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
                $nombrePdf = 'listado_sicore_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new SicoreListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_sicore.xlsx');

            case 'CSV':
                return (new SicoreListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_sicore.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('sicore', array_merge(
            SicoreListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * Liquidación quincenal: cuadro + PDF combinado (liquidación + compras + sueldos).
     * Rangos: compras_fecha_* y sueldos_fecha_* (el modal completa el que falta).
     */
    public function liquidacion(Request $request)
    {
        can('exportar-sicore');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SicoreListadoFiltros::resolverDesdeRequest($request);
        $filtros['compras_fecha_desde'] = (string) $request->input('compras_fecha_desde', '');
        $filtros['compras_fecha_hasta'] = (string) $request->input('compras_fecha_hasta', '');
        $filtros['sueldos_fecha_desde'] = (string) $request->input('sueldos_fecha_desde', '');
        $filtros['sueldos_fecha_hasta'] = (string) $request->input('sueldos_fecha_hasta', '');

        $rangos = \App\Support\Contable\Sicore\SicoreLiquidacionQuincenasSupport::resolverRangosLiquidacion($filtros);
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0
            || $rangos['compras_desde'] === '' || $rangos['compras_hasta'] === ''
            || $rangos['sueldos_desde'] === '' || $rangos['sueldos_hasta'] === '') {
            return $this->respuestaLiquidacionEnIframe(
                route('sicore'),
                'Indique empresa y los rangos de Compras y Sueldos (4ta categoría) para la liquidación.',
            );
        }

        $filtros['compras_fecha_desde'] = $rangos['compras_desde'];
        $filtros['compras_fecha_hasta'] = $rangos['compras_hasta'];
        $filtros['sueldos_fecha_desde'] = $rangos['sueldos_desde'];
        $filtros['sueldos_fecha_hasta'] = $rangos['sueldos_hasta'];
        $filtros['fecha_desde'] = $rangos['compras_desde'];
        $filtros['fecha_hasta'] = $rangos['compras_hasta'];

        try {
            $resultado = $this->liquidacionService->generarCompleta($filtros);
        } catch (\Throwable $e) {
            report($e);

            return $this->respuestaLiquidacionEnIframe(
                route('sicore', array_merge(
                    SicoreListadoFiltros::paraQueryString($filtros),
                    ['consultar' => 1],
                )),
                'No se pudo generar la liquidación: '.$e->getMessage(),
            );
        }

        $rutaPdf = (string) ($resultado['ruta_pdf'] ?? '');
        if ($rutaPdf === '' || ! is_file($rutaPdf)) {
            return $this->respuestaLiquidacionEnIframe(
                route('sicore'),
                'La liquidación no generó el PDF combinado.',
            );
        }

        return response()->download(
            $rutaPdf,
            (string) ($resultado['nombre_pdf'] ?? 'SICORE - Liquidacion completa.pdf'),
        )->deleteFileAfterSend(true);
    }

    /**
     * Si la liquidación corre en iframe, un redirect HTML queda invisible.
     * Devolvemos una página mínima que lleva el mensaje a la ventana padre.
     */
    private function respuestaLiquidacionEnIframe(string $urlDestino, string $mensajeError)
    {
        session()->flash('mensaje-error', $mensajeError);
        $urlJs = json_encode($urlDestino);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>SICORE</title></head><body>'
            .'<script>try{if(window.parent&&window.parent!==window){window.parent.location='.$urlJs.';}'
            .'else{window.location='.$urlJs.';}}catch(e){window.location='.$urlJs.';}</script>'
            .'Error: '.e($mensajeError)
            .'</body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
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
