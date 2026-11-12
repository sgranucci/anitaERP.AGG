<?php

namespace App\Support\Compras;

use App\Models\Configuracion\Empresa;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTextoExtractor;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Empresa del grupo a la que está emitida una factura, leída del PDF (CUIT o razón social).
 * Solo responde cuando aparece una única empresa: con ninguna o varias (factura intercompany,
 * PDF ilegible) no se puede afirmar nada y no se bloquea.
 */
class ComprobanteProveedorReceptorPdfSupport
{
    public function __construct(
        private RecepcionProveedorOcrTextoExtractor $extractor,
    ) {}

    public function empresaReceptoraDetectada(string $rutaPdf): ?int
    {
        try {
            $texto = $this->extractor->extraer($rutaPdf, 'application/pdf');
        } catch (Throwable $e) {
            Log::info('receptor_pdf.sin_texto', ['ruta' => $rutaPdf, 'error' => $e->getMessage()]);

            return null;
        }

        return self::empresaDesdeTexto($texto);
    }

    public static function empresaDesdeTexto(string $texto): ?int
    {
        $digitos = preg_replace('/(?<=\d)[\s.\-\/]+(?=\d)/', '', $texto) ?? $texto;
        $plano = ' '.self::normalizarNombre($texto).' ';

        $encontradas = [];
        foreach (Empresa::query()->get(['id', 'nombre', 'nroinscripcion']) as $empresa) {
            $cuit = preg_replace('/\D/', '', (string) $empresa->nroinscripcion) ?? '';
            $nombre = self::normalizarNombre((string) $empresa->nombre);
            if ((strlen($cuit) === 11 && str_contains($digitos, $cuit))
                || (strlen($nombre) >= 4 && str_contains($plano, ' '.$nombre.' '))) {
                $encontradas[] = (int) $empresa->id;
            }
        }

        return count($encontradas) === 1 ? $encontradas[0] : null;
    }

    /** @throws RuntimeException */
    public function assertReceptorEs(string $rutaPdf, int $empresaId, string $contexto): void
    {
        $detectada = $this->empresaReceptoraDetectada($rutaPdf);
        if ($detectada === null || $detectada === $empresaId) {
            return;
        }

        $nombre = static fn (int $id) => (string) (Empresa::query()->whereKey($id)->value('nombre') ?: 'empresa #'.$id);

        throw new RuntimeException(sprintf(
            'La factura está emitida a %s pero %s es de %s: cargala en la empresa receptora, con una OC de esa empresa.',
            $nombre($detectada),
            $contexto,
            $nombre($empresaId),
        ));
    }

    private static function normalizarNombre(string $texto): string
    {
        $t = mb_strtoupper($texto);
        $t = preg_replace('/\bS\.?\s?A\.?(?=\s|$)|\bS\.?\s?R\.?\s?L\.?(?=\s|$)|\bS\.?\s?A\.?\s?S\.?(?=\s|$)/u', ' ', $t) ?? $t;
        $t = preg_replace('/[^A-Z0-9ÁÉÍÓÚÑ]+/u', ' ', $t) ?? $t;

        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    }
}
