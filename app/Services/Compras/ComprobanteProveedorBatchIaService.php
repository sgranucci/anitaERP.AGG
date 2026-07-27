<?php

namespace App\Services\Compras;

use App\Jobs\Compras\ProcesarFacturaBatchIaJob;
use App\Models\Compras\Precarga_Comprobante_Batch_Ia_Archivo;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaProveedor\Batch\FacturaBatchOcExtractorSupport;
use App\Support\Compras\PrecargaRecepcionErrorRegistrar;
use DirectoryIterator;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

/**
 * Carpeta caliente BATCH_IA: reclama PDFs, los encola y reutiliza el flujo
 * PDF+IA para crear precargas PENDIENTE y mover el original a Facturas_scan.
 */
final class ComprobanteProveedorBatchIaService
{
    public function __construct(
        private ComprobanteProveedorPdfIaService $pdfIaService,
        private FacturaBatchOcExtractorSupport $ocExtractor,
    ) {}

    /**
     * @return array{encontrados:int,encolados:int,duplicados:int,detalle:list<array<string,mixed>>}
     */
    public function barrer(?int $limite = null, bool $dryRun = false): array
    {
        $this->asegurarDirectorios();
        $limite = $limite ?? (int) config('precarga_comprobante_batch_ia.max_archivos', 20);
        $resultado = ['encontrados' => 0, 'encolados' => 0, 'duplicados' => 0, 'detalle' => []];

        foreach (new DirectoryIterator($this->dir('entrada')) as $archivo) {
            if ($archivo->isDot() || ! $archivo->isFile() || strtolower($archivo->getExtension()) !== 'pdf') {
                continue;
            }

            $origen = $archivo->getPathname();
            $nombre = $archivo->getFilename();
            $estabilidad = (int) config('precarga_comprobante_batch_ia.estabilidad_segundos', 3);
            if ($estabilidad > 0) {
                $mtime = $archivo->getMTime();
                if ($mtime !== false && (time() - $mtime) < $estabilidad) {
                    $resultado['detalle'][] = ['archivo' => $nombre, 'accion' => 'espera_escritura'];
                    continue;
                }
            }

            if ($resultado['encontrados'] >= $limite) {
                break;
            }

            $resultado['encontrados']++;
            $hash = hash_file('sha256', $origen);
            if (! is_string($hash)) {
                $resultado['detalle'][] = ['archivo' => $nombre, 'accion' => 'error_hash'];
                continue;
            }

            $existente = Precarga_Comprobante_Batch_Ia_Archivo::query()
                ->where('archivo_hash', $hash)
                ->first();
            if ($existente) {
                $resultado['duplicados']++;
                $resultado['detalle'][] = ['archivo' => $nombre, 'accion' => 'duplicado', 'id' => $existente->id];
                if (! $dryRun) {
                    $this->mover($origen, $this->destinoUnico('archivo', 'DUPLICADO_'.$nombre));
                }
                continue;
            }

            $resultado['detalle'][] = [
                'archivo' => $nombre,
                'accion' => $dryRun ? 'dry_run' : 'encolado',
                'numero_oc_nombre' => $this->ocExtractor->extraer($nombre),
            ];
            if ($dryRun) {
                continue;
            }

            $rutaProcesando = $this->destinoUnico('procesando', $hash.'__'.$nombre);
            if (! @rename($origen, $rutaProcesando)) {
                throw new RuntimeException('No se pudo reclamar el PDF BATCH_IA: '.$nombre);
            }

            $registro = Precarga_Comprobante_Batch_Ia_Archivo::query()->create([
                'archivo_nombre' => $nombre,
                'archivo_hash' => $hash,
                'ruta_procesando' => $rutaProcesando,
                'estado' => Precarga_Comprobante_Batch_Ia_Archivo::ESTADO_ENCOLADO,
                'numero_oc' => $this->ocExtractor->extraer($nombre),
            ]);

            ProcesarFacturaBatchIaJob::dispatch((int) $registro->id)
                ->onQueue((string) config('precarga_comprobante_batch_ia.cola', 'default'));
            $resultado['encolados']++;
        }

        return $resultado;
    }

