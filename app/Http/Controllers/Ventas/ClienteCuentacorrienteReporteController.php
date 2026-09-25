<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ClienteCuentacorrienteReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\ClienteCuentacorrienteReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\ClienteCuentacorrienteReporteClienteSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteFiltros;
use App\Support\Ventas\ClienteCuentacorrienteReporteVendedorSupport;
use Illuminate\Http\Request;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class ClienteCuentacorrienteReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'cliente_cuentacorriente_reporte';

    public function __construct(
        private readonly ClienteCuentacorrienteReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cliente-cuentacorriente-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ClienteCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($request->boolean('consultar') && ClienteCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
            $this->persistirPreferencias($filtros);

            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
            $page = max(1, (int) $request->input('page', 1));
            $filas = $this->reporteService->paginarFilas($resultado['filas'] ?? [], $perPage, $page);
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = ClienteCuentacorrienteReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        $clientesIniciales = $this->clientesInicialesParaVista($filtros, $resultado);
        $vendedoresIniciales = $this->vendedoresInicialesParaVista($filtros, $resultado);

        return view('ventas.cliente_cuentacorriente_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'clientes_iniciales' => $clientesIniciales,
            'vendedores_iniciales' => $vendedoresIniciales,
            'subtitulo' => ClienteCuentacorrienteReporteFiltros::armarSubtitulo(
                $filtros,
                $this->textoEmpresas($filtros, $empresaQuery),
                $this->textoVendedores($vendedoresIniciales, $resultado)
            ),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_factura' => can('listar-factura', false) || can('editar-factura', false),
            'puede_ver_cobranza' => can('listar-cobranza', false) || can('editar-cobranza', false),
            'mostrarLinks' => true,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-cliente-cuentacorriente-reporte');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ClienteCuentacorrienteReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if (! ClienteCuentacorrienteReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('cliente_cuentacorriente_reporte');
        }

        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'] ?? [];
        $titulo = (($filtros['modo'] ?? '') === ClienteCuentacorrienteReporteFiltros::MODO_FICHA)
            ? 'Ficha cuenta corriente de clientes'
            : 'Deuda de clientes por vendedor';
        $vendedoresTexto = $this->textoVendedores(
            $this->vendedoresInicialesDesdeResultado($resultado, $filtros),
            $resultado
        );
        $subtitulo = ClienteCuentacorrienteReporteFiltros::armarSubtitulo(
            $filtros,
            $this->textoEmpresas($filtros, $empresaQuery),
            $vendedoresTexto
        );

        switch (strtoupper($formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado, $titulo);
                }

                return $this->descargarPdf(
                    $this->renderizarPdf($filas, $resultado, $filtros, $titulo, $subtitulo),
                    'cliente_cc_reporte_'.date('Ymd_His')
                );

            case 'EXCEL':
                return (new ClienteCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('cliente_cuentacorriente_reporte.xlsx');

            case 'CSV':
                return (new ClienteCuentacorrienteReporteExport($filas, $titulo, $subtitulo, $resultado, $filtros))
                    ->download('cliente_cuentacorriente_reporte.csv', Excel::CSV);
        }

        return redirect()->route(
            'cliente_cuentacorriente_reporte',
            array_merge(ClienteCuentacorrienteReporteFiltros::paraQueryString($filtros), ['consultar' => 1])
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    private function renderizarPdf(
        array $filas,
        array $resultado,
        array $filtros,
        string $titulo,
        string $subtitulo
    ): string {
        return \View::make('ventas.cliente_cuentacorriente_reporte.listado', [
            'filas' => $filas,
            'resultado' => $resultado,
            'filtros' => $filtros,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'mostrarLinks' => false,
            'para_pdf' => true,
            'puede_ver_cliente' => false,
            'puede_ver_factura' => false,
            'puede_ver_cobranza' => false,
        ])->render();
    }

    private function descargarPdf(string $html, string $nombre)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($path.'/'.$nombre.'.pdf');

        return response()->download($path.'/'.$nombre.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoCompleto
     */
    private function descargarPdfPorEmpresa(array $filtros, array $resultadoCompleto, string $titulo)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $temporales = [];

        try {
            foreach ($resultadoCompleto['secciones'] ?? [] as $seccion) {
                $empresaId = (int) ($seccion['empresa_id'] ?? 0);
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'empresa_id' => $empresaId,
                    'consolidar_empresas' => true,
                ]);
                $subtitulo = trim((string) ($seccion['empresa_nombre'] ?? ''))
                    .' · '.ClienteCuentacorrienteReporteFiltros::armarSubtitulo($filtrosEmpresa);
                $html = $this->renderizarPdf(
                    $seccion['filas'] ?? [],
                    array_merge($resultadoCompleto, [
                        'filas' => $seccion['filas'] ?? [],
                        'totales' => $seccion['totales'] ?? [],
                        'stats' => $seccion['stats'] ?? [],
                    ]),
                    $filtrosEmpresa,
                    $titulo,
                    $subtitulo
                );

                $temp = $dir.'/cliente_cc_reporte_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($html)->save($temp);
                $temporales[] = $temp;
            }

            $nombreBase = 'cliente_cc_reporte_'.date('Ymd_His');
            $destino = $dir.'/'.$nombreBase.'.pdf';

            if ($temporales === []) {
                return $this->descargarPdf(
                    $this->renderizarPdf(
                        $resultadoCompleto['filas'] ?? [],
                        $resultadoCompleto,
                        $filtros,
                        $titulo,
                        ClienteCuentacorrienteReporteFiltros::armarSubtitulo($filtros)
                    ),
                    $nombreBase
                );
            }

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
     * @param  array<string, mixed>  $filtros
     */
    private function persistirPreferencias(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_ids' => ClienteCuentacorrienteReporteFiltros::empresaIds($filtros),
            'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            } elseif (($cachedId = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE)) !== null
                && in_array($cachedId, $permitidos, true)) {
                $filtros['empresa_ids'] = [$cachedId];
            }
        } else {
            $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
                $filtros['empresa_ids'],
                $permitidos
            );
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $permitidos;
        }

        $filtros['empresa_id'] = $filtros['empresa_ids'][0] ?? null;

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true
            );
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    private function textoEmpresas(array $filtros, $empresaQuery): string
    {
        $ids = ClienteCuentacorrienteReporteFiltros::empresaIds($filtros);
        if ($ids === []) {
            return '';
        }

        return $empresaQuery->whereIn('id', $ids)->pluck('nombre')->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function clientesInicialesParaVista(array $filtros, ?array $resultado): array
    {
        if (! empty($resultado['clientes_resueltos'])) {
            return $resultado['clientes_resueltos'];
        }

        if (($filtros['alcance_clientes'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES) {
            return ClienteCuentacorrienteReporteClienteSupport::etiquetasPorIds($filtros['cliente_ids'] ?? []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function vendedoresInicialesParaVista(array $filtros, ?array $resultado): array
    {
        if (! empty($resultado['vendedores_resueltos'])) {
            return $resultado['vendedores_resueltos'];
        }

        if (($filtros['alcance_vendedores'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES) {
            return ClienteCuentacorrienteReporteVendedorSupport::etiquetasPorIds($filtros['vendedor_ids'] ?? []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    private function vendedoresInicialesDesdeResultado(array $resultado, array $filtros): array
    {
        return $this->vendedoresInicialesParaVista($filtros, $resultado);
    }

    /**
     * @param  list<array{id?:int,codigo?:string,nombre?:string}>  $vendedores
     * @param  array<string, mixed>|null  $resultado
     */
    private function textoVendedores(array $vendedores, ?array $resultado): string
    {
        $etiquetas = [];
        foreach ($vendedores as $vend) {
            $etiqueta = trim(
                (trim((string) ($vend['codigo'] ?? '')) !== '' ? trim((string) $vend['codigo']).' ' : '')
                .(string) ($vend['nombre'] ?? '')
            );
            if ($etiqueta !== '') {
                $etiquetas[] = $etiqueta;
            }
        }

        if ($etiquetas === [] && is_array($resultado)) {
            foreach ($resultado['filas'] ?? [] as $fila) {
                if (($fila['tipo'] ?? '') !== 'header_vendedor') {
                    continue;
                }
                $etiqueta = trim(
                    (trim((string) ($fila['vendedor_codigo'] ?? '')) !== ''
                        ? trim((string) $fila['vendedor_codigo']).' '
                        : '')
                    .(string) ($fila['vendedor_nombre'] ?? '')
                );
                if ($etiqueta !== '') {
                    $etiquetas[$etiqueta] = $etiqueta;
                }
            }
            $etiquetas = array_values($etiquetas);
        }

        if ($etiquetas === []) {
            return '';
        }

        if (count($etiquetas) > 4) {
            return count($etiquetas).' vendedores';
        }

        return implode(', ', $etiquetas);
    }
}
