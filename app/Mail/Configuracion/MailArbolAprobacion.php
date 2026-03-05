<?php

namespace App\Mail\Configuracion;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailArbolAprobacion extends Mailable
{
    use Queueable, SerializesModels;

    public $datosComprobante;
    public $tipoArbol;
    public $linkAprobacion, $linkRechazo;
    public $linkVisualizar;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar)
    {
        $this->datosComprobante = $ptrcomprobante;
        $this->tipoArbol = $tipoarbol;
        $this->linkAprobacion = $linkaprobacion;
        $this->linkRechazo = $linkrechazo;
        $this->linkVisualizar = $linkvisualizar;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.configuracion.arbolaprobacion');
    }
}
