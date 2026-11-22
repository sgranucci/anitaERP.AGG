<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ControlNcDescuentoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        public string $fecha,
        public array $totales,
        public string $pdfContenido,
        public string $nombreArchivoPdf,
    ) {
    }

    public function build(): self
    {
        $fechaTxt = date('d/m/Y', strtotime($this->fecha)) ?: $this->fecha;
        $asunto = sprintf('Notas de Credito de Dto. %s', $fechaTxt);

        return $this->subject($asunto)
            ->view('mails.ventas.control_nc_descuento')
            ->attachData(
                $this->pdfContenido,
                $this->nombreArchivoPdf,
                ['mime' => 'application/pdf'],
            );
    }
}
