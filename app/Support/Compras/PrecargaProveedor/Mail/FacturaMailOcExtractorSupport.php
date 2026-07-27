<?php

namespace App\Support\Compras\PrecargaProveedor\Mail;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use Throwable;

/**
 * Saca el número de OC del mail: primero asunto, después cuerpo,
 * por último el nombre del adjunto. Formatos aceptados:
 * "OC 222102", "O.C. 222102", "OC: 222102", "OC#222102",
 * "orden de compra 222102", "Nro O.C.: 222102", "OC Nº 222102".
 */
final class FacturaMailOcExtractorSupport
{
    /**
     * Rótulo de OC + 1 a 6 dígitos (evita agarrar CUIT o número de factura).
     */
    private const PATRON_OC = '/(?:\borden\s+de\s+compra\b|(?<![a-z])(?:nro\.?|n[°ºo]\.?)?\s*o\.?\s*c\.?)\s*(?:nro\.?|n[°ºo]\.?)?\s*[:#\-]?\s*(\d{1,6})\b/iu';

    public function __construct(
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
    ) {}

    /**
     * @return array{numero: ?string, origen: ?string} numero normalizado a 6 dígitos
     */
    public function extraer(MailFacturaMensaje $mensaje, ?string $nombreAdjunto = null): array
    {
        foreach ([
            'asunto' => $mensaje->asunto,
            'cuerpo' => $mensaje->cuerpoTexto,
            'adjunto' => (string) $nombreAdjunto,
        ] as $origen => $texto) {
            $numero = $this->buscarEnTexto($texto);
            if ($numero !== null) {
                return ['numero' => $numero, 'origen' => 'mail_'.$origen];
            }
        }

        return ['numero' => null, 'origen' => null];
    }

    private function buscarEnTexto(string $texto): ?string
    {
        if (trim($texto) === '') {
            return null;
        }

        if (! preg_match_all(self::PATRON_OC, $texto, $matches)) {
            return null;
        }

        foreach ($matches[1] as $candidato) {
            try {
                return $this->numeroOcSupport->normalizar($candidato);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
