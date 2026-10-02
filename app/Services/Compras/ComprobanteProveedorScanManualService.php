<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Factura cargada sin orden de compra: si el primer archivo es un PDF, se copia a
 * Facturas_scan y queda como scan del comprobante (cuenta corriente, tracking y
 * el resto de las consultas). No es obligatorio para guardar ni para contabilizar.
 */
class ComprobanteProveedorScanManualService
{
    public function __construct(
        private readonly ComprobanteProveedorArchivoPathSupport $archivoPath = new ComprobanteProveedorArchivoPathSupport,
        private readonly PrecargaFacturaScanPathResolver $scanPath = new PrecargaFacturaScanPathResolver,
    ) {}

    public function publicarPrimerAdjunto(Comprobante_Proveedor $comprobante, Request $request): void
    {
        if ((int) ($comprobante->ordencompra_id ?? 0) > 0) {
            return;
        }

        $comprobante->loadMissing([
            'proveedores',
            'tipotransaccion_compras',
            'comprobante_proveedor_archivos',
            'precarga_comprobante_proveedores',
        ]);

        $primerUpload = $this->primerUpload($request);
        if ($primerUpload !== null && ! $this->esPdfUpload($primerUpload)) {
            return;
        }

        if ($this->scanYaLegible($comprobante)) {
            return;
        }

        $origen = null;
        $nombreOriginal = '';
        if ($primerUpload !== null) {
            $nombreOriginal = basename((string) $primerUpload->getClientOriginalName());
            $origen = $this->rutaLocal($comprobante, $nombreOriginal);
        } else {
            $local = $this->primerPdfLocal($comprobante);
            if ($local !== null) {
                $origen = $local['ruta'];
                $nombreOriginal = $local['nombre'];
            }
        }

        if ($origen === null || ! is_readable($origen)) {
            return;
        }

        $publicado = $this->copiarAFacturasScan($comprobante, $origen);
        $this->registrarScan($comprobante, $publicado['referencia'], $publicado['destino'], $publicado['nombre']);

        if ($nombreOriginal !== '') {
            $this->quitarCopiaLocal($comprobante, $nombreOriginal, $origen);
        }
    }

    /**
     * @return array{referencia: string, destino: string, nombre: string}
     */
    public function copiarAFacturasScan(Comprobante_Proveedor $comprobante, string $origenAbsoluto): array
    {
        if (! is_readable($origenAbsoluto) || ! is_file($origenAbsoluto)) {
            throw new RuntimeException('No se encontró el PDF para publicarlo como scan de la factura.');
        }

        $base = $this->scanPath->comprobantesBasePath();
        if ($base === '' || ! is_dir($base) || ! is_writable($base)) {
            throw new RuntimeException(
                'No se pudo guardar el scan de la factura: el montaje Facturas_scan no está disponible.'
            );
        }

        $relativo = $this->archivoPath->relativePathDesdeComprobante($comprobante);
        $destino = rtrim($base, '/').'/'.$relativo;
        $directorio = dirname($destino);
        if (! is_dir($directorio) && ! mkdir($directorio, 0775, true) && ! is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta del scan en Facturas_scan.');
        }

        if (! is_file($destino) || filesize($destino) !== filesize($origenAbsoluto)) {
            $temporal = $destino.'.parcial';
            if (! copy($origenAbsoluto, $temporal)) {
                throw new RuntimeException('No se pudo copiar el PDF de la factura a Facturas_scan.');
            }
            if (filesize($temporal) !== filesize($origenAbsoluto)) {
                @unlink($temporal);
                throw new RuntimeException('La copia del scan quedó incompleta.');
            }
            if (is_file($destino)) {
                @unlink($destino);
            }
            if (! rename($temporal, $destino)) {
                @unlink($temporal);
                throw new RuntimeException('No se pudo dejar el scan en Facturas_scan.');
            }
            @chmod($destino, 0664);
        }

        return [
            'referencia' => 'storage:/comprobantes/'.$relativo,
            'destino' => $destino,
            'nombre' => basename($relativo),
        ];
    }

    private function registrarScan(
        Comprobante_Proveedor $comprobante,
        string $referencia,
        string $destino,
        string $nombre,
    ): void {
        $archivo = Comprobante_Proveedor_Archivo::query()->updateOrCreate(
            [
                'comprobante_proveedor_id' => $comprobante->id,
                'tipo' => ComprobanteProveedorArchivoTipos::ORIGEN_IA,
            ],
            [
                'nombrearchivo' => $nombre,
                'ruta_externa' => $referencia,
                'origen_externo' => true,
            ]
        );

        DB::table('comprobante_tracking_indice')
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->update([
                'pdf_origen' => TrackingPdfReferencia::ORIGEN_ADJUNTO,
                'pdf_documento_id' => null,
                'pdf_archivo_id' => $archivo->id,
                'pdf_ruta' => $destino,
                'pdf_disponible' => true,
                'updated_at' => now(),
            ]);
    }

    private function scanYaLegible(Comprobante_Proveedor $comprobante): bool
    {
        $referencia = ComprobanteProveedorArchivoPathSupport::referenciaPdfPrecarga($comprobante);

        return $referencia !== null
            && $this->archivoPath->absolutePathDesdeStorageReference($referencia) !== null;
    }

    private function primerUpload(Request $request): ?UploadedFile
    {
        $archivos = $request->file('nombrearchivos');
        if ($archivos instanceof UploadedFile) {
            return $archivos->isValid() ? $archivos : null;
        }
        if (! is_array($archivos)) {
            return null;
        }

        foreach ($archivos as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                return $archivo;
            }
        }

        return null;
    }

    private function esPdfUpload(UploadedFile $archivo): bool
    {
        $nombre = strtolower((string) $archivo->getClientOriginalName());
        if (str_ends_with($nombre, '.pdf')) {
            return true;
        }

        // El mime del cliente no abre el temporal. getMimeType() sí, y falla
        // cuando el archivo ya se movió a la carpeta del comprobante.
        $mime = strtolower((string) $archivo->getClientMimeType());

        return str_contains($mime, 'pdf');
    }

    /**
     * @return array{ruta: string, nombre: string}|null
     */
    private function primerPdfLocal(Comprobante_Proveedor $comprobante): ?array
    {
        $filas = $comprobante->comprobante_proveedor_archivos
            ->whereIn('tipo', ComprobanteProveedorArchivoTipos::subibles())
            ->sortBy('id');

        foreach ($filas as $fila) {
            $nombre = basename((string) $fila->nombrearchivo);
            if ($nombre === '' || ! str_ends_with(strtolower($nombre), '.pdf')) {
                continue;
            }
            $ruta = $this->rutaLocal($comprobante, $nombre);
            if (is_file($ruta) && is_readable($ruta)) {
                return ['ruta' => $ruta, 'nombre' => $nombre];
            }
        }

        return null;
    }

    private function rutaLocal(Comprobante_Proveedor $comprobante, string $nombre): string
    {
        return public_path('storage/archivos/comprobantes_proveedor/'.$comprobante->id.'/'.basename($nombre));
    }

    private function quitarCopiaLocal(Comprobante_Proveedor $comprobante, string $nombre, string $ruta): void
    {
        Comprobante_Proveedor_Archivo::query()
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->where('nombrearchivo', $nombre)
            ->whereIn('tipo', ComprobanteProveedorArchivoTipos::subibles())
            ->delete();

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
