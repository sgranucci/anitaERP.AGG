<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor_Archivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Punto de extensión para OCR automatizado desde foto/PDF.
 * Driver stub: persiste archivo y deja estado PENDIENTE para procesamiento batch.
 */
class RecepcionProveedorOcrService
{
    /** @return array{archivo_id: int, ocr_estado: string, lineas: list<array<string, mixed>>} */
    public function procesarArchivo(int $recepcionId, UploadedFile $archivo): array
    {
        if (! config('recepcion_proveedor.ocr.habilitado')) {
            throw new \RuntimeException('OCR de recepción no habilitado. Active RECEPCION_PROVEEDOR_OCR_HABILITADO.');
        }

        $nombre = $archivo->getClientOriginalName();
        $ruta = $archivo->store('recepcion_proveedor/ocr/'.date('Y/m'), 'local');

        $registro = Recepcion_Proveedor_Archivo::create([
            'recepcion_proveedor_id' => $recepcionId,
            'nombre' => $nombre,
            'ruta' => $ruta,
            'tipo_archivo' => Recepcion_Proveedor_Archivo::TIPO_OCR,
            'mime' => $archivo->getMimeType(),
            'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PENDIENTE,
        ]);

        $driver = config('recepcion_proveedor.ocr.driver', 'stub');

        if ($driver === 'stub') {
            return [
                'archivo_id' => $registro->id,
                'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PENDIENTE,
                'lineas' => [],
            ];
        }

        return $this->invocarDriver($driver, $registro);
    }

    /** @return array{archivo_id: int, ocr_estado: string, lineas: list<array<string, mixed>>} */
    private function invocarDriver(string $driver, Recepcion_Proveedor_Archivo $archivo): array
    {
        // Hook para integrar Tesseract, Google Vision, Azure Form Recognizer, etc.
        throw new \RuntimeException("Driver OCR '{$driver}' no implementado aún.");
    }

    public function rutaAbsoluta(Recepcion_Proveedor_Archivo $archivo): string
    {
        return Storage::disk('local')->path($archivo->ruta);
    }
}
