<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\FacturacionCobranzasListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Transporte;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Ventas\FacturacionCobranzasReporteService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\FacturacionCobranzasListadoFiltros;
use App\Support\Ventas\FacturacionCobranzasReporteCacheSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FacturacionCobranzasReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'facturacion_cobranzas';

    public function __construct(
        private readonly FacturacionCobranzasReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertEntornoElBierzo();
        can('listar-facturacion-cobranzas');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FacturacionCobranzasListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar') && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) $filtros['empresa_id'],
            ]);
            ReportePreferenciasUsuario::persistirString(
                self::PREFERENCIAS_CLAVE,
                'orden',
                FacturacionCobranzasListadoFiltros::normalizarOrden($filtros['orden'] ?? null),
            );
            ReportePreferenciasUsuario::persistirString(
                self::PREFERENCIAS_CLAVE,
                'fuente',
                FacturacionCobranzasListadoFiltros::normalizarFuente($filtros['fuente'] ?? null),
            );
        }

        $consultado = false;
        $filas = null;
        $filasVista = [];
        $totales = null;
        $errores = [];
        $avisos = [];

        if ($request->boolean('consultar') && FacturacionCobranzasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->resultadoConCache($filtros, $request->boolean('refrescar'));
            $totales = $resultado['totales'];
            $errores = $resultado['errores'] ?? [];
            $avisos = $resultado['avisos'] ?? [];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = FacturacionCobranzasListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $this->completarNombresReparto($filtros);

        return view('ventas.facturacion_cobranzas.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'moneda_query' => $this->monedaRepository->all(),
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'errores' => $errores,
            'avisos' => $avisos,
            'periodo_texto' => FacturacionCobranzasListadoFiltros::formatearPeriodoTexto($filtros),
            'orden_texto' => FacturacionCobranzasListadoFiltros::formatearOrdenTexto($filtros),
            'fuente_texto' => FacturacionCobranzasListadoFiltros::formatearFuenteTexto($filtros),
            'reparto_texto' => FacturacionCobranzasListadoFiltros::formatearRepartoTexto($filtros),
            'repartos_iniciales' => $this->metaRepartosParticulares($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'puede_ver_venta' => can('editar-factura', false) || can('listar-factura', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_cobranza' => can('editar-cobranza', false) || can('listar-cobranza', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertEntornoElBierzo();
        can('listar-facturacion-cobranzas');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FacturacionCobranzasListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! FacturacionCobranzasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('facturacion_cobranzas');
        }

        $resultado = $this->resultadoConCache($filtros, false);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $titulo = 'Facturación y cobranzas';
        $subtitulo = $this->reporteService->armarSubtitulo($filtros, $empresaTexto);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.facturacion_cobranzas.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_venta' => false,
                    'puede_ver_cliente' => false,
                    'puede_ver_cobranza' => false,
                    'para_pdf' => true,
                ])->render();

                return $this->descargarPdfListado($view, 'facturacion_cobranzas', 'legal', 'landscape');

            case 'EXCEL':
                return (new FacturacionCobranzasListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('facturacion_cobranzas.xlsx');

            case 'CSV':
                return (new FacturacionCobranzasListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('facturacion_cobranzas.csv', Excel::CSV);
        }

        return redirect()->route(
            'facturacion_cobranzas',
            array_merge(FacturacionCobranzasListadoFiltros::paraQueryString($filtros), ['consultar' => 1]),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $preferida = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($preferida !== null && $this->empresaRepository->empresaIdPermitida($preferida)) {
                $filtros['empresa_id'] = $preferida;
            } elseif ($empresaQuery->count() === 1) {
                $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
            }
        }

        if (($filtros['fecha_desde'] ?? '') === '') {
            $filtros['fecha_desde'] = date('Y-m-d');
        }
        if (($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }

        [$desde, $hasta] = FacturacionCobranzasListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        if (! request()->filled('orden')) {
            $filtros['orden'] = FacturacionCobranzasListadoFiltros::normalizarOrden(
                ReportePreferenciasUsuario::leerString(
                    self::PREFERENCIAS_CLAVE,
                    'orden',
                    FacturacionCobranzasListadoFiltros::ORDEN_COMPROBANTE,
                ),
            );
        }
        if (! request()->filled('fuente')) {
            $filtros['fuente'] = FacturacionCobranzasListadoFiltros::normalizarFuente(
                ReportePreferenciasUsuario::leerString(
                    self::PREFERENCIAS_CLAVE,
                    'fuente',
                    FacturacionCobranzasListadoFiltros::FUENTE_AMBOS,
                ),
            );
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function completarNombresReparto(array &$filtros): void
    {
        foreach (['reparto_desde', 'reparto_hasta'] as $campo) {
            $codigo = trim((string) ($filtros[$campo] ?? ''));
            $nombreKey = $campo.'_nombre';
            if ($codigo === '' || $codigo === '999999') {
                $filtros[$nombreKey] = '';
                continue;
            }
            if (($filtros[$nombreKey] ?? '') !== '') {
                continue;
            }
            $tr = Transporte::query()->where('codigo', $codigo)->first(['nombre']);
            $filtros[$nombreKey] = (string) ($tr->nombre ?? '');
        }

        $codigos = FacturacionCobranzasListadoFiltros::codigosRepartoParticulares($filtros);
        if ($codigos === []) {
            $filtros['repartos_nombres'] = [];

            return;
        }

        $nombres = is_array($filtros['repartos_nombres'] ?? null) ? $filtros['repartos_nombres'] : [];
        $faltan = [];
        foreach ($codigos as $codigo) {
            if (trim((string) ($nombres[$codigo] ?? '')) === '') {
                $faltan[] = $codigo;
            }
        }
        if ($faltan !== []) {
            $encontrados = Transporte::query()
                ->whereIn('codigo', $faltan)
                ->get(['codigo', 'nombre']);
            foreach ($encontrados as $tr) {
                $nombres[(int) $tr->codigo] = (string) ($tr->nombre ?? '');
            }
        }
        $filtros['repartos_nombres'] = [];
        foreach ($codigos as $codigo) {
            $filtros['repartos_nombres'][$codigo] = (string) ($nombres[$codigo] ?? '');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{codigo:int,nombre:string}>
     */
    private function metaRepartosParticulares(array $filtros): array
    {
        $codigos = FacturacionCobranzasListadoFiltros::codigosRepartoParticulares($filtros);
        $nombres = is_array($filtros['repartos_nombres'] ?? null) ? $filtros['repartos_nombres'] : [];
        $out = [];
        foreach ($codigos as $codigo) {
            $out[] = [
                'codigo' => $codigo,
                'nombre' => (string) ($nombres[$codigo] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        $emp = $empresaQuery->firstWhere('id', $empresaId);

        return trim((string) ($emp->nombre ?? ''));
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function assertEntornoElBierzo(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            abort(404);
        }
    }

    private function descargarPdfListado(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * Primera consulta / cambio de filtros: genera. Páginas siguientes y export: sesión.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>, errores?: list<string>, avisos?: list<string>}
     */
    private function resultadoConCache(array $filtros, bool $forzarRefresco): array
    {
        if ($forzarRefresco) {
            FacturacionCobranzasReporteCacheSupport::limpiar();
        }

        $cached = FacturacionCobranzasReporteCacheSupport::recuperar($filtros);
        if ($cached !== null) {
            return $cached;
        }

        $resultado = $this->reporteService->generar($filtros);
        FacturacionCobranzasReporteCacheSupport::guardar($filtros, $resultado);

        return $resultado;
    }
}
