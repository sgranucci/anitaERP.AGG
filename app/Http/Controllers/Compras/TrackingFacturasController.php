<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\TrackingFacturasListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Compras\TrackingFacturasRepository;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ComprobanteProveedorInternoPdfService;
use App\Services\Compras\Tracking\TrackingIndiceSyncService;
use App\Services\Compras\Tracking\TrackingPdfResolverService;
use App\Support\Compras\ComprobanteProveedorInternoTipos;
use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracking de facturas: la consulta transversal de comprobantes de proveedor.
 *
 * Reemplaza el «Informe de tracking de facturas» del sistema anterior, con sus
 * tres búsquedas (sin contabilizar, cargados entre fechas, sin pagar) y el
 * acceso al PDF de cada comprobante.
 */
class TrackingFacturasController extends Controller
{
    public function __construct(
        private readonly TrackingFacturasRepository $repository,
        private readonly TrackingPdfResolverService $pdfResolver,
        private readonly TrackingIndiceSyncService $syncService,
        private readonly ComprobanteProveedorInternoPdfService $pdfInterno,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-tracking-facturas');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltrosListado($request, null, $empresaQuery);

        return view('compras.tracking_facturas.index', [
            'datas' => $this->repository->leeTrackingFacturas($filtros, true),
            'resumen' => $this->repository->resumen($filtros),
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => TrackingFacturasListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TrackingFacturasListadoFiltros::CAMPOS,
            'segmentos' => TrackingFacturasListadoFiltros::segmentos(),
            'ejesFecha' => TrackingFacturasListadoFiltros::ejesFecha(),
            'familias' => $this->repository->familiasDisponibles(),
            'empresa_query' => $empresaQuery,
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('listar-tracking-facturas');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                return $this->descargarPdfListado($filtros);

            case 'EXCEL':
                return (new TrackingFacturasListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tracking_facturas.xlsx');

            case 'CSV':
                return (new TrackingFacturasListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tracking_facturas.csv', Excel::CSV);
        }

        return redirect()->route(
            'tracking_facturas',
            TrackingFacturasListadoFiltros::paraQueryString($filtros)
        );
    }

    /**
     * Entrega el PDF del comprobante.
     *
     * Orden: ruta cacheada del índice → resolución en vivo (scan/adjunto) →
     * PDF sintético para FIN/CIN (documento interno del ERP). Así los internos
     * sin escaneo igual tienen un comprobante imprimible con logo.
     */
    public function verPdf(Request $request, int $id): Response
    {
        can('ver-pdf-tracking-facturas');

        $comprobante = $this->repository->findParaTracking($id);
        if ($comprobante === null) {
            abort(404, 'El comprobante no existe o no pertenece a una empresa asignada.');
        }

        $ruta = $this->rutaPdfCacheada($comprobante);

        if ($ruta === null) {
            $modelo = $this->repository->modelosPorIds([$id])->first();
            $ruta = $modelo !== null ? $this->pdfResolver->resolver($modelo)?->ruta : null;

            if ($ruta === null && $modelo !== null && $this->pdfInterno->puedeGenerar($modelo)) {
                return $this->pdfInterno->generarRespuesta($modelo, $request->boolean('descargar'));
            }
        }

        if ($ruta === null) {
            // Último intento: aunque no haya modelo cargado, si la fila del
            // tracking dice FIN/CIN se arma el interno igual.
            if (ComprobanteProveedorInternoTipos::esInterno($comprobante->abreviaturatipotransaccion_compra ?? null)) {
                $modelo = $this->repository->modelosPorIds([$id])->first();
                if ($modelo !== null) {
                    return $this->pdfInterno->generarRespuesta($modelo, $request->boolean('descargar'));
                }
            }

            abort(404, 'No se encontró el PDF del comprobante en el ERP ni en el escaneo del Anita.');
        }

        $disposicion = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion.'; filename="'.$this->nombreArchivo($comprobante).'"',
        ]);
    }

    /**
     * Refresca el índice de los comprobantes de la página actual.
     *
     * Es la vía para actualizar PDF y saldo sin esperar el proceso nocturno,
     * acotada a lo que el usuario está mirando para no golpear el puente.
     */
    public function sincronizarPagina(Request $request)
    {
        can('listar-tracking-facturas');

        $filtros = $this->resolverFiltrosListado($request);
        $pagina = $this->repository->leeTrackingFacturas($filtros, true);

        $ids = collect($pagina->items())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return back()->with('advertencia', 'No hay comprobantes en la página para sincronizar.');
        }

        $modelos = $this->repository->modelosPorIds($ids);
        $stats = $this->syncService->sincronizarLote($modelos);

        return back()->with('mensaje', sprintf(
            'Sincronizados %d comprobantes: %d con PDF, %d sin PDF, %d con deuda.',
            $stats['procesados'],
            $stats['con_pdf'],
            $stats['sin_pdf'],
            $stats['con_deuda'],
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function descargarPdfListado(array $filtros): BinaryFileResponse
    {
        $datas = $this->repository->leeTrackingFacturas($filtros, false);
        $resumen = $this->repository->resumen($filtros);

        $html = view('compras.tracking_facturas.listado', compact('datas', 'resumen', 'filtros'))->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/tracking_facturas.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo);
    }

    private function rutaPdfCacheada(object $comprobante): ?string
    {
        $ruta = trim((string) ($comprobante->pdf_ruta ?? ''));

        return $ruta !== '' && is_readable($ruta) ? $ruta : null;
    }

    private function nombreArchivo(object $comprobante): string
    {
        return sprintf(
            '%s_%s_%04d-%08d.pdf',
            $comprobante->abreviaturatipotransaccion_compra ?? 'comprobante',
            $comprobante->letra ?? '',
            (int) ($comprobante->sucursal ?? 0),
            (int) ($comprobante->numerocomprobante ?? 0),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(
        Request $request,
        ?string $busquedaRuta = null,
        $empresaQuery = null
    ): array {
        $empresas = $empresaQuery ?? $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresas->first())->id;

        return TrackingFacturasListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }
}
