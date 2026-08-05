<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Adjunta PDF de factura al legajo (OC) creando o actualizando una precarga mínima.
 */
class OrdencompraLegajoFacturaPdfService
{
    public function __construct(
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private PrecargaFacturaScanPathResolver $scanPathResolver = new PrecargaFacturaScanPathResolver(),
    ) {}

    public function adjuntarPdfAlLegajo(Ordencompra $oc, UploadedFile $pdf): Precarga_Comprobante_Proveedor
    {
        $ext = strtolower((string) $pdf->getClientOriginalExtension());
        $mime = strtolower((string) $pdf->getMimeType());
        if (! $pdf->isValid() || ($ext !== 'pdf' && ! str_contains($mime, 'pdf'))) {
            throw new RuntimeException('Debe adjuntar un archivo PDF válido de la factura.');
        }

        $proveedorId = (int) ($oc->proveedor_id ?? 0);
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            throw new RuntimeException('La orden de compra debe tener empresa y proveedor para adjuntar la factura.');
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor de la orden de compra inexistente.');
        }

        $ruta = $this->guardarPdfLegajo($pdf, $oc, $proveedor);

        $existente = OrdencompraEnvioCuentasAPagarGateSupport::precargaDelLegajoSinPdf($oc)
            ?? OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);

        if ($existente) {
            $this->precargaRepository->update([
                'empresa_id' => $empresaId,
                'proveedor_id' => $proveedorId,
                'tipotransaccion_compra_id' => (int) ($existente->tipotransaccion_compra_id
                    ?: OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraDefaultId()),
                'letra' => (string) ($existente->letra ?: 'A'),
                'sucursal' => (int) ($existente->sucursal ?? 0),
                'numerocomprobante' => (int) ($existente->numerocomprobante ?? 0),
                'numeroordencompra' => (string) $oc->numeroordencompra,
                'rutaalmacenamiento' => $ruta,
                'estado' => $existente->estado ?: 'PENDIENTE',
                'pararevisar' => 1,
            ], $existente->id);

            return Precarga_Comprobante_Proveedor::query()->findOrFail($existente->id);
        }

        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraDefaultId();
        if ($tipoId <= 0) {
            throw new RuntimeException('No hay tipo de transacción de compra configurado para la precarga.');
        }

        $numeroProvisorio = $this->numeroProvisorioUnico($empresaId, $tipoId, $proveedorId);

        return $this->precargaRepository->create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId,
            'tipotransaccion_compra_id' => $tipoId,
            'letra' => 'A',
            'sucursal' => 0,
            'numerocomprobante' => $numeroProvisorio,
            'fechafactura' => now()->format('Y-m-d'),
            'numeroordencompra' => (string) $oc->numeroordencompra,
            'rutaalmacenamiento' => $ruta,
            'subtotal' => 0,
            'total' => 0,
            'estado' => 'PENDIENTE',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::MANUAL,
            'pararevisar' => 1,
            'cotizacion' => 1,
        ]);
    }

    private function guardarPdfLegajo(UploadedFile $pdf, Ordencompra $oc, Proveedor $proveedor): string
    {
        $comprobantesBase = $this->scanPathResolver->comprobantesBasePath();
        if ($comprobantesBase === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $cuit = ComprobanteProveedorArchivoPathSupport::cuitCarpeta($proveedor->nroinscripcion);
        $ym = now()->format('Y-m');
        $nombre = sprintf(
            'LEG-OC-%s-%s.pdf',
            preg_replace('/\D/', '', (string) $oc->numeroordencompra) ?: (string) $oc->id,
            now()->format('YmdHis'),
        );
        $relative = $cuit.'/'.$ym.'/'.$nombre;
        $destDir = rtrim($comprobantesBase, '/').'/'.$cuit.'/'.$ym;

        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        $pdf->move($destDir, $nombre);
        if (! is_readable($destDir.'/'.$nombre)) {
            throw new RuntimeException('No se pudo grabar el PDF en Facturas_scan.');
        }

        return 'storage:/comprobantes/'.$relative;
    }

    private function numeroProvisorioUnico(int $empresaId, int $tipoId, int $proveedorId): int
    {
        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        for ($i = 0; $i < 20; $i++) {
            $nro = ((int) now()->format('ymdHis') * 10 + $i) % 2000000000;
            if ($nro <= 0) {
                $nro = random_int(1, 1999999999);
            }
            $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
                $empresaId,
                $tipoId,
                'A',
                0,
                $nro,
                $cuit,
            );
            if ($dup === null) {
                return $nro;
            }
        }

        throw new RuntimeException('No se pudo generar un número provisorio único para la precarga del legajo.');
    }
}
