<?php

namespace App\Mail\Compras;

use App\Models\Compras\Ordencompra;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdencompraProveedor extends Mailable
{
    use Queueable, SerializesModels;

    public Ordencompra $ordencompra;

    public ?string $mensajeAdicional;

    public function __construct(Ordencompra $ordencompra, ?string $mensajeAdicional = null)
    {
        $this->ordencompra = $ordencompra;
        $this->mensajeAdicional = $mensajeAdicional;
        $numero = (string) ($ordencompra->numeroordencompra ?? $ordencompra->id);
        $this->subject('Orden de compra Nº '.$numero);
    }

    public function build(): self
    {
        return $this->view('mails.compras.ordencompra_proveedor');
    }
}
