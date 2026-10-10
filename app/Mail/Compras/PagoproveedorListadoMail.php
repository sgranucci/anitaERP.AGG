<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoproveedorListadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreArchivo,
        public string $contenido,
        public int $filas,
        public bool $recorte,
    ) {}

    public function build(): self
    {
        $mail = $this->subject('Listado de pagos a proveedores')
            ->view('mails.compras.pagoproveedor_listado', [
                'filas' => $this->filas,
                'recorte' => $this->recorte,
            ]);

        return $mail->attachData($this->contenido, $this->nombreArchivo, [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
