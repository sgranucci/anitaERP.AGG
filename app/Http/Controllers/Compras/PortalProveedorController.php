<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Proveedor_Documento_FiscalRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Services\Compras\ComprobanteProveedorPdfIaService;
use App\Support\Compras\PortalProveedorContexto;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\PrecargaRecepcionErrorRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * MVP interno del Portal de Proveedores.
 *
 * Permite simular la experiencia futura desde el menú de Compras: seleccionar un
 * proveedor, enviar una factura PDF y seguir sus precargas. En la versión externa,
 * el proveedor se resolverá exclusivamente desde la cuenta autenticada; no se
 * aceptará proveedor_id desde el navegador.
 */
final class PortalProveedorController extends Controller
{
    public function __construct(
        private ProveedorRepositoryInterface $proveedorRepository,
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private Proveedor_Documento_FiscalRepositoryInterface $documentoFiscalRepository,
        private ComprobanteProveedorPdfIaService $pdfIaService,
        private PrecargaFacturaScanPathResolver $facturaScanPathResolver,
    ) {}

    public function index(Request $request)
    {
        can('listar-portal-proveedores');

        $proveedor = PortalProveedorContexto::resolverProveedor($request, $this->proveedorRepository);
        $proveedorId = $proveedor ? (int) $proveedor->id : 0;
        $precargas = null;
        $avisosDocumentos = [];

        if ($proveedorId > 0) {
            $precargas = $this->precargaRepository
                ->listarPortalProveedor($proveedorId, true)
                ->appends(['proveedor_id' => $proveedorId]);
            $avisosDocumentos = $this->documentoFiscalRepository->avisosPortal($proveedorId);
        }

        return view('compras.portal_proveedor.index', [
            'moduloActivo' => 'facturas',
            'proveedor' => $proveedor,
            'proveedorId' => $proveedorId > 0 ? $proveedorId : null,
            'precargas' => $precargas,
            'avisosDocumentos' => $avisosDocumentos,
            'pdfIaHabilitado' => (bool) config('comprobante_proveedor_pdf_ia.habilitado'),
            'canalMail' => $this->datosCanalMail(),
        ]);
    }

    /**
     * Datos del canal mail (misma casilla que compras:ingestar-facturas-mail).
     *
     * @return array{
     *   habilitado: bool,
     *   casilla: string,
     *   carpeta: string,
     *   intervalo_min: int,
     *   filtro_palabras: string
     * }
     */
    private function datosCanalMail(): array
    {
        $casilla = trim((string) config('precarga_comprobante_mail.imap.username', ''));
        $ingestaOn = (bool) config('precarga_comprobante_mail.habilitada');

        return [
            // Mostrar casilla aunque la ingesta esté apagada (falta password IMAP).
            'habilitado' => $casilla !== '',
            'ingesta_activa' => $ingestaOn && $casilla !== '',
            'casilla' => $casilla,
            'carpeta' => (string) config('precarga_comprobante_mail.carpeta', 'INBOX'),
            'intervalo_min' => (int) config('precarga_comprobante_mail.intervalo_minutos', 5),
            'filtro_palabras' => (string) config(
                'precarga_comprobante_mail.filtro_candidato.palabras',
                'factura,orden de compra'
            ),
        ];
    }

    public function preview(Request $request): JsonResponse
    {
        can('cargar-portal-proveedores');

        $request->validate([
            'proveedor_id' => 'required|integer|min:1',
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'numero_oc' => 'nullable|string|max:12',
        ]);

        $proveedorId = (int) $request->input('proveedor_id');
        $this->assertProveedorExiste($proveedorId);

        try {
            $preview = $this->pdfIaService->preview(
                $request->file('pdf'),
                $request->input('numero_oc')
            );

            $this->assertProveedorPreview($preview, $proveedorId);
            $preview['portal_proveedor_id'] = $proveedorId;

            return response()->json($preview, ($preview['ok'] ?? false) ? 200 : 422);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'portal_preview',
                $e->getMessage(),
                422,
                ['proveedor_id' => $proveedorId],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Error al procesar la factura del portal.',
            ], 500);
        }
    }

    public function resolverOc(Request $request): JsonResponse
    {
        can('cargar-portal-proveedores');

        $request->validate([
            'proveedor_id' => 'required|integer|min:1',
            'extraccion' => 'required|json',
            'numero_oc' => 'required|string|max:12',
        ]);

        $proveedorId = (int) $request->input('proveedor_id');
        $this->assertProveedorExiste($proveedorId);
        $extraccion = json_decode((string) $request->input('extraccion'), true);

        if (! is_array($extraccion)) {
            return response()->json(['ok' => false, 'message' => 'Extracción inválida.'], 422);
        }

        try {
            $preview = $this->pdfIaService->resolverConOcManual(
                $extraccion,
                (string) $request->input('numero_oc')
            );

            $this->assertProveedorPreview($preview, $proveedorId);
            $preview['portal_proveedor_id'] = $proveedorId;

            return response()->json($preview);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'oc_requerida' => true,
                'message' => $e->getMessage(),
                'extraccion' => $extraccion,
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Error al validar la OC del portal.',
            ], 500);
        }
    }

    public function confirmar(Request $request): JsonResponse
    {
        can('cargar-portal-proveedores');

        $request->validate([
            'proveedor_id' => 'required|integer|min:1',
            'payload' => 'required|json',
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $proveedorId = (int) $request->input('proveedor_id');
        $this->assertProveedorExiste($proveedorId);
        $payload = json_decode((string) $request->input('payload'), true);

        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'message' => 'Payload inválido.'], 422);
        }

        try {
            $resultado = $this->pdfIaService->confirmar(
                $payload,
                $request->file('pdf'),
                PrecargaComprobanteOrigenEntrada::PORTAL,
                $proveedorId,
            );

            return response()->json([
                'ok' => true,
                'precarga_id' => $resultado['precarga_id'],
                'message' => $resultado['message'],
                'redirect' => route('portal_proveedores', ['proveedor_id' => $proveedorId]),
            ]);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'portal_confirmar',
                $e->getMessage(),
                422,
                ['proveedor_id' => $proveedorId],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Error al crear la precarga desde el portal.',
            ], 500);
        }
    }

    public function verFactura(Request $request, int $id): BinaryFileResponse
    {
        can('listar-portal-proveedores');

        $proveedorId = (int) $request->input('proveedor_id', 0);
        $precarga = $this->precargaRepository->find($id);

        if ($proveedorId <= 0 || (int) $precarga->proveedor_id !== $proveedorId) {
            abort(403, 'La factura no corresponde al proveedor seleccionado.');
        }

        $path = $this->facturaScanPathResolver->resolve($precarga->rutaalmacenamiento);
        if ($path === null) {
            abort(404, 'No se encontró el PDF de la factura.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    private function assertProveedorExiste(int $proveedorId): void
    {
        if (! $this->proveedorRepository->find($proveedorId)) {
            throw new RuntimeException('Proveedor inexistente.');
        }
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function assertProveedorPreview(array $preview, int $proveedorId): void
    {
        $resuelto = $preview['resuelto'] ?? null;
        if (! is_array($resuelto)) {
            return;
        }

        $detectado = (int) ($resuelto['proveedor_id'] ?? 0);
        if ($detectado > 0 && $detectado !== $proveedorId) {
            throw new RuntimeException(
                'La factura pertenece a otro proveedor. Revise el PDF o la cuenta seleccionada.'
            );
        }
    }
}
