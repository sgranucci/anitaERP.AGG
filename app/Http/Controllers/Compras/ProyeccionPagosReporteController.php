<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProyeccionPagosReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProyeccionPagosReporteService;
use App\Support\Compras\ProyeccionPagosColumnasSupport;
use App\Support\Compras\ProyeccionPagosReporteFiltros;
use App\Support\Compras\ProyeccionPagosTramosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class ProyeccionPagosReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'proyeccion_pagos_compras';

    private const PERMISO = 'listar-reporte-proyeccion-pagos';

    public function __construct(
        private ProyeccionPagosReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can(self::PERMISO);

        ini_set('memory_limit', '768M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProyeccionPagosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && ProyeccionPagosReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($consultado) {
            $this->persistirPreferencias($filtros);
            $resultado = $this->service->generar($filtros);
            $perPage = max(25, min(500, (int) $request->input('per_page', 100)));
            $filas = $this->service->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
        } else {
            $resultado = $this->estructuraVacia($filtros);
        }

        $filtrosQuery = ProyeccionPagosReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.proyeccion_pagos_reporte.index', [
            'empresa_query' => $empresaQuery,
            'moneda_query' => DB::table('moneda')->orderBy('id')->get(),
            'tipotransaccion_query' => DB::table('tipotransaccion_compra')
                ->whereNull('deleted_at')
                ->orderBy('nombre')
                ->get(),
            'opciones_informe' => ProyeccionPagosReporteFiltros::OPCIONES_INFORME,
            'opciones_vencimiento' => ProyeccionPagosReporteFiltros::OPCIONES_VENCIMIENTO,
            'opciones_moneda' => ProyeccionPagosReporteFiltros::OPCIONES_MONEDA,
            'opciones_salida' => ProyeccionPagosReporteFiltros::OPCIONES_SALIDA,
            'opciones_aprobacion' => ProyeccionPagosReporteFiltros::OPCIONES_APROBACION,
            'opciones_agrupacion' => ProyeccionPagosReporteFiltros::OPCIONES_AGRUPACION,
            'opciones_orden' => ProyeccionPagosReporteFiltros::OPCIONES_ORDEN,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'columnas' => $resultado['columnas'],
            'panel_columnas' => ProyeccionPagosColumnasSupport::panelConfiguracion(
                $resultado['catalogo'],
                (string) ($filtros['columnas'] ?? ''),
                (string) $filtros['salida'],
            ),
            'filas' => $filas,
            'filasVista' => $filasVista,
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'puede_ver_proveedor' => can('editar-proveedores', false) || can('listar-proveedores', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false)
                || can('listar-comprobante-proveedor', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_requisicion' => can('editar-requisicion', false) || can('listar-requisicion', false),
            'puede_ver_concepto' => can('editar-conceptos-de-gastos', false)
                || can('listar-conceptos-de-gastos', false),
            'puede_ver_cuentacontable' => can('editar-cuentas-contables', false)
                || can('listar-cuentas-contables', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can(self::PERMISO);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ProyeccionPagosReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! ProyeccionPagosReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_proyeccion_pagos');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Proyección de pagos a proveedores';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $columnas = $resultado['columnas'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado, $empresaQuery, $titulo);
                }

                return $this->descargarPdf(
                    $this->renderizarPdf($filas, $totales, $columnas, $titulo, $subtitulo),
                    'proyeccion_pagos_'.date('Ymd_His'),
                );

            case 'EXCEL':
                return (new ProyeccionPagosReporteExport($filas, $columnas, $titulo, $subtitulo, $totales))
                    ->download('proyeccion_pagos.xlsx');

            case 'CSV':
                return (new ProyeccionPagosReporteExport($filas, $columnas, $titulo, $subtitulo, $totales))
                    ->download('proyeccion_pagos.csv', Excel::CSV);
        }

        return redirect()->route('reporte_proyeccion_pagos', ProyeccionPagosReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     * @param  list<array<string, mixed>>  $columnas
     */
    private function renderizarPdf(array $filas, array $totales, array $columnas, string $titulo, string $subtitulo): string
    {
        return \View::make('compras.proyeccion_pagos_reporte.listado', [
            'filas' => $filas,
            'totales' => $totales,
            'columnas' => $columnas,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
        ])->render();
    }

    private function descargarPdf(string $html, string $nombre)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($path.'/'.$nombre.'.pdf');

        return response()->download($path.'/'.$nombre.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoCompleto
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    private function descargarPdfPorEmpresa(array $filtros, array $resultadoCompleto, $empresaQuery, string $titulo)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];

        try {
            foreach ($resultadoCompleto['secciones'] ?? [] as $seccion) {
                $empresaId = (int) ($seccion['empresa_id'] ?? 0);
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'consolidar_empresas' => true,
                ]);
                $subtitulo = ((string) ($seccion['empresa_nombre'] ?? ''))
                    .' · '.$this->service->subtituloFiltros($filtrosEmpresa, $empresaQuery);

                $html = $this->renderizarPdf(
                    $seccion['filas'] ?? [],
                    $seccion['totales'] ?? [],
                    $resultadoCompleto['columnas'] ?? [],
                    $titulo,
                    $subtitulo,
                );

                $temp = $dir.'/proyeccion_pagos_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($html, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $nombreBase = 'proyeccion_pagos_'.date('Ymd_His');
            $destino = $dir.'/'.$nombreBase.'.pdf';

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

            return response()->download($destino, $nombreBase.'.pdf')->deleteFileAfterSend(true);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    /**
     * Catálogo y columnas visibles sin consultar (pantalla inicial y panel de configuración).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function estructuraVacia(array $filtros): array
    {
        $tramos = ProyeccionPagosTramosSupport::construir($filtros);
        $catalogo = ProyeccionPagosColumnasSupport::catalogoConTramos($tramos);

        return [
            'filas' => [],
            'totales' => ['cantidad' => 0, 'proveedores' => 0, 'importes' => [], 'total_adeudado' => 0.0],
            'columnas' => ProyeccionPagosColumnasSupport::resolverVisibles(
                $catalogo,
                (string) ($filtros['columnas'] ?? ''),
                (string) $filtros['salida'],
            ),
            'catalogo' => $catalogo,
            'tramos' => $tramos,
            'secciones' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function persistirPreferencias(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_ids' => $filtros['empresa_ids'],
            'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
        ]);

        foreach (['columnas', 'agrupacion', 'orden', 'tipo_informe', 'tipo_vencimiento', 'tramos_dias', 'tramos_meses', 'modo_moneda', 'salida'] as $campo) {
            ReportePreferenciasUsuario::persistirString(
                self::PREFERENCIAS_CLAVE,
                $campo,
                (string) ($filtros[$campo] ?? ''),
            );
        }

        ReportePreferenciasUsuario::persistirBool(
            self::PREFERENCIAS_CLAVE,
            'incluir_adelantos',
            (bool) ($filtros['incluir_adelantos'] ?? true),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = ProyeccionPagosReporteFiltros::defaults();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $permitidos;
        }

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        foreach (['columnas', 'agrupacion', 'orden', 'tipo_informe', 'tipo_vencimiento', 'modo_moneda', 'salida'] as $campo) {
            if (! $request->has($campo)) {
                $filtros[$campo] = ReportePreferenciasUsuario::leerString(
                    self::PREFERENCIAS_CLAVE,
                    $campo,
                    (string) ($defaults[$campo] ?? ''),
                );
            }
        }

        foreach (['tramos_dias', 'tramos_meses'] as $campo) {
            if (! $request->has($campo)) {
                $filtros[$campo] = ReportePreferenciasUsuario::leerString(
                    self::PREFERENCIAS_CLAVE,
                    $campo,
                    (string) ($defaults[$campo] ?? ''),
                );
            }
        }

        if (! $request->has('incluir_adelantos') && ! $request->has('consultar')) {
            $filtros['incluir_adelantos'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'incluir_adelantos',
                true,
            );
        }

        if (($filtros['tipo_vencimiento'] ?? '') === ProyeccionPagosReporteFiltros::VENCIMIENTO_DIAS
            && ProyeccionPagosReporteFiltros::tramos($filtros) === []) {
            $filtros['tramos_dias'] = $defaults['tramos_dias'];
        }

        if (empty($filtros['fecha_base'])) {
            $filtros['fecha_base'] = $defaults['fecha_base'];
        }

        if ((int) ($filtros['moneda_id'] ?? 0) <= 0) {
            $filtros['moneda_id'] = (int) $defaults['moneda_id'];
        }

        return $filtros;
    }
}
