<?php

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IngresoEgresoListadoMail extends Mailable
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
        $mail = $this->subject('Listado de ingresos y egresos')
            ->view('mails.caja.ingresoegreso_listado', [
                'filas' => $this->filas,
                'recorte' => $this->recorte,
            ]);

        return $mail->attachData($this->contenido, $this->nombreArchivo, [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
