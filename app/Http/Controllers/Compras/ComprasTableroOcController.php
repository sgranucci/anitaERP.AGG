<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ComprasTableroOcAhorrosExport;
use App\Exports\Compras\ComprasTableroOcDetalleExport;
use App\Http\Controllers\Controller;
use App\Jobs\Compras\GenerarTableroComprasPdfJob;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ComprasTableroOcPdfService;
use App\Services\Compras\ComprasTableroOcService;
use App\Support\Compras\ComprasKpisProcesoProductividadSupport;
use App\Support\Compras\ComprasTableroOcFiltros;
use App\Support\Compras\ComprasTableroOcGraficosSupport;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel;

/**
 * Tablero de compras: réplica del Power BI (Compras, OC, Compradores, Proveedores, Precios).
 */
class ComprasTableroOcController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'tablero_compras_oc';

    private const POR_PAGINA_DETALLE = 50;

    public function __construct(
        private ComprasTableroOcService $service,
        private ComprasTableroOcPdfService $pdfService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can(ComprasKpisProcesoProductividadSupport::PERMISO);

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);

        $resultado = null;
        if (ComprasTableroOcFiltros::tieneCriteriosAplicados($filtros)) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);
            $resultado = $this->service->generar($filtros);
        }

        $filtrosQuery = ComprasTableroOcFiltros::paraQueryString($filtros);

        $detalle = null;
        if ($resultado !== null) {
            $detalle = $this->service->paginarFilas(
                $resultado['detalle'],
                self::POR_PAGINA_DETALLE,
                max(1, (int) $request->input('page', 1)),
                $filtrosQuery + ['tab' => ComprasTableroOcFiltros::TAB_DETALLE],
            );
        }

        $ahorroDetalle = null;
        if ($resultado !== null) {
            $ahorroDetalle = $this->service->paginarFilas(
                $resultado['ahorros']['filas'],
                self::POR_PAGINA_DETALLE,
                max(1, (int) $request->input('page_ahorros', 1)),
                $filtrosQuery + ['tab' => ComprasTableroOcFiltros::TAB_AHORROS],
                'page_ahorros',
            );
        }

        return view('compras.tablero_oc.index', [
            'ahorros' => $resultado['ahorros'] ?? null,
            'ahorro_detalle' => $ahorroDetalle,
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'resultado' => $resultado,
            'tablero' => $resultado['tablero'] ?? null,
            'meses' => $resultado['meses'] ?? [],
            'graficos' => $resultado !== null
                ? ComprasTableroOcGraficosSupport::desde($resultado['tablero'], $resultado['meses'], $resultado['ahorros'])
                : null,
            'detalle' => $detalle,
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'periodo_texto' => ComprasTableroOcFiltros::textoPeriodo($filtros),
            'anios' => ComprasTableroOcFiltros::aniosDisponibles(),
            'meses_nombres' => ComprasTableroOcFiltros::MESES,
            'opciones_estado' => ComprasTableroOcFiltros::OPCIONES_ESTADO,
            'opciones_vencimiento' => ComprasTableroOcFiltros::OPCIONES_VENCIMIENTO,
            'opciones_com' => ComprasTableroOcFiltros::OPCIONES_COM,
            'opciones_factura' => ComprasTableroOcFiltros::OPCIONES_FACTURA,
            'meta_usuarios' => RequisicionReporteCriteriosSupport::metaTextoUsuarios((string) $filtros['usuarios']),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores((string) $filtros['proveedores']),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can(ComprasKpisProcesoProductividadSupport::PERMISO);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltros($request, $empresaQuery);

        if (! ComprasTableroOcFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('consultar_tablero_compras');
        }

        $resultado = $this->service->generar($filtros);
        $filas = $resultado['detalle'];
        $titulo = 'Compras: detalle de líneas de órdenes de compra';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                if (count($filas) > (int) config('compras.tablero_pdf.max_filas_sincrono', 3000)) {
                    return $this->encolarPdf($request, $filtros, $subtitulo, count($filas));
                }

                $ruta = $this->pdfService->generar($filas, $titulo, $subtitulo);

                return response()->download($ruta, 'tablero_compras_oc.pdf')->deleteFileAfterSend(true);

            case 'EXCEL':
                if ($request->input('tab') === ComprasTableroOcFiltros::TAB_AHORROS) {
                    return $this->exportarAhorros($resultado['ahorros']['filas'], $subtitulo, 'tablero_compras_ahorros.xlsx');
                }

                return (new ComprasTableroOcDetalleExport($filas, $titulo, $subtitulo))
                    ->download('tablero_compras_detalle.xlsx');

            case 'CSV':
                if ($request->input('tab') === ComprasTableroOcFiltros::TAB_AHORROS) {
                    return $this->exportarAhorros($resultado['ahorros']['filas'], $subtitulo, 'tablero_compras_ahorros.csv', Excel::CSV);
                }

                return (new ComprasTableroOcDetalleExport($filas, $titulo, $subtitulo))
                    ->download('tablero_compras_detalle.csv', Excel::CSV);
        }

        return redirect()->route('consultar_tablero_compras', ComprasTableroOcFiltros::paraQueryString($filtros));
    }

    /**
     * Excel / CSV del detalle de ahorros (solo líneas con ahorro cargado, con los filtros del tablero).
     *
     * @param  list<array<string, mixed>>  $filas
     */
    private function exportarAhorros(array $filas, string $subtitulo, string $archivo, ?string $writerType = null)
    {
        $export = new ComprasTableroOcAhorrosExport($filas, 'Compras: ahorros por línea de orden de compra', $subtitulo);

        return $writerType === null ? $export->download($archivo) : $export->download($archivo, $writerType);
    }

    /**
     * PDF grande: se arma en la cola y llega por mail. Desde la pantalla (fetch) responde JSON;
     * si alguien abre el enlace directo, vuelve al tablero con el aviso.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function encolarPdf(Request $request, array $filtros, string $subtitulo, int $lineas)
    {
        $usuario = auth()->user();
        $email = trim((string) ($usuario->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respuestaPdfEncolado(
                $request,
                $filtros,
                false,
                'El PDF tiene '.number_format($lineas, 0, ',', '.').' líneas y se envía por mail, pero tu usuario no tiene un email válido. Cargá tu email o exportá a Excel.'
            );
        }

        try {
            Bus::dispatch(new GenerarTableroComprasPdfJob($filtros, $subtitulo, (int) auth()->id()));
        } catch (\Throwable $e) {
            Log::error('tablero_compras.pdf.dispatch_fallo', ['usuario_id' => auth()->id(), 'error' => $e->getMessage()]);

            return $this->respuestaPdfEncolado($request, $filtros, false, 'No se pudo encolar el PDF: '.$e->getMessage());
        }

        return $this->respuestaPdfEncolado(
            $request,
            $filtros,
            true,
            'El PDF tiene '.number_format($lineas, 0, ',', '.').' líneas: se genera en segundo plano y te llega por mail a '.$email.' cuando termine.'
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function respuestaPdfEncolado(Request $request, array $filtros, bool $ok, string $mensaje)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => $ok, 'encolado' => $ok, 'mensaje' => $mensaje], $ok ? 202 : 422);
        }

        return redirect()
            ->route('consultar_tablero_compras', ComprasTableroOcFiltros::paraQueryString($filtros) + ['tab' => ComprasTableroOcFiltros::TAB_DETALLE])
            ->with($ok ? 'mensaje-aviso' : 'mensaje-error', $mensaje);
    }

    /**
     * @param  iterable<object>  $empresaQuery
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request, $empresaQuery): array
    {
        $filtros = ComprasTableroOcFiltros::resolverDesdeRequest($request);
        $permitidos = collect($empresaQuery)->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Empresas pedidas que el usuario no tiene asignadas: se descartan.
        $filtros['empresa_ids'] = array_values(array_intersect($filtros['empresa_ids'], $permitidos));

        // Solo se recuerda la empresa si la pantalla se abrió sin elegir (no si "Ninguna" a propósito).
        if ($filtros['empresa_ids'] === [] && ! $request->has('consultar')) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
            // Como en el Power BI: sin elección previa se ven todas las empresas.
            if ($filtros['empresa_ids'] === []) {
                $filtros['empresa_ids'] = $permitidos;
            }
        }

        return $filtros;
    }
}
