<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturacionLocalShoppingCaballitoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $fechaTexto,
        public readonly string $nombreArchivo,
        public readonly string $rutaAbsoluta,
        public readonly int $comprobantes,
        public readonly int $lineas,
    ) {
        $this->subject('Ventas Caballito '.$fechaTexto.' — '.$nombreArchivo);
    }

    public function build(): self
    {
        return $this
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->view('mails.ventas.facturacion_local_shopping_caballito', [
                'fechaTexto' => $this->fechaTexto,
                'nombreArchivo' => $this->nombreArchivo,
                'comprobantes' => $this->comprobantes,
                'lineas' => $this->lineas,
            ])
            ->attach($this->rutaAbsoluta, [
                'as' => $this->nombreArchivo,
                'mime' => 'text/plain',
            ]);
    }
}
