<?php

namespace App\Mail\Stock;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StkmovIntMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $porcentajes
     */
    public function __construct(
        public string $fecha,
        public array $totales,
        public string $pdfContenido,
        public string $nombreArchivoPdf,
        public array $porcentajes = [],
        public string $graficoPng = '',
    ) {
    }

    public function build(): self
    {
        $fechaTxt = date('d/m/Y', strtotime($this->fecha)) ?: $this->fecha;
        $asunto = sprintf('Movimientos de stock del %s', $fechaTxt);

        return $this->subject($asunto)
            ->view('mails.stock.stkmov_int')
            ->attachData(
                $this->pdfContenido,
                $this->nombreArchivoPdf,
                ['mime' => 'application/pdf'],
            );
    }
}
