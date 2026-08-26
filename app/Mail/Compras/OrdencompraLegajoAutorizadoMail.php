<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdencompraLegajoAutorizadoMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $datos */
    public function __construct(public array $datos) {}

    public function build(): self
    {
        return $this->subject(sprintf(
            '[%s] Legajo OC %s autorizado — disponible en Cuentas a pagar',
            config('app.name', 'anitaERP'),
            $this->datos['numero_oc'] ?? '',
        ))->view('mails.compras.ordencompra_legajo_autorizado');
    }
}
