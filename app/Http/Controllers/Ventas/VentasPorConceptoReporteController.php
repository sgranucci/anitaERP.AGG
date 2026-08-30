<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\VentasPorConceptoListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Ventas\Concepto_Venta;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Services\Ventas\VentasPorConceptoReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\VentasPorConceptoListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class VentasPorConceptoReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'ventas_por_concepto';

    public function __construct(
        private readonly VentasPorConceptoReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-ventas-por-concepto');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = VentasPorConceptoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if ($request->boolean('consultar') && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) $filtros['empresa_id'],
            ]);
            ReportePreferenciasUsuario::persistirString(
                self::PREFERENCIAS_CLAVE,
                'agrupar_por',
                (string) ($filtros['agrupar_por'] ?? VentasPorConceptoListadoFiltros::AGRUPAR_CONCEPTO),
            );
        }

        $consultado = false;
        $filas = null;
        $filasVista = [];
        $totales = null;

        if ($request->boolean('consultar') && VentasPorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
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

        $filtrosQuery = VentasPorConceptoListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(200, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.ventas_por_concepto.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'tipo_query' => $this->tiposParaSelector(),
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'periodo_texto' => VentasPorConceptoListadoFiltros::formatearPeriodoTexto($filtros),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'concepto_texto' => $this->etiquetaConcepto($filtros),
            'tipo_texto' => $this->etiquetaTipo((int) ($filtros['tipotransaccion_id'] ?? 0)),
            'agrupacion_texto' => $this->etiquetaAgrupacion($filtros),
            'puede_ver_venta' => can('editar-factura', false) || can('listar-factura', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_concepto' => can('editar-conceptos-venta', false) || can('listar-conceptos-venta', false),
            'puede_ver_cuenta' => can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-ventas-por-concepto');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = VentasPorConceptoListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! VentasPorConceptoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('ventas_por_concepto');
        }

        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $conceptoTexto = $this->etiquetaConcepto($filtros);
        $tipoTexto = $this->etiquetaTipo((int) ($filtros['tipotransaccion_id'] ?? 0));
        $titulo = $this->tituloReporte($filtros);
        $subtitulo = $this->reporteService->armarSubtitulo($filtros, $empresaTexto, $tipoTexto, $conceptoTexto);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.ventas_por_concepto.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_venta' => false,
                    'puede_ver_cliente' => false,
                    'puede_ver_concepto' => false,
                    'para_pdf' => true,
                ])->render();

                return $this->descargarPdfListado($view, 'ventas_por_concepto', 'legal', 'landscape');

            case 'EXCEL':
                return (new VentasPorConceptoListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('ventas_por_concepto.xlsx');

            case 'CSV':
                return (new VentasPorConceptoListadoExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo)
                    ->download('ventas_por_concepto.csv', Excel::CSV);
        }

        return redirect()->route(
            'ventas_por_concepto',
            array_merge(VentasPorConceptoListadoFiltros::paraQueryString($filtros), ['consultar' => 1]),
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
            $filtros['fecha_desde'] = date('Y-m-01');
        }
        if (($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }

        [$desde, $hasta] = VentasPorConceptoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        $this->completarEtiquetaConcepto($filtros);

        if (! request()->filled('agrupar_por')) {
            $guardada = ReportePreferenciasUsuario::leerString(
                self::PREFERENCIAS_CLAVE,
                'agrupar_por',
                VentasPorConceptoListadoFiltros::AGRUPAR_CONCEPTO,
            );
            $filtros['agrupar_por'] = VentasPorConceptoListadoFiltros::normalizarAgruparPor($guardada);
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function tituloReporte(array $filtros): string
    {
        return VentasPorConceptoListadoFiltros::agrupaPorCuenta($filtros)
            ? 'Ventas por concepto — por cuenta'
            : 'Ventas por concepto';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function etiquetaAgrupacion(array $filtros): string
    {
        return VentasPorConceptoListadoFiltros::agrupaPorCuenta($filtros)
            ? 'Cuenta contable'
            : 'Concepto';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function completarEtiquetaConcepto(array &$filtros): void
    {
        $id = (int) ($filtros['concepto_venta_id'] ?? 0);
        if ($id <= 0) {
            $filtros['concepto_codigo'] = '';
            $filtros['concepto_nombre'] = '';

            return;
        }

        if (($filtros['concepto_codigo'] ?? '') !== '' && ($filtros['concepto_nombre'] ?? '') !== '') {
            return;
        }

        $concepto = Concepto_Venta::query()->find($id);
        if ($concepto === null) {
            $filtros['concepto_venta_id'] = null;
            $filtros['concepto_codigo'] = '';
            $filtros['concepto_nombre'] = '';

            return;
        }

        $filtros['concepto_codigo'] = (string) ($concepto->codigo ?? '');
        $filtros['concepto_nombre'] = (string) ($concepto->nombre ?? '');
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
    private function etiquetaConcepto(array $filtros): string
    {
        if ((int) ($filtros['concepto_venta_id'] ?? 0) <= 0) {
            return 'Todos';
        }

        $codigo = trim((string) ($filtros['concepto_codigo'] ?? ''));
        $nombre = trim((string) ($filtros['concepto_nombre'] ?? ''));

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
}
