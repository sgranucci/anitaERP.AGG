<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorProvisorio extends Mailable
{
    use Queueable, SerializesModels;

    public $datosProveedor;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($datos)
    {
        $this->datosProveedor = $datos;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.compras.proveedorprovisorio');
    }
}
