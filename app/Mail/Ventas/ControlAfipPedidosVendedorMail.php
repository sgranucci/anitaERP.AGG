<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ControlAfipPedidosVendedorMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $fila
     */
    public function __construct(
        public array $fila,
        public string $fechaControl,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Problemas con cliente')
            ->view('mails.ventas.control_afip_pedidos_vendedor');
    }
}
