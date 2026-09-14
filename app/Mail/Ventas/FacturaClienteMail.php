<?php

namespace App\Mail\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturaClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  list<array{path: string, name: string}>  $adjuntos */
    public function __construct(
        public Venta $venta,
        public string $cuerpoHtml,
        public array $adjuntos = [],
    ) {
        $codigo = (string) ($venta->codigo ?? $venta->id);
        $this->subject('Comprobante '.$codigo);
    }

    public function build(): self
    {
        $mail = $this->view('mails.ventas.factura_cliente', [
            'venta' => $this->venta,
            'cuerpoHtml' => $this->cuerpoHtml,
        ]);

        foreach ($this->adjuntos as $adj) {
            if (! empty($adj['path']) && is_file($adj['path'])) {
                $mail->attach($adj['path'], [
                    'as' => $adj['name'] ?? basename($adj['path']),
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }
}
