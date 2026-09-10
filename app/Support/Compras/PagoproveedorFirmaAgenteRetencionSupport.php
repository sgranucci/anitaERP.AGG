<?php

namespace App\Support\Compras;

/**
 * Firma del agente de retención en certificados PDF de la OP.
 */
final class PagoproveedorFirmaAgenteRetencionSupport
{
    public static function dataUri(): ?string
    {
        $ruta = (string) config('pagoproveedor.firma_agente_retencion', '');
        if ($ruta === '') {
            $ruta = resource_path('firmas/firma_acosta.jpg');
        } elseif ($ruta[0] !== '/') {
            $ruta = base_path($ruta);
        }
        if ($ruta === '' || ! is_file($ruta) || ! is_readable($ruta)) {
            return null;
        }

        $data = @file_get_contents($ruta);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = function_exists('mime_content_type')
            ? (string) (mime_content_type($ruta) ?: 'image/jpeg')
            : 'image/jpeg';
        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }
}
