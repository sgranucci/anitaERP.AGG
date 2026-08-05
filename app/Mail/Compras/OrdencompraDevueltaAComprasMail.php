<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdencompraDevueltaAComprasMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     numero_oc: string|int,
     *     ordencompra_id: int,
     *     proveedor: string,
     *     empresa: string,
     *     motivo: string,
     *     detalle: string,
     *     url: string
     * }  $datos
     */
    public function __construct(public array $datos) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Legajo OC %s devuelto a COMPRAS — %s',
            config('app.name', 'anitaERP'),
            $this->datos['numero_oc'] ?? '',
            mb_substr((string) ($this->datos['motivo'] ?? ''), 0, 60),
        );

        return $this->subject($asunto)
            ->view('mails.compras.ordencompra_devuelta_a_compras');
    }
}
