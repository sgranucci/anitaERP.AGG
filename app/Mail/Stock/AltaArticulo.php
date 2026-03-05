<?php

namespace App\Mail\Stock;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AltaArticulo extends Mailable
{
    use Queueable, SerializesModels;

    public $datosArticulo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($datos, $marca, $linea)
    {
        $this->datosArticulo = $datos;
        $this->datosArticulo['marca'] = $marca;
        $this->datosArticulo['linea'] = $linea;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.stock.altaarticulo');
    }
}
