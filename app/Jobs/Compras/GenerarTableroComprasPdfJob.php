<?php

declare(strict_types=1);

namespace App\Jobs\Compras;

use App\Mail\Compras\TableroComprasPdfListoMail;
use App\Models\Seguridad\Usuario;
use App\Services\Compras\ComprasTableroOcPdfService;
use App\Services\Compras\ComprasTableroOcService;
use App\Support\Compras\ComprasTableroOcFiltros;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * PDF del detalle del tablero de compras con muchas líneas: se arma en la cola y llega por mail.
 * Hasta `compras.tablero_pdf.max_filas_sincrono` líneas sale al instante y no usa este job.
 */
class GenerarTableroComprasPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DIRECTORIO_PUBLICO = 'exports/tablero_compras_async';

    /** Días que se conservan los PDF generados para descargar por enlace. */
    private const DIAS_CONSERVAR = 7;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor = 7200;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public readonly array $filtros,
        public readonly string $subtitulo,
        public readonly int $usuarioId,
    ) {
        $this->timeout = (int) config('compras.tablero_pdf.job_timeout', 3600);
        $this->onQueue((string) config('compras.tablero_pdf.cola', 'reports'));
    }

    public function uniqueId(): string
    {
        return 'tablero-compras-pdf-'.$this->usuarioId.'-'.md5(json_encode(ComprasTableroOcFiltros::paraQueryString($this->filtros)));
    }

    public function handle(ComprasTableroOcService $service, ComprasTableroOcPdfService $pdfService): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit($this->timeout);

        $usuario = Usuario::query()->find($this->usuarioId);
        $email = trim((string) ($usuario->email ?? ''));
        $periodo = ComprasTableroOcFiltros::textoPeriodo($this->filtros);

        Log::info('tablero_compras.pdf.inicio', ['usuario_id' => $this->usuarioId, 'periodo' => $periodo]);

        try {
            $filas = $service->generar($this->filtros)['detalle'];
            $ruta = $pdfService->generar($filas, 'Compras: detalle de líneas de órdenes de compra', $this->subtitulo);

            $this->limpiarAntiguos();
            $nombre = 'tablero_compras_'.now()->format('Ymd_His').'_'.Str::random(16).'.pdf';
            $relativa = self::DIRECTORIO_PUBLICO.'/'.$nombre;
            $absoluta = storage_path('app/public/'.$relativa);
            $dir = dirname($absoluta);
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('No se pudo crear el directorio de descarga.');
            }
            if (! rename($ruta, $absoluta)) {
                throw new \RuntimeException('No se pudo guardar el PDF generado.');
            }

            $bytes = (int) filesize($absoluta);
            $maxAdjunto = (int) config('compras.tablero_pdf.adjunto_max_mb', 8) * 1048576;
            $adjunta = $bytes > 0 && $bytes <= $maxAdjunto;

            $this->enviarMail($email, [
                'ok' => true,
                'periodo' => $periodo,
                'subtitulo' => $this->subtitulo,
                'lineas' => count($filas),
                'mensaje' => $adjunta
                    ? 'Adjuntamos el PDF; también podés descargarlo con el enlace (disponible '.self::DIAS_CONSERVAR.' días).'
                    : 'El PDF pesa demasiado para adjuntarlo; descargalo con el enlace (disponible '.self::DIAS_CONSERVAR.' días).',
                'url_descarga' => urlAppAbsoluta('storage/'.$relativa),
                'nombre_archivo' => $nombre,
                'adjunto_path' => $adjunta ? $absoluta : '',
                'usuario_nombre' => (string) ($usuario->nombre ?? ''),
            ]);

            Log::info('tablero_compras.pdf.fin', [
                'usuario_id' => $this->usuarioId,
                'lineas' => count($filas),
                'bytes' => $bytes,
                'archivo' => $relativa,
            ]);
        } catch (Throwable $e) {
            Log::error('tablero_compras.pdf.error', ['usuario_id' => $this->usuarioId, 'error' => $e->getMessage()]);

            $this->enviarMail($email, [
                'ok' => false,
                'periodo' => $periodo,
                'subtitulo' => $this->subtitulo,
                'lineas' => 0,
                'mensaje' => 'No se pudo generar el PDF: '.$e->getMessage(),
                'usuario_nombre' => (string) ($usuario->nombre ?? ''),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('tablero_compras.pdf.failed', ['usuario_id' => $this->usuarioId, 'error' => $e?->getMessage()]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function enviarMail(string $email, array $datos): void
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('tablero_compras.pdf.sin_email', ['usuario_id' => $this->usuarioId]);

            return;
        }

        Mail::to($email)->send(new TableroComprasPdfListoMail($datos));
    }

    /** Borra los PDF de más de DIAS_CONSERVAR días de este mismo directorio. */
    private function limpiarAntiguos(): void
    {
        $limite = now()->subDays(self::DIAS_CONSERVAR)->getTimestamp();
        foreach (glob(storage_path('app/public/'.self::DIRECTORIO_PUBLICO.'/tablero_compras_*.pdf')) ?: [] as $archivo) {
            if (is_file($archivo) && filemtime($archivo) < $limite) {
                @unlink($archivo);
            }
        }
    }
}
