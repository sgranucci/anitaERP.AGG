<?php

namespace App\Support\Compras\PrecargaProveedor\Batch;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use Throwable;

/** Extrae una OC rotulada del nombre del PDF: OC222102, OC-222102, O.C. 222102. */
final class FacturaBatchOcExtractorSupport
{
    private const PATRON = '/(?<![a-z])o\.?\s*c\.?\s*(?:n[°ºo]\.?)?\s*[:#_-]?\s*(\d{1,6})\b/iu';

    public function __construct(
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
    ) {}

    public function extraer(string $nombreArchivo): ?string
    {
        if (! preg_match(self::PATRON, $nombreArchivo, $match)) {
            return null;
        }

        try {
            return $this->numeroOcSupport->normalizar($match[1]);
        } catch (Throwable) {
            return null;
        }
    }
}