    public function procesar(int $registroId): void
    {
        $registro = Precarga_Comprobante_Batch_Ia_Archivo::query()->findOrFail($registroId);
        if ($registro->estado === Precarga_Comprobante_Batch_Ia_Archivo::ESTADO_PROCESADO) {
            return;
        }

        $ruta = (string) $registro->ruta_procesando;
        $nombre = (string) $registro->archivo_nombre;
        $rutaArchivo = null;
        $registro->update(['estado' => Precarga_Comprobante_Batch_Ia_Archivo::ESTADO_PROCESANDO]);

        try {
            if (! is_file($ruta)) {
                throw new RuntimeException('No se encontró el PDF reclamado: '.$ruta);
            }

            // Copia de auditoría del ingreso; el original será movido por Facturas_scan.
            $rutaArchivo = $this->destinoUnico('archivo', $nombre);
            if (! copy($ruta, $rutaArchivo)) {
                throw new RuntimeException('No se pudo archivar copia del PDF BATCH_IA.');
            }

            $pdf = new UploadedFile($ruta, $nombre, 'application/pdf', null, true);
            $preview = $this->pdfIaService->preview($pdf, null, true);
            $ocDesdeNombre = false;

            if (empty($preview['ok'])) {
                if (! empty($preview['oc_requerida']) && filled($registro->numero_oc)) {
                    $previewOriginal = $preview;
                    $preview = $this->pdfIaService->resolverConOcManual(
                        (array) ($preview['extraccion'] ?? []),
                        (string) $registro->numero_oc,
                    );
                    foreach (['ai_decision_id', 'ai_score', 'ai_auto_aplicable'] as $claveIa) {
                        if (array_key_exists($claveIa, $previewOriginal)) {
                            $preview[$claveIa] = $previewOriginal[$claveIa];
                        }
                    }
                    $ocDesdeNombre = true;
                } else {
                    throw new RuntimeException((string) ($preview['message'] ?? 'No se pudo interpretar el PDF.'));
                }
            }

            $resuelto = (array) ($preview['resuelto'] ?? []);
            $autoAplicable = ! empty($preview['ai_auto_aplicable']) && ! $ocDesdeNombre;
            $forzarRevision = $ocDesdeNombre
                || ! empty($resuelto['pararevisar'])
                || blank($resuelto['numerocae'] ?? null)
                || (array) ($preview['advertencias'] ?? []) !== []
                || ! $autoAplicable;
            if ($forzarRevision) {
                $autoAplicable = false;
            }
            $preview['ai_auto_aplicable'] = $autoAplicable;

            $confirmacion = $this->pdfIaService->confirmar(
                $preview,
                $pdf,
                PrecargaComprobanteOrigenEntrada::BATCH_IA,
                null,
                $forzarRevision,
            );

            $registro->update([
                'estado' => Precarga_Comprobante_Batch_Ia_Archivo::ESTADO_PROCESADO,
                'numero_oc' => $resuelto['numero_oc'] ?? $registro->numero_oc,
                'precarga_id' => (int) $confirmacion['precarga_id'],
                'ruta_archivo' => $rutaArchivo,
                'ruta_procesando' => null,
                'mensaje_error' => $forzarRevision
                    ? 'Precarga creada para revisión humana.'
                    : 'Auto-aplicada (score ≥ umbral).',
            ]);
        } catch (Throwable $e) {
            if ($rutaArchivo !== null && is_file($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
            $rutaError = $this->destinoUnico('errores', $nombre);
            if (is_file($ruta)) {
                $this->mover($ruta, $rutaError);
            }

            $registro->update([
                'estado' => Precarga_Comprobante_Batch_Ia_Archivo::ESTADO_ERROR,
                'ruta_procesando' => is_file($rutaError) ? $rutaError : $ruta,
                'mensaje_error' => mb_substr($e->getMessage(), 0, 5000),
            ]);

            PrecargaRecepcionErrorRegistrar::registrarBatchIa('procesar_archivo', $e->getMessage(), [
                'numero_oc' => $registro->numero_oc,
                'archivo_nombre' => $nombre,
                'archivo_hash' => $registro->archivo_hash,
                'ruta_error' => $rutaError,
            ]);
        }
    }

    public function asegurarDirectorios(): void
    {
        foreach (['entrada', 'procesando', 'errores', 'archivo'] as $clave) {
            $dir = $this->dir($clave);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException('No se pudo crear directorio BATCH_IA: '.$dir);
            }
        }
    }

    private function dir(string $clave): string
    {
        $base = rtrim((string) config('precarga_comprobante_batch_ia.base_path'), '/');
        $subdir = trim((string) config('precarga_comprobante_batch_ia.'.$clave, $clave), '/');

        return $base.'/'.$subdir;
    }

    private function destinoUnico(string $carpeta, string $nombre): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?: 'factura.pdf';
        $ruta = $this->dir($carpeta).'/'.$nombre;
        if (! file_exists($ruta)) {
            return $ruta;
        }

        return $this->dir($carpeta).'/'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'_'.$nombre;
    }

    private function mover(string $origen, string $destino): void
    {
        if (! @rename($origen, $destino)) {
            throw new RuntimeException('No se pudo mover '.$origen.' → '.$destino);
        }
    }
}
