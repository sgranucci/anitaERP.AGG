<?php

namespace App\Support\Mail;

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Mail\Mailable;

/**
 * Mails al proveedor salen de la casilla SMTP (AnitaERP), que nadie lee.
 * El nombre visible dice que no se responde, y Reply-To apunta a la casilla
 * cargada en Configuración general.
 */
final class CorreoProveedorNoResponderSupport
{
    public const NOMBRE_REMITENTE = 'Anita ERP (no responder)';

    public static function aplicar(Mailable $mail): string
    {
        $consulta = ParametroSistemaSupport::mailConsultaProveedor();
        $desde = trim((string) config('mail.from.address'));
        if ($desde !== '') {
            $mail->from($desde, self::NOMBRE_REMITENTE);
        }
        if ($consulta !== '') {
            $mail->replyTo($consulta);
        }

        return $consulta;
    }
}
