<?php

namespace App\Mail\Caja\Flash;

use App\Models\Caja\Flash\FlashCrownSuscripcion;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FlashCrownDistribucion extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{path: string, nombre: string, mime: string, dias?: int}  $archivo
     */
    public function __construct(
        public FlashCrownSuscripcion $suscripcion,
        public Carbon $desde,
        public Carbon $hasta,
        public array $archivo,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Flash Report Crown — %s (%s al %s)',
            config('app.name', 'anitaERP'),
            $this->suscripcion->nombre,
            $this->desde->format('d/m/Y'),
            $this->hasta->format('d/m/Y')
        );

        $mail = $this->subject($asunto)->view('mails.caja.flash.reporte_crown_distribucion');

        if (is_file($this->archivo['path'])) {
            $mail->attach($this->archivo['path'], [
                'as' => $this->archivo['nombre'],
                'mime' => $this->archivo['mime'],
            ]);
        }

        return $mail;
    }
}
