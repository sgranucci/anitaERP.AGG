<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PortalProveedorOrdencompraListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\PortalProveedorCircuitoOcSupport;
use App\Support\Compras\PortalProveedorContexto;
use App\Support\Compras\PortalProveedorOrdencompraListadoFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Módulo Órdenes de compra del MVP interno Portal de Proveedores.
 */
final class PortalProveedorOrdencompraController extends Controller
{
    public function __construct(
        private ProveedorRepositoryInterface $proveedorRepository,
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        $proveedorId = $proveedor ? (int) $proveedor->id : 0;
        $filtros = PortalProveedorOrdencompraListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorOrdencompraListadoFiltros::paraQueryString($filtros);

        $ordenes = null;
        $resumen = null;

        if ($proveedorId > 0) {
            $ordenes = $this->ordencompraRepository
                ->listarPortalProveedor($proveedorId, $filtros, true)
                ->appends($filtrosQuery);
            $resumen = $this->ordencompraRepository->resumenPortalProveedor($proveedorId, $filtros);
        }

        return view('compras.portal_proveedor.ordenes.index', [
            'moduloActivo' => 'ordenes',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId > 0 ? $proveedorId : null,
            'ordenes' => $ordenes,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'canalMail' => [],
            'pdfIaHabilitado' => false,
        ]);
    }

    public function show(Request $request, int $id)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            return redirect()->route('portal_proveedores_ordenes')
                ->with('mensaje_error', 'Seleccione un proveedor para consultar la orden de compra.');
        }

        $proveedorId = (int) $proveedor->id;

        try {
            $orden = $this->ordencompraRepository->findPortalProveedor($id, $proveedorId);
        } catch (ModelNotFoundException) {
            abort(404, 'Orden de compra no encontrada.');
        }

        return view('compras.portal_proveedor.ordenes.show', [
            'moduloActivo' => 'ordenes',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
            'orden' => $orden,
            'circuito' => PortalProveedorCircuitoOcSupport::desdeOrden($orden),
            'canalMail' => [],
            'pdfIaHabilitado' => false,
            'filtrosQuery' => PortalProveedorContexto::queryBase($proveedorId),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-portal-proveedores');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            return redirect()->route('portal_proveedores_ordenes')
                ->with('mensaje_error', 'Seleccione un proveedor para exportar.');
        }

        $proveedorId = (int) $proveedor->id;
        $filtros = PortalProveedorOrdencompraListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorOrdencompraListadoFiltros::paraQueryString($filtros);
        $formato = strtoupper($formato);

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('portal_proveedores_ordenes', $filtrosQuery);
        }

        $filas = $this->ordencompraRepository->listarPortalProveedor($proveedorId, $filtros, false);
        $resumen = $this->ordencompraRepository->resumenPortalProveedor($proveedorId, $filtros);
        $subtitulo = PortalProveedorOrdencompraListadoFiltros::subtituloFiltros($filtros);

        if ($formato === 'PDF') {
            foreach ($filas as $fila) {
                $fila->nombreempresa = $fila->empresas->nombre ?? '';
            }
            $pdf = Pdf::loadView('compras.portal_proveedor.ordenes.listado', [
                'filas' => $filas,
                'proveedor' => $proveedor,
                'resumen' => $resumen,
                'subtitulo' => $subtitulo,
                'logos' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas),
            ])->setPaper('legal', 'landscape');

            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir.'/listado_portal_ordenes.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = (new PortalProveedorOrdencompraListadoExport)->parametros(
            $filas,
            $proveedor,
            $resumen,
            $subtitulo
        );

        $nombre = 'portal_ordenes_'.now()->format('Ymd_His');

        return $formato === 'CSV'
            ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $nombre.'.xlsx');
    }
}
