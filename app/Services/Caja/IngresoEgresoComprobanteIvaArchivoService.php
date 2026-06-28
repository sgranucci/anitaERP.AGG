<?php

namespace App\Services\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PDF de comprobantes IVA desde IE: Facturas_scan + registro ORIGEN_IA (mismo patrón que comprobante proveedor).
 */
class IngresoEgresoComprobanteIvaArchivoService
{
    private const TEMP_DIR = 'ie_comprobante_iva_temp';

    public function __construct(
        private ComprobanteProveedorArchivoPathSupport $archivoPathSupport = new ComprobanteProveedorArchivoPathSupport(),
        private PrecargaFacturaScanPathResolver $scanPathResolver = new PrecargaFacturaScanPathResolver(),
    ) {}

    public function guardarTempDesdeUpload(UploadedFile $pdf): string
    {
        if (! $pdf->isValid()) {
            throw new RuntimeException('Archivo PDF inválido.');
        }

        $tempId = (string) Str::uuid();
        $pdf->storeAs(self::TEMP_DIR, $tempId.'.pdf', 'local');

        return $tempId;
    }

    public function persistirDesdePayload(Comprobante_Proveedor $comprobante, array $payload): void
    {
        $tempId = trim((string) ($payload['pdf_temp_id'] ?? ''));
        if ($tempId !== '') {
            $this->persistirDesdeTemp($comprobante, $tempId);

            return;
        }

        if (! empty($payload['pdf_base64'])) {
            $this->persistirDesdeBase64($comprobante, (string) $payload['pdf_base64']);

            return;
        }
    }

    public function persistirDesdeTemp(Comprobante_Proveedor $comprobante, string $tempId): void
    {
        $tempId = basename($tempId);
        $rutaTemp = self::TEMP_DIR.'/'.$tempId.'.pdf';

        if (! Storage::disk('local')->exists($rutaTemp)) {
            throw new RuntimeException('No se encontró el PDF temporal («'.$tempId.'»). Vuelva a cargar el archivo.');
        }

        $contenido = Storage::disk('local')->get($rutaTemp);
        $this->grabarEnFacturasScan($comprobante, $contenido);
        Storage::disk('local')->delete($rutaTemp);
    }

    private function persistirDesdeBase64(Comprobante_Proveedor $comprobante, string $base64): void
    {
        $raw = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $base64) ?? '', true);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('PDF en base64 inválido.');
        }

        $this->grabarEnFacturasScan($comprobante, $raw);
    }

    private function grabarEnFacturasScan(Comprobante_Proveedor $comprobante, string $contenidoPdf): void
    {
        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);

        $relative = $this->archivoPathSupport->relativePathDesdeComprobante($comprobante);
        $storageRef = 'storage:/comprobantes/'.$relative;

        $base = $this->scanPathResolver->comprobantesBasePath();
        if ($base === '') {
            throw new RuntimeException('Montaje Facturas_scan no configurado (PRECARGA_FACTURAS_SCAN_BASE).');
        }

        $destDir = rtrim($base, '/').'/'.dirname($relative);
        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException('No se pudo crear el directorio en Facturas_scan.');
        }

        $nombre = basename($relative);
        $destPath = $destDir.'/'.$nombre;

        if (file_put_contents($destPath, $contenidoPdf) === false) {
            throw new RuntimeException('No se pudo grabar el PDF en Facturas_scan.');
        }

        Comprobante_Proveedor_Archivo::query()
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->where('tipo', ComprobanteProveedorArchivoTipos::ORIGEN_IA)
            ->delete();

        Comprobante_Proveedor_Archivo::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'tipo' => ComprobanteProveedorArchivoTipos::ORIGEN_IA,
            'nombrearchivo' => $nombre,
            'origen_externo' => true,
            'ruta_externa' => $storageRef,
        ]);

        $this->copiaLocalPublica($comprobante, $contenidoPdf, $nombre);
    }

    private function copiaLocalPublica(Comprobante_Proveedor $comprobante, string $contenidoPdf, string $nombre): void
    {
        $path = public_path('storage/archivos/comprobantes_proveedor/'.$comprobante->id);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($path.'/'.$nombre, $contenidoPdf);
    }
}
