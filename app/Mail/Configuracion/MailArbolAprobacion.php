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

    /** @var array<string, mixed>|null Datos extra solo para requisiciones (estado al aprobar, monto ítems). */
    public $mailExtras;

    /**
     * Create a new message instance.
     *
     * @param  array<string, mixed>|null  $mailExtras
     * @return void
     */
    public function __construct($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras = null)
    {
        $this->datosComprobante = $ptrcomprobante;
        $this->tipoArbol = $tipoarbol;
        $this->linkAprobacion = $linkaprobacion;
        $this->linkRechazo = $linkrechazo;
        $this->linkVisualizar = $linkvisualizar;
        $this->mailExtras = $mailExtras;
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
