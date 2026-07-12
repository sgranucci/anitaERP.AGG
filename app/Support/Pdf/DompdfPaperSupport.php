<?php

namespace App\Support\Pdf;

final class DompdfPaperSupport
{
    public const CONTEXTO_LISTADO = 'listado';

    public const CONTEXTO_COMPROBANTE = 'comprobante';

    public static function tamano(?string $contexto = null): string
    {
        $especifico = match ($contexto) {
            self::CONTEXTO_LISTADO => config('pdf.listado.tamano'),
            self::CONTEXTO_COMPROBANTE => config('pdf.comprobante.tamano'),
            default => null,
        };

        if (is_string($especifico) && $especifico !== '') {
            return $especifico;
        }

        return (string) config('pdf.tamano', 'a4');
    }

    public static function orientacion(?string $contexto = null): string
    {
        return match ($contexto) {
            self::CONTEXTO_LISTADO => (string) config('pdf.listado.orientacion', 'landscape'),
            self::CONTEXTO_COMPROBANTE => (string) config('pdf.comprobante.orientacion', 'portrait'),
            default => (string) config('pdf.orientacion', 'portrait'),
        };
    }

    /**
     * @param  object  $pdf  Instancia dompdf.wrapper
     */
    public static function aplicar(object $pdf, ?string $contexto = null, ?string $tamano = null, ?string $orientacion = null): void
    {
        $pdf->setPaper(
            $tamano ?? self::tamano($contexto),
            $orientacion ?? self::orientacion($contexto),
        );
    }
}
