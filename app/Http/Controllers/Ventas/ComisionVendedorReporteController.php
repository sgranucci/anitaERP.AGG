<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ComisionVendedorDetalleListadoExport;
use App\Exports\Ventas\ComisionVendedorResumenListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Vendedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Services\Ventas\ComisionVendedorReporteService;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\ComisionVendedorListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ComisionVendedorReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'comision_vendedor';

    public function __construct(
        private readonly ComisionVendedorReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {
        $this->middleware('auth');
    }

    public function indexDetalle(Request $request)
    {
        return $this->index($request, ComisionVendedorListadoFiltros::MODO_DETALLE);
    }

    public function indexResumen(Request $request)
    {
        return $this->index($request, ComisionVendedorListadoFiltros::MODO_RESUMEN);
    }

    public function exportarDetalle(Request $request, string $formato)
    {
        return $this->exportar($request, $formato, ComisionVendedorListadoFiltros::MODO_DETALLE);
    }

    public function exportarResumen(Request $request, string $formato)
    {
        return $this->exportar($request, $formato, ComisionVendedorListadoFiltros::MODO_RESUMEN);
    }

    private function index(Request $request, string $modo)
    {
        $this->assertPermiso($modo);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ComisionVendedorListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar') && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) $filtros['empresa_id'],
            ]);
        }

        $consultado = false;
        $filas = null;
        $filasVista = [];
        $totales = null;

        if ($request->boolean('consultar') && ComisionVendedorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->generar($modo, $filtros);
            $totales = $resultado['totales'];
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = ComisionVendedorListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $esDetalle = $modo === ComisionVendedorListadoFiltros::MODO_DETALLE;

        return view($esDetalle ? 'ventas.comision_vendedor_detalle.index' : 'ventas.comision_vendedor.index', [
            'modo' => $modo,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'tipo_query' => $this->tiposParaSelector(),
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'periodo_texto' => ComisionVendedorListadoFiltros::formatearPeriodoTexto($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'vendedor_texto' => $this->etiquetaVendedor($filtros),
            'tipo_texto' => $this->etiquetaTipo((int) ($filtros['tipotransaccion_id'] ?? 0)),
            'puede_ver_venta' => can('editar-factura', false) || can('listar-factura', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_vendedor' => can('editar-vendedores', false) || can('listar-vendedores', false),
            'ruta_export' => $esDetalle ? 'listar_comision_vendedor_detalle' : 'listar_comision_vendedor',
            'ruta_index' => $esDetalle ? 'comision_vendedor_detalle' : 'comision_vendedor',
            'ruta_hermano' => $esDetalle ? 'comision_vendedor' : 'comision_vendedor_detalle',
        ]);
    }

    private function exportar(Request $request, string $formato, string $modo)
    {
        $this->assertPermiso($modo);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ComisionVendedorListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $rutaIndex = $modo === ComisionVendedorListadoFiltros::MODO_DETALLE
            ? 'comision_vendedor_detalle'
            : 'comision_vendedor';

        if (! ComisionVendedorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route($rutaIndex);
        }

        $resultado = $this->generar($modo, $filtros);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $vendedorTexto = $this->etiquetaVendedor($filtros);
        $tipoTexto = $this->etiquetaTipo((int) ($filtros['tipotransaccion_id'] ?? 0));
        $titulo = $modo === ComisionVendedorListadoFiltros::MODO_DETALLE
            ? 'Comisiones de vendedores — detalle'
            : 'Comisiones de vendedores — resumen';
        $subtitulo = $this->reporteService->armarSubtitulo($filtros, $empresaTexto, $vendedorTexto, $tipoTexto);

        $esDetalle = $modo === ComisionVendedorListadoFiltros::MODO_DETALLE;

        switch (strtoupper($formato)) {
            case 'PDF':
                $vista = $esDetalle
                    ? 'ventas.comision_vendedor_detalle.listado'
                    : 'ventas.comision_vendedor.listado';
                $html = view($vista, [
                    'filas' => $filas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_venta' => false,
                    'puede_ver_cliente' => false,
                    'puede_ver_vendedor' => false,
                    'para_pdf' => true,
                ])->render();

                $nombreBase = $esDetalle ? 'comision_vendedor_detalle' : 'comision_vendedor';
                $ruta = storage_path('pdf/listados/'.$nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true).'.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => $esDetalle ? 'Comisiones detalle' : 'Comisiones resumen',
                ]);

                return response()->download($ruta)->deleteFileAfterSend(true);

            case 'EXCEL':
                if ($esDetalle) {
                    return (new ComisionVendedorDetalleListadoExport($this->reporteService))
                        ->parametros($filtros, $titulo, $subtitulo)
                        ->download('comision_vendedor_detalle.xlsx');
                }

                return (new ComisionVendedorResumenListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('comision_vendedor.xlsx');

            case 'CSV':
                if ($esDetalle) {
                    return (new ComisionVendedorDetalleListadoExport($this->reporteService))
                        ->parametros($filtros, $titulo, $subtitulo)
                        ->download('comision_vendedor_detalle.csv', Excel::CSV);
                }

                return (new ComisionVendedorResumenListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('comision_vendedor.csv', Excel::CSV);
        }

        return redirect()->route(
            $rutaIndex,
            array_merge(ComisionVendedorListadoFiltros::paraQueryString($filtros), ['consultar' => 1]),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>}
     */
    private function generar(string $modo, array $filtros): array
    {
        return $modo === ComisionVendedorListadoFiltros::MODO_DETALLE
            ? $this->reporteService->generarDetalle($filtros)
            : $this->reporteService->generarResumen($filtros);
    }

    private function assertPermiso(string $modo): void
    {
        if ($modo === ComisionVendedorListadoFiltros::MODO_DETALLE) {
            can('listar-comision-vendedor-detalle');
        } else {
            can('listar-comision-vendedor');
        }
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
            $filtros['fecha_desde'] = date('Y-m-01');
        }
        if (($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }

        [$desde, $hasta] = ComisionVendedorListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        $this->completarEtiquetaVendedor($filtros);

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function completarEtiquetaVendedor(array &$filtros): void
    {
        $id = (int) ($filtros['vendedor_id'] ?? 0);
        if ($id <= 0) {
            $filtros['vendedor_codigo'] = '';
            $filtros['vendedor_nombre'] = '';

            return;
        }

        if (($filtros['vendedor_codigo'] ?? '') !== '' && ($filtros['vendedor_nombre'] ?? '') !== '') {
            return;
        }

        $vendedor = Vendedor::query()->find($id);
        if ($vendedor === null) {
            $filtros['vendedor_id'] = null;
            $filtros['vendedor_codigo'] = '';
            $filtros['vendedor_nombre'] = '';

            return;
        }

        $filtros['vendedor_codigo'] = (string) ($vendedor->codigo ?? '');
        $filtros['vendedor_nombre'] = (string) ($vendedor->nombre ?? '');
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function tiposParaSelector()
    {
        return $this->tipotransaccionRepository
            ->all(['V', 'C', 'U'])
            ->sortBy(fn ($t) => (string) ($t->abreviatura ?? $t->nombre))
            ->values();
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

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function etiquetaVendedor(array $filtros): string
    {
        if ((int) ($filtros['vendedor_id'] ?? 0) <= 0) {
            return 'Todos';
        }

        $codigo = trim((string) ($filtros['vendedor_codigo'] ?? ''));
        $nombre = trim((string) ($filtros['vendedor_nombre'] ?? ''));

        return trim($codigo.($nombre !== '' ? ' — '.$nombre : ''));
    }

    private function etiquetaTipo(int $tipoId): string
    {
        if ($tipoId <= 0) {
            return 'Todos';
        }

        $tipo = $this->tiposParaSelector()->firstWhere('id', $tipoId);
        if ($tipo === null) {
            return (string) $tipoId;
        }

        return trim((string) ($tipo->abreviatura ?? '').' '.(string) ($tipo->nombre ?? ''));
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
}
