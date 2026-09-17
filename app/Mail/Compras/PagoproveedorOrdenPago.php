<?php

namespace App\Mail\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoproveedorOrdenPago extends Mailable
{
    use Queueable, SerializesModels;

    public Pagoproveedor $pagoproveedor;

    public ?string $mensajeAdicional;

    public function __construct(Pagoproveedor $pagoproveedor, ?string $mensajeAdicional = null)
    {
        $this->pagoproveedor = $pagoproveedor;
        $this->mensajeAdicional = $mensajeAdicional;
        $this->subject('Orden de pago '.$pagoproveedor->etiquetaComprobante());
    }

    public function build(): self
    {
        return $this->view('mails.compras.pagoproveedor_orden_pago');
    }
}
