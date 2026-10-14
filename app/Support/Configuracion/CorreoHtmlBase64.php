<?php

namespace App\Support\Configuracion;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * El HTML de los correos de aprobación se manda en quoted-printable, que corta
 * las líneas a 76 caracteres. Outlook abre el enlace en ese corte y el hash
 * llega incompleto. Base64 deja el href entero.
 */
final class CorreoHtmlBase64
{
    public const HEADER = 'X-Anita-Html-Base64';

    public static function aplicarSiCorresponde(object $message): void
    {
        if (! $message instanceof Email) {
            return;
        }

        $headers = $message->getHeaders();
        if (! $headers->has(self::HEADER)) {
            return;
        }

        $headers->remove(self::HEADER);

        $html = $message->getHtmlBody();
        if (! is_string($html) || $html === '') {
            return;
        }

        $htmlPart = new TextPart($html, 'utf-8', 'html', 'base64');
        $adjuntos = $message->getAttachments();
        $message->setBody($adjuntos === []
            ? $htmlPart
            : new MixedPart($htmlPart, ...$adjuntos));
    }
}
