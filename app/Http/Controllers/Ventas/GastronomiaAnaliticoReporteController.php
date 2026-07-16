<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaAnaliticoReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaAnaliticoReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\GastronomiaAnaliticoReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class GastronomiaAnaliticoReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/reportes';

    private const PREFERENCIAS_CLAVE = 'gastronomia_analitico_reporte';

    private const PER_PAGE = 25;

    public function __construct(
        private readonly GastronomiaAnaliticoReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaAnaliticoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas(GastronomiaAnaliticoReporteFiltros::empresaIds($filtros));

        $consultado = $request->boolean('consultar')
            && GastronomiaAnaliticoReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => GastronomiaAnaliticoReporteFiltros::empresaIds($filtros),
                'consolidar_empresas' => ! empty($filtros['consolidar_empresas']),
            ]);

            $perPage = max(10, min(200, (int) $request->input('per_page', self::PER_PAGE)));
            $resultado = $this->reporteService->generar($filtros, true, $perPage);
            $filas = $resultado['filas'];
        }

        $filtrosQuery = GastronomiaAnaliticoReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.analitico_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => GastronomiaAnaliticoReporteFiltros::CAMPOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'periodo_texto' => GastronomiaAnaliticoReporteFiltros::formatearPeriodoTexto($filtros),
            'empresa_texto' => $this->etiquetaEmpresas(
                GastronomiaAnaliticoReporteFiltros::empresaIds($filtros),
                $empresaQuery,
                ! empty($filtros['consolidar_empresas']),
            ),
            'tiene_filtros_texto' => GastronomiaAnaliticoReporteFiltros::tieneFiltrosTextoAplicados($filtros),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_mozo' => can('editar-mozo-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_descuento' => can('editar-descuento-gastronomia', false),
            'puede_ver_puntoventa' => can('editar-puntos-de-venta', false) || can('listar-puntos-de-venta', false),
            'puede_ver_categoria' => can('editar-categorias', false) || can('listar-categorias', false),
            'puede_ver_tipotransaccion' => can('editar-tipos-transacciones', false) || can('listar-tipos-transacciones', false),
            'puede_ver_empresa' => can('editar-empresas', false) || can('listar-empresas', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertAccesoMenu();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaAnaliticoReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas(GastronomiaAnaliticoReporteFiltros::empresaIds($filtros));

        if (! $request->boolean('consultar')
            || ! GastronomiaAnaliticoReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()
                ->route('gastronomia_analitico_reporte', GastronomiaAnaliticoReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consulte el reporte antes de exportar.']);
        }

        $empresaIds = GastronomiaAnaliticoReporteFiltros::empresaIds($filtros);
        $consolidar = ! empty($filtros['consolidar_empresas']) || count($empresaIds) <= 1;
        $empresaTexto = $this->etiquetaEmpresas($empresaIds, $empresaQuery, $consolidar);
        $titulo = 'Reporte analítico gastronomía';

        if (strtoupper($formato) === 'PDF'
            && ! $consolidar
            && count($empresaIds) > 1
        ) {
            return $this->descargarPdfPorEmpresa($filtros, $empresaQuery, $titulo);
        }

        $resultado = $this->reporteService->generar($filtros, false);
        $filas = $resultado['filas'];
        $filasDatos = $filas->filter(static fn ($f) => ($f->tipo_fila ?? 'detalle') !== 'header_empresa');
        if ($filasDatos->isEmpty()) {
            return redirect()
                ->route('gastronomia_analitico_reporte', array_merge(
                    GastronomiaAnaliticoReporteFiltros::paraQueryString($filtros),
                    ['consultar' => 1],
                ))
                ->with('errores', ['No hay movimientos para los filtros aplicados.']);
        }

        $subtitulo = 'Empresa: '.$empresaTexto
            .' · '.$resultado['periodo_texto']
            .' · Lista costo '.$resultado['lista_costo']
            .' · Filas: '.$resultado['totales']['cantidad_filas'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.analitico_reporte.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                    'puede_ver_articulo' => false,
                    'puede_ver_mozo' => false,
                    'puede_ver_factura' => false,
                    'puede_ver_cliente' => false,
                    'puede_ver_descuento' => false,
                    'puede_ver_puntoventa' => false,
                    'puede_ver_categoria' => false,
                    'puede_ver_tipotransaccion' => false,
                    'puede_ver_empresa' => false,
                ])->render();

                return $this->descargarPdf($view, 'analitico_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new GastronomiaAnaliticoReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('analitico_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaAnaliticoReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('analitico_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_analitico_reporte',
            GastronomiaAnaliticoReporteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        if (GastronomiaAnaliticoReporteFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            } else {
                $legacy = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($legacy !== null && in_array($legacy, $permitidos, true)) {
                    $filtros['empresa_ids'] = [$legacy];
                }
            }
        }

        if (GastronomiaAnaliticoReporteFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            GastronomiaAnaliticoReporteFiltros::empresaIds($filtros),
            $permitidos,
        );
        $filtros['empresa_id'] = (int) (GastronomiaAnaliticoReporteFiltros::empresaIds($filtros)[0] ?? 0);

        if (count(GastronomiaAnaliticoReporteFiltros::empresaIds($filtros)) <= 1) {
            $filtros['consolidar_empresas'] = true;
        }

        if (($filtros['modo_periodo'] ?? '') === GastronomiaAnaliticoReporteFiltros::PERIODO_MES) {
            if ((int) ($filtros['anio'] ?? 0) <= 0) {
                $filtros['anio'] = (int) date('Y');
            }
            if ((int) ($filtros['mes'] ?? 0) <= 0) {
                $filtros['mes'] = (int) date('n');
            }
            $inicio = Carbon::create((int) $filtros['anio'], (int) $filtros['mes'], 1)->startOfMonth();
            $filtros['fecha_desde'] = $inicio->format('Y-m-d');
            $filtros['fecha_hasta'] = $inicio->copy()->endOfMonth()->format('Y-m-d');
        } elseif (($filtros['fecha_desde'] ?? '') === '' && ($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
            $filtros['anio'] = (int) Carbon::today()->format('Y');
            $filtros['mes'] = (int) Carbon::today()->format('n');
        }

        [$desde, $hasta] = GastronomiaAnaliticoReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        return $filtros;
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function assertAccesoEmpresas(array $empresaIds): void
    {
        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            if (! $this->empresaRepository->empresaIdPermitida((int) $empresaId)) {
                abort(403, 'No tiene acceso a la empresa seleccionada.');
            }
        }
    }

    private function assertAccesoMenu(): void
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        $rolId = (int) session()->get('rol_id');
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            abort(403, 'Reporte no disponible.');
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            abort(403, 'No tiene acceso a este reporte.');
        }
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function etiquetaEmpresas(array $empresaIds, $empresaQuery, bool $consolidar): string
    {
        if ($empresaIds === []) {
            return '—';
        }

        $nombres = [];
        foreach ($empresaIds as $empresaId) {
            $nombre = $empresaQuery->firstWhere('id', $empresaId)?->nombre;
            $nombres[] = $nombre !== null && trim((string) $nombre) !== ''
                ? trim((string) $nombre)
                : (string) $empresaId;
        }

        if (count($nombres) === 1) {
            return $nombres[0];
        }

        $lista = implode(', ', $nombres);

        return $consolidar ? $lista.' (consolidado)' : $lista.' (por empresa)';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function descargarPdfPorEmpresa(array $filtros, $empresaQuery, string $titulo)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $temporales = [];

        try {
            foreach (GastronomiaAnaliticoReporteFiltros::empresaIds($filtros) as $empresaId) {
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [(int) $empresaId],
                    'empresa_id' => (int) $empresaId,
                    'consolidar_empresas' => true,
                ]);

                $resultado = $this->reporteService->generar($filtrosEmpresa, false);
                $filas = $resultado['filas'];
                if ($filas->isEmpty()) {
                    continue;
                }

                $empresaTexto = $this->etiquetaEmpresas([(int) $empresaId], $empresaQuery, true);
                $subtitulo = 'Empresa: '.$empresaTexto
                    .' · '.$resultado['periodo_texto']
                    .' · Lista costo '.$resultado['lista_costo']
                    .' · Filas: '.$resultado['totales']['cantidad_filas'];

                $view = \View::make('ventas.gastronomia.analitico_reporte.listado', [
                    'filas' => $filas,
                    'filtros' => $filtrosEmpresa,
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                    'puede_ver_articulo' => false,
                    'puede_ver_mozo' => false,
                    'puede_ver_factura' => false,
                    'puede_ver_cliente' => false,
                    'puede_ver_descuento' => false,
                    'puede_ver_puntoventa' => false,
                    'puede_ver_categoria' => false,
                    'puede_ver_tipotransaccion' => false,
                    'puede_ver_empresa' => false,
                ])->render();

                $temp = $dir.'/analitico_gastro_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            if ($temporales === []) {
                return redirect()
                    ->route('gastronomia_analitico_reporte', array_merge(
                        GastronomiaAnaliticoReporteFiltros::paraQueryString($filtros),
                        ['consultar' => 1],
                    ))
                    ->with('errores', ['No hay movimientos para los filtros aplicados.']);
            }

            $nombrePdf = 'analitico_gastronomia_'.date('Ymd_His').'_'.uniqid('', true);
            $destino = $dir.'/'.$nombrePdf.'.pdf';

            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
                $temporales = [];
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
