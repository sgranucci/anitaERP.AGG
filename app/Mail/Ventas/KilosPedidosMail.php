<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KilosPedidosMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{pieza?: float, kilo?: float, caja?: float, pesada?: float}  $totalesMerge
     */
    public function __construct(
        public string $perfilNombre,
        public string $fechaEntrega,
        public string $repartoTexto,
        public array $totalesMerge,
        public string $pdfContenido,
        public string $nombreArchivoPdf,
    ) {
    }

    public function build(): self
    {
        $fechaTxt = date('d/m/Y', strtotime($this->fechaEntrega)) ?: $this->fechaEntrega;
        $asunto = sprintf('Kilos pedidos %s', $fechaTxt);

        return $this->subject($asunto)
            ->view('mails.ventas.kilos_pedidos')
            ->attachData(
                $this->pdfContenido,
                $this->nombreArchivoPdf,
                ['mime' => 'application/pdf'],
            );
    }
}
