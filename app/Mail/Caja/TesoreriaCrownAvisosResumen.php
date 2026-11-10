<?php

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TesoreriaCrownAvisosResumen extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{nuevos: list<array<string, mixed>>, abiertos: array<string, int>, total_abiertos: int, url: string}  $resumen
     */
    public function __construct(
        public array $resumen,
    ) {
    }

    public function build(): self
    {
        $nuevos = count($this->resumen['nuevos']);
        $asunto = sprintf(
            '[%s] Tesorería Crown — avisos de auditoría — %d nuevo(s), %d abierto(s)',
            config('app.name', 'anitaERP'),
            $nuevos,
            (int) $this->resumen['total_abiertos'],
        );

        return $this->subject($asunto)->view('mails.caja.tesoreria_crown_avisos_resumen');
    }
}
