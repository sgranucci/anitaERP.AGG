<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Graba PDFs de factura en {Facturas_scan}/comprobantes/{CUIT}/{Y-m}/{TIPO}-{letra}-{suc}-{nro}.pdf
 * (misma convención que la precarga vía API / agente externo).
 */
final class ComprobanteProveedorFacturaScanStorageService
{
    public function __construct(
        private PrecargaFacturaScanPathResolver $scanPathResolver = new PrecargaFacturaScanPathResolver(),
    ) {}

    /**
     * @param  array<string, mixed>  $resuelto  Payload «resuelto» del preview PDF+IA
     */
    public function guardarPdfPrecarga(UploadedFile $pdf, array $resuelto): string
    {
        if (! $pdf->isValid()) {
            throw new RuntimeException('Archivo PDF inválido.');
        }

        $proveedorId = (int) ($resuelto['proveedor_id'] ?? 0);
        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor inexistente para almacenar el PDF en Facturas_scan.');
        }

        $relative = $this->relativePathDesdeResuelto($resuelto, $proveedor->nroinscripcion);
        $storageRef = 'storage:/comprobantes/'.$relative;

        $comprobantesBase = $this->scanPathResolver->comprobantesBasePath();
        if ($comprobantesBase === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $destDir = rtrim($comprobantesBase, '/').'/'.dirname($relative);
        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        $nombre = basename($relative);
        $pdf->move($destDir, $nombre);

        if (! is_readable($destDir.'/'.$nombre)) {
            throw new RuntimeException('No se pudo grabar el PDF en Facturas_scan.');
        }

        return $storageRef;
    }

    /**
     * @param  array<string, mixed>  $resuelto
     */
    public function relativePathDesdeResuelto(array $resuelto, ?string $nroInscripcionProveedor): string
    {
        $cuit = ComprobanteProveedorArchivoPathSupport::cuitCarpeta($nroInscripcionProveedor);
        $fechaRaw = $resuelto['fecha_factura'] ?? null;
        $fecha = filled($fechaRaw) ? Carbon::parse($fechaRaw) : now();
        $nombre = ComprobanteProveedorArchivoPathSupport::nombrePdfComprobante(
            (string) ($resuelto['tipo_abreviatura'] ?? 'FAC'),
            (string) ($resuelto['letra'] ?? 'A'),
            (int) ($resuelto['sucursal'] ?? 0),
            (int) ($resuelto['numero_factura'] ?? 0),
        );

        return $cuit.'/'.$fecha->format('Y-m').'/'.$nombre;
    }

    public function storageReferenceDesdeResuelto(array $resuelto, ?string $nroInscripcionProveedor): string
    {
        return 'storage:/comprobantes/'.$this->relativePathDesdeResuelto($resuelto, $nroInscripcionProveedor);
    }
}
