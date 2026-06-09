<?php

namespace App\Support\Ventas\Waitry;

/**
 * Referencia externa de factura Anita para pushExternalOrder.external_client_id.
 */
final class WaitryExternalClientIdSupport
{
    public static function desdeFactura(string $facturaTxt, int $ventaId): string
    {
        $facturaTxt = trim($facturaTxt);
        if ($facturaTxt !== '' && preg_match('/^\S+\s+\S\s+(\d+-\d+)$/u', $facturaTxt, $m)) {
            return mb_substr($m[1], 0, 32);
        }

        if ($facturaTxt !== '') {
            return mb_substr(preg_replace('/\s+/u', ' ', $facturaTxt) ?? $facturaTxt, 0, 32);
        }

        return mb_substr('V'.$ventaId, 0, 32);
    }
}
