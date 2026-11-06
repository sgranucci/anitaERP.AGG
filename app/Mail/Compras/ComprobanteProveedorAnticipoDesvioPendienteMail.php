<?php

namespace App\Mail\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorAnticipoDesvioPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $analisis
     * @param  array<string, mixed>  $links
     */
    public function __construct(
        public Comprobante_Proveedor $comprobante,
        public array $analisis,
        public array $links,
    ) {}

    public function build(): self
    {
        $numero = trim(
            (string) ($this->comprobante->letra ?? '').' '
            .(string) ($this->comprobante->sucursal ?? '').'-'
            .(string) ($this->comprobante->numerocomprobante ?? '')
        );

        return $this->subject('['.config('app.name', 'anitaERP').'] Factura '.$numero.' — desvío de anticipo para aprobar')
            ->view('mails.compras.comprobante_proveedor_anticipo_desvio_pendiente');
    }
}
