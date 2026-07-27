<?php

namespace App\Mail\Compras;

use App\Support\Compras\PrecargaProveedor\Mail\MailFacturaMensaje;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de errores al procesar un mail de la casilla de facturas.
 * Destinatarios: config('precarga_comprobante_mail.aviso_errores.destinatarios').
 */
class FacturaMailIngestaErroresMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{ok: bool, adjunto: string, numero_oc: ?string, error?: string}>  $errores
     */
    public function __construct(
        public MailFacturaMensaje $mensaje,
        public array $errores,
        public int $exitos,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Ingesta facturas por mail — %d error(es) — %s',
            config('app.name', 'anitaERP'),
            count($this->errores),
            mb_substr($this->mensaje->asunto, 0, 80),
        );

        return $this->subject($asunto)
            ->view('mails.compras.factura_mail_ingesta_errores');
    }
}
