<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Compras\Proveedor_Documento_FiscalRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Support\Compras\PortalProveedorContexto;
use App\Support\Compras\ProveedorDocumentoFiscalSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Presentación de CUIT / CM05 anual desde el portal de proveedores.
 */
final class PortalProveedorDocumentoController extends Controller
{
    public function __construct(
        private ProveedorRepositoryInterface $proveedorRepository,
        private Proveedor_Documento_FiscalRepositoryInterface $documentoFiscalRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        $proveedorId = $proveedor ? (int) $proveedor->id : 0;
        $documentos = collect();
        $avisos = [];
        $vigentes = [
            ProveedorDocumentoFiscalSupport::TIPO_CUIT => null,
            ProveedorDocumentoFiscalSupport::TIPO_CM05 => null,
        ];

        if ($proveedorId > 0) {
            $documentos = $this->documentoFiscalRepository->listarPorProveedor($proveedorId);
            $avisos = $this->documentoFiscalRepository->avisosPortal($proveedorId);
            $vigentes = $this->documentoFiscalRepository->vigentesPorTipo($proveedorId);
        }

        return view('compras.portal_proveedor.documentos.index', [
            'moduloActivo' => 'documentos',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId > 0 ? $proveedorId : null,
            'documentos' => $documentos,
            'avisos' => $avisos,
            'vigentes' => $vigentes,
            'puedePresentar' => can('cargar-portal-proveedores', false),
            'canalMail' => [],
            'pdfIaHabilitado' => false,
        ]);
    }

    public function guardar(Request $request)
    {
        can('cargar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            return redirect()->route('portal_proveedores_documentos')
                ->with('mensaje_error', 'Seleccione un proveedor para presentar documentación.');
        }

        $data = $request->validate([
            'tipo' => 'required|in:CUIT,CM05',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'fecha_vencimiento' => 'required|date',
            'anio_ejercicio' => 'nullable|integer|min:2000|max:2100',
        ]);

        $this->documentoFiscalRepository->crearDesdeUpload(
            (int) $proveedor->id,
            $data['tipo'],
            $request->file('archivo'),
            $data['fecha_vencimiento'],
            isset($data['anio_ejercicio']) ? (int) $data['anio_ejercicio'] : null,
            ProveedorDocumentoFiscalSupport::ORIGEN_PORTAL,
        );

        return redirect()
            ->route('portal_proveedores_documentos', ['proveedor_id' => $proveedor->id])
            ->with('mensaje', ProveedorDocumentoFiscalSupport::etiquetaTipo($data['tipo'])
                .' presentado. Quedó registrado en el padrón del proveedor (solapa CM05).');
    }

    public function ver(Request $request, int $id): BinaryFileResponse
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        if (! $proveedor) {
            abort(404);
        }

        try {
            $doc = $this->documentoFiscalRepository->findDelProveedor($id, (int) $proveedor->id);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $path = ProveedorDocumentoFiscalSupport::directorioProveedor((int) $proveedor->id)
            .DIRECTORY_SEPARATOR.$doc->nombrearchivo;
        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file($path);
    }
}
