<?php

namespace App\Mail\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorDiferenciaCambioPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $analisis
     * @param  array{
     *     bandeja: string,
     *     visualizar: string,
     *     factura: string,
     *     ordencompra: ?string,
     *     recepciones: list<array{id: int, url: string, fecha: string, tc: string}>
     * }  $links
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

        return $this->subject('['.config('app.name', 'anitaERP').'] Factura '.$numero.' — diferencia de cambio para aprobar')
            ->view('mails.compras.comprobante_proveedor_difcambio_pendiente');
    }
}
