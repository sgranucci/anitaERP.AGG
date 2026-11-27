<?php

declare(strict_types=1);

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TableroComprasPdfListoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   ok: bool,
     *   periodo: string,
     *   subtitulo?: string,
     *   lineas: int,
     *   mensaje: string,
     *   url_descarga?: string,
     *   nombre_archivo?: string,
     *   adjunto_path?: string,
     *   usuario_nombre?: string
     * }  $datos
     */
    public function __construct(
        public readonly array $datos,
    ) {
        $periodo = trim((string) ($datos['periodo'] ?? ''));
        $this->subject(
            (($datos['ok'] ?? false) ? 'PDF del tablero de compras listo' : 'PDF del tablero de compras con error')
            .($periodo !== '' ? ' — '.$periodo : '')
        );
    }

    public function build(): self
    {
        $mail = $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.compras.tablero_pdf_listo', ['datos' => $this->datos]);

        $adjunto = (string) ($this->datos['adjunto_path'] ?? '');
        if ($adjunto !== '' && is_file($adjunto)) {
            $mail->attach($adjunto, [
                'as' => (string) ($this->datos['nombre_archivo'] ?? 'tablero_compras.pdf'),
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
