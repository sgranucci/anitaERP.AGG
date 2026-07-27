<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PortalProveedorPagosListadoExport;
use App\Exports\Compras\PortalProveedorRetencionesListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\PortalProveedorContexto;
use App\Support\Compras\PortalProveedorPagosListadoFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo Pagos / Retenciones del MVP interno Portal de Proveedores.
 */
final class PortalProveedorPagoController extends Controller
{
    public function __construct(
        private ProveedorRepositoryInterface $proveedorRepository,
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        $proveedorId = $proveedor ? (int) $proveedor->id : 0;
        $filtros = PortalProveedorPagosListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorPagosListadoFiltros::paraQueryString($filtros);

        $pagos = null;
        $resumen = null;

        if ($proveedorId > 0) {
            $pagos = $this->pagoproveedorRepository
                ->listarPortalProveedor($proveedorId, $filtros, true)
                ->appends($filtrosQuery);
            $resumen = $this->pagoproveedorRepository->resumenPortalProveedor($proveedorId, $filtros);
        }

        return view('compras.portal_proveedor.pagos.index', [
            'moduloActivo' => 'pagos',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId > 0 ? $proveedorId : null,
            'pagos' => $pagos,
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
            return redirect()->route('portal_proveedores_pagos')
                ->with('mensaje_error', 'Seleccione un proveedor para consultar el pago.');
        }

        $proveedorId = (int) $proveedor->id;

        try {
            $pago = $this->pagoproveedorRepository->findPortalProveedor($id, $proveedorId);
        } catch (ModelNotFoundException) {
            abort(404, 'Orden de pago no encontrada.');
        }

        $totalRetenciones = (float) $pago->pagoproveedor_retenciones->sum('importe');

        return view('compras.portal_proveedor.pagos.show', [
            'moduloActivo' => 'pagos',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId,
            'pago' => $pago,
            'totalRetenciones' => $totalRetenciones,
            'montoNeto' => (float) $pago->monto - $totalRetenciones,
            'canalMail' => [],
            'pdfIaHabilitado' => false,
            'filtrosQuery' => PortalProveedorContexto::queryBase($proveedorId),
        ]);
    }

    public function imprimir(Request $request, int $id): BinaryFileResponse
    {
        can('listar-portal-proveedores');

        $proveedorId = PortalProveedorContexto::proveedorIdDesdeRequest($request);
        if ($proveedorId <= 0) {
            abort(403, 'Proveedor no indicado.');
        }

        try {
            $pago = $this->pagoproveedorRepository->findPortalProveedor($id, $proveedorId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $pdf = Pdf::loadView('compras.pagoproveedor.comprobante', [
            'pago' => $pago,
            'retenciones' => $pago->pagoproveedor_retenciones,
            'aplicaciones' => $pago->pagoproveedor_comprobantes,
        ])->setPaper('a4');

        $dir = storage_path('pdf/portal_proveedor');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/op_'.$pago->id.'.pdf';
        $pdf->save($path);

        $nombrePdf = 'OP_'.$pago->id.'_'.$pago->numerotransaccion.'.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombrePdf.'"',
        ]);
    }

    public function imprimirRetencion(Request $request, int $id, int $retencionId): StreamedResponse
    {
        can('listar-portal-proveedores');

        $proveedorId = PortalProveedorContexto::proveedorIdDesdeRequest($request);
        if ($proveedorId <= 0) {
            abort(403, 'Proveedor no indicado.');
        }

        try {
            $pago = $this->pagoproveedorRepository->findPortalProveedor($id, $proveedorId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $retencion = $pago->pagoproveedor_retenciones->firstWhere('id', $retencionId);
        if ($retencion === null) {
            abort(404, 'Retención no encontrada.');
        }

        $pdf = Pdf::loadView('compras.pagoproveedor.retencion', [
            'pago' => $pago,
            'retencion' => $retencion,
        ])->setPaper('a4');

        return $pdf->stream('retencion_'.$retencion->id.'.pdf');
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-portal-proveedores');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            return redirect()->route('portal_proveedores_pagos')
                ->with('mensaje_error', 'Seleccione un proveedor para exportar.');
        }

        $proveedorId = (int) $proveedor->id;
        $filtros = PortalProveedorPagosListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorPagosListadoFiltros::paraQueryString($filtros);
        $formato = strtoupper($formato);

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('portal_proveedores_pagos', $filtrosQuery);
        }

        $filas = $this->pagoproveedorRepository->listarPortalProveedor($proveedorId, $filtros, false);
        $resumen = $this->pagoproveedorRepository->resumenPortalProveedor($proveedorId, $filtros);
        $subtitulo = PortalProveedorPagosListadoFiltros::subtituloFiltros($filtros);

        if ($formato === 'PDF') {
            foreach ($filas as $fila) {
                $fila->nombreempresa = $fila->empresas->nombre ?? '';
            }
            $pdf = Pdf::loadView('compras.portal_proveedor.pagos.listado', [
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
            $path = $dir.'/listado_portal_pagos.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = (new PortalProveedorPagosListadoExport)->parametros(
            $filas,
            $proveedor,
            $resumen,
            $subtitulo
        );

        $nombre = 'portal_pagos_'.now()->format('Ymd_His');

        return $formato === 'CSV'
            ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $nombre.'.xlsx');
    }

    public function retenciones(Request $request)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        $proveedorId = $proveedor ? (int) $proveedor->id : 0;
        $filtros = PortalProveedorPagosListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorPagosListadoFiltros::paraQueryString($filtros);

        $retenciones = null;
        $resumen = null;

        if ($proveedorId > 0) {
            $retenciones = $this->pagoproveedorRepository
                ->listarRetencionesPortalProveedor($proveedorId, $filtros, true)
                ->appends($filtrosQuery);
            $resumen = $this->pagoproveedorRepository->resumenPortalProveedor($proveedorId, $filtros);
        }

        return view('compras.portal_proveedor.retenciones.index', [
            'moduloActivo' => 'retenciones',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId > 0 ? $proveedorId : null,
            'retenciones' => $retenciones,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'canalMail' => [],
            'pdfIaHabilitado' => false,
        ]);
    }

    public function exportarRetenciones(Request $request, string $formato)
    {
        can('listar-portal-proveedores');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            return redirect()->route('portal_proveedores_retenciones')
                ->with('mensaje_error', 'Seleccione un proveedor para exportar.');
        }

        $proveedorId = (int) $proveedor->id;
        $filtros = PortalProveedorPagosListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PortalProveedorPagosListadoFiltros::paraQueryString($filtros);
        $formato = strtoupper($formato);

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('portal_proveedores_retenciones', $filtrosQuery);
        }

        $filas = $this->pagoproveedorRepository->listarRetencionesPortalProveedor($proveedorId, $filtros, false);
        $resumen = $this->pagoproveedorRepository->resumenPortalProveedor($proveedorId, $filtros);
        $subtitulo = PortalProveedorPagosListadoFiltros::subtituloFiltros($filtros);

        if ($formato === 'PDF') {
            foreach ($filas as $fila) {
                $fila->nombreempresa = optional($fila->pagoproveedores)->empresas->nombre ?? '';
            }
            $pdf = Pdf::loadView('compras.portal_proveedor.retenciones.listado', [
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
            $path = $dir.'/listado_portal_retenciones.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = (new PortalProveedorRetencionesListadoExport)->parametros(
            $filas,
            $proveedor,
            $resumen,
            $subtitulo
        );

        $nombre = 'portal_retenciones_'.now()->format('Ymd_His');

        return $formato === 'CSV'
            ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $nombre.'.xlsx');
    }
}
