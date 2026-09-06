<?php

namespace App\Services\Compras\Tracking;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copia los PDF escaneados del Anita al repositorio propio del ERP.
 *
 * El acervo histórico vive en `/scan/compras/documentos` (share CIFS
 * `//10.20.30.37/Scan`) con nombres opacos (`docu_0000346354.pdf`). El ERP
 * guarda los PDF propios en `/Facturas_scan/comprobantes` (otro share del
 * mismo NAS: `//10.20.30.37/Facturas_scan`).
 *
 * Son el mismo servidor pero montajes distintos: hardlink y symlink entre
 * shares CIFS no funcionan. La única forma de dejar el PDF bajo la convención
 * del ERP (CUIT / año-mes / tipo-letra-número) es copiarlo. El origen no se
 * borra: la copia es reintentable y el Anita queda intacto.
 *
 * Mientras tanto el tracking ya sirve el PDF desde `/scan` vía el índice; esta
 * migración sólo independiza el acervo del montaje legacy.
 */
class TrackingPdfMigradorService
{
    private const RELACIONES = [
        'proveedores',
        'tipotransaccion_compras',
        'comprobante_proveedor_archivos',
    ];

    public function __construct(
        private readonly ComprobanteProveedorArchivoPathSupport $archivoPath = new ComprobanteProveedorArchivoPathSupport,
        private readonly PrecargaFacturaScanPathResolver $scanPath = new PrecargaFacturaScanPathResolver,
    ) {}

    /**
     * @param  callable(int, int): void|null  $progreso
     * @return array{candidatos: int, copiados: int, ya_estaban: int, sin_origen: int, errores: int, bytes: int, detalle_errores: list<string>}
     */
    public function migrar(
        int $tamanoLote = 100,
        ?int $limite = null,
        bool $simular = false,
        ?callable $progreso = null,
    ): array {
        $stats = [
            'candidatos' => 0, 'copiados' => 0, 'ya_estaban' => 0,
            'sin_origen' => 0, 'errores' => 0, 'bytes' => 0, 'detalle_errores' => [],
        ];

        if (! $this->destinoDisponible()) {
            $stats['detalle_errores'][] = 'El montaje Facturas_scan/comprobantes no está disponible para escritura.';
            $stats['errores']++;

            return $stats;
        }

        $total = $this->consultaCandidatos()->count();
        if ($limite !== null) {
            $total = min($total, $limite);
        }

        $this->consultaCandidatos()
            ->with(self::RELACIONES)
            ->chunkById($tamanoLote, function (Collection $lote) use (&$stats, $total, $progreso, $limite, $simular) {
                foreach ($lote as $comprobante) {
                    $this->migrarUno($comprobante, $simular, $stats);
                    $stats['candidatos']++;

                    if ($limite !== null && $stats['candidatos'] >= $limite) {
                        break;
                    }
                }

                if ($progreso !== null) {
                    $progreso($stats['candidatos'], $total);
                }

                return $limite === null || $stats['candidatos'] < $limite;
            }, 'comprobante_proveedor.id', 'id');

        return $stats;
    }

    /**
     * Comprobantes cuyo PDF sigue viviendo sólo en el escaneo del Anita.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Comprobante_Proveedor>
     */
    private function consultaCandidatos()
    {
        return Comprobante_Proveedor::query()
            ->join(
                'comprobante_tracking_indice as i',
                'i.comprobante_proveedor_id',
                '=',
                'comprobante_proveedor.id'
            )
            ->where('i.pdf_origen', TrackingPdfReferencia::ORIGEN_ANITA)
            ->where('i.pdf_disponible', true)
            ->select('comprobante_proveedor.*');
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrarUno(Comprobante_Proveedor $comprobante, bool $simular, array &$stats): void
    {
        $origen = (string) DB::table('comprobante_tracking_indice')
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->value('pdf_ruta');

        if ($origen === '' || ! is_readable($origen)) {
            $stats['sin_origen']++;

            return;
        }

        $relativo = $this->archivoPath->relativePathDesdeComprobante($comprobante);
        $destino = rtrim($this->scanPath->comprobantesBasePath(), '/').'/'.$relativo;
        $referencia = $this->archivoPath->storageReferenceDesdeComprobante($comprobante);

        // Si el destino ya tiene el mismo contenido, la corrida anterior lo
        // dejó hecho: se registra el vínculo si falta y se sigue.
        if (is_file($destino) && filesize($destino) === filesize($origen)) {
            $stats['ya_estaban']++;
            if (! $simular) {
                $this->registrarArchivo($comprobante, $referencia, $destino, basename($relativo));
            }

            return;
        }

        if ($simular) {
            $stats['copiados']++;
            $stats['bytes'] += (int) filesize($origen);

            return;
        }

        try {
            $directorio = dirname($destino);
            if (! is_dir($directorio) && ! mkdir($directorio, 0775, true) && ! is_dir($directorio)) {
                throw new \RuntimeException('No se pudo crear el directorio '.$directorio);
            }

            // Se escribe a un temporal en el mismo directorio y se renombra:
            // sobre CIFS una copia interrumpida dejaría un PDF truncado que
            // después parecería válido.
            $temporal = $destino.'.parcial';
            if (! copy($origen, $temporal)) {
                throw new \RuntimeException('Falló la copia a '.$temporal);
            }
            if (filesize($temporal) !== filesize($origen)) {
                @unlink($temporal);
                throw new \RuntimeException('La copia quedó incompleta para '.$origen);
            }
            if (! rename($temporal, $destino)) {
                @unlink($temporal);
                throw new \RuntimeException('No se pudo renombrar '.$temporal);
            }

            $this->registrarArchivo($comprobante, $referencia, $destino, basename($relativo));

            $stats['copiados']++;
            $stats['bytes'] += (int) filesize($destino);
        } catch (\Throwable $e) {
            $stats['errores']++;
            if (count($stats['detalle_errores']) < 20) {
                $stats['detalle_errores'][] = 'Comprobante '.$comprobante->id.': '.$e->getMessage();
            }
            Log::warning('tracking_facturas.migrar_pdf', [
                'comprobante_id' => $comprobante->id,
                'origen' => $origen,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deja el PDF registrado como archivo del comprobante y repunta el índice.
     *
     * A partir de acá el resolutor lo encuentra en el primer paso de la cascada
     * y ya no necesita el puente ni el montaje del Anita.
     *
     * Va como ORIGEN_IA porque ése es el lugar del «PDF de la factura» en todo
     * el ERP: la solapa de archivos lo muestra como tal, el alta de caja lo
     * levanta de ahí y el borrado manual lo tiene protegido. Un tipo nuevo
     * quedaría invisible para toda esa maquinaria.
     */
    private function registrarArchivo(
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
                'pdf_archivo_id' => $archivo->id,
                'pdf_ruta' => $destino,
                'pdf_disponible' => true,
                'updated_at' => now(),
            ]);
    }

    private function destinoDisponible(): bool
    {
        $base = $this->scanPath->comprobantesBasePath();

        return $base !== '' && is_dir($base) && is_writable($base);
    }
}
