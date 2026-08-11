<?php

namespace App\Services\Stock;

use App\Models\Stock\Stock_Etiqueta;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\SalidaImpresionFallbackSupport;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impresión etiquetas Surmar: ZPL a impresora de red (seteosalida) o PDF DomPDF.
 */
class SurmarEtiquetaImpresionService
{
    public const DESTINO_IMPRESORA = 'impresora';

    public const DESTINO_PDF = 'pdf';

    public function __construct(
        private readonly SeteosalidaRepositoryInterface $seteoSalidaRepository,
        private readonly RecepcionProveedorSurmarService $recepcionService,
    ) {
    }

    public function programa(): string
    {
        return SeteoSalidaProgramaSupport::STOCK_ETIQUETA_SURMAR;
    }

    public function destinoDefault(): string
    {
        $d = strtolower(trim((string) config('recepcion_anita_surmar.etiqueta_destino_default', self::DESTINO_IMPRESORA)));

        return in_array($d, [self::DESTINO_IMPRESORA, self::DESTINO_PDF], true)
            ? $d
            : self::DESTINO_IMPRESORA;
    }

    /**
     * @return array{id:int,nombre:string,ubicacion:?string,comando:?string}|null
     */
    public function salidaConfigurada(?int $usuarioId = null): ?array
    {
        $usuarioId = $usuarioId ?: (int) Auth::id();
        $seteo = $this->seteoSalidaRepository->buscaSeteo($usuarioId, $this->programa());
        if (! $seteo || ! $seteo->salidas) {
            return null;
        }
        $salida = $seteo->salidas;
        $salida->loadMissing('ubicacionImpresora');

        return [
            'id' => (int) $salida->id,
            'nombre' => (string) $salida->nombre,
            'ubicacion' => $salida->ubicacionImpresora->nombre ?? null,
            'comando' => (string) $salida->comando,
        ];
    }

    public function buscarEtiqueta(int $etiquetaId): Stock_Etiqueta
    {
        return Stock_Etiqueta::query()
            ->with(['articulos', 'unidadesmedida', 'separaUnidadmedida'])
            ->whereKey($etiquetaId)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->firstOrFail();
    }

    /**
     * Envía ZPL a la impresora configurada (Zebra red / CUPS raw).
     *
     * @param  list<string>|string  $zplOrZpls
     * @return array{ok:bool,mensaje:string,salida:?string}
     */
    public function enviarZplAImpresora(string|array $zplOrZpls, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?: (int) Auth::id();
        $zpls = is_array($zplOrZpls) ? $zplOrZpls : [$zplOrZpls];
        $zpls = array_values(array_filter(array_map('strval', $zpls)));
        if ($zpls === []) {
            return ['ok' => false, 'mensaje' => 'No hay ZPL para imprimir.', 'salida' => null];
        }

        $seteo = $this->seteoSalidaRepository->buscaSeteo($usuarioId, $this->programa());
        if (! $seteo || ! $seteo->salidas) {
            return [
                'ok' => false,
                'mensaje' => 'No hay impresora configurada para etiquetas Surmar. Use «Configura salida».',
                'salida' => null,
            ];
        }

        $salida = $seteo->salidas;
        if (! SalidaImpresionFallbackSupport::comandoImpresionValido($salida)) {
            return [
                'ok' => false,
                'mensaje' => 'El comando de la impresora debe incluir %s (ruta del archivo ZPL). Use bin/imprimir-etiqueta-zebra.sh "%s" HOST_O_COLA.',
                'salida' => (string) $salida->nombre,
            ];
        }

        $contenido = implode("\n", $zpls);
        $rel = 'tmp/eti-surmar-'.Str::random(10).'.txt';
        Storage::disk('local')->put($rel, $contenido);
        $path = Storage::path($rel);

        $exitCode = 0;
        try {
            passthru(sprintf(trim((string) $salida->comando), $path), $exitCode);
        } finally {
            Storage::disk('local')->delete($rel);
        }

        if ($exitCode !== 0) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo enviar a la impresora «'.$salida->nombre.'». Verifique red/CUPS y el comando Zebra.',
                'salida' => (string) $salida->nombre,
            ];
        }

        $n = count($zpls);

        return [
            'ok' => true,
            'mensaje' => $n > 1
                ? ('Se enviaron '.$n.' etiquetas a «'.$salida->nombre.'».')
                : ('Etiqueta enviada a «'.$salida->nombre.'».'),
            'salida' => (string) $salida->nombre,
        ];
    }

    /**
     * @return array{ok:bool,mensaje:string,salida:?string}
     */
    public function imprimirEtiquetaId(int $etiquetaId, int $copias = 1, ?int $usuarioId = null): array
    {
        $etiqueta = $this->buscarEtiqueta($etiquetaId);
        $zpl = $this->recepcionService->zplEtiqueta($etiqueta->id);
        $copias = max(1, min(10, $copias));
        $zpls = array_fill(0, $copias, $zpl);

        return $this->enviarZplAImpresora($zpls, $usuarioId);
    }

    /**
     * Genera PDF de la etiqueta (vista previa imprimible).
     *
     * @return array{path:string,filename:string}
     */
    public function generarPdfEtiqueta(int $etiquetaId): array
    {
        $etiqueta = $this->buscarEtiqueta($etiquetaId);
        $preview = $this->recepcionService->previewDesdeEtiqueta($etiqueta, null);

        $dir = storage_path('pdf/etiquetas_surmar');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw ValidationException::withMessages([
                'pdf' => 'No se pudo crear el directorio de PDF de etiquetas.',
            ]);
        }

        $filename = 'etiqueta_surmar_'.$etiquetaId.'.pdf';
        $path = $dir.'/'.$filename;
        $html = View::make('stock.recepcion_proveedor_surmar.etiqueta_pdf', [
            'preview' => $preview,
            'etiquetaId' => $etiquetaId,
        ])->render();

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper([0, 0, 288, 432], 'portrait'); // ~4x6"
        $pdf->loadHTML($html)->save($path);

        return ['path' => $path, 'filename' => $filename];
    }
}
