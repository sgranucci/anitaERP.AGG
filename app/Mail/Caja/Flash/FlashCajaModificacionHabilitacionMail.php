<?php

namespace App\Mail\Caja\Flash;

use App\Models\Caja\Flash\FlashCajaModificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FlashCajaModificacionHabilitacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FlashCajaModificacion $solicitud,
        public string $urlHabilitar,
    ) {}

    public function build(): self
    {
        $flash = $this->solicitud->flash;
        $fecha = $flash?->fecha?->format('d/m/Y') ?? '';
        $empresa = (string) ($flash?->empresa?->nombre ?? '');

        return $this->subject('['.config('app.name', 'anitaERP').'] Habilitar modificación de flash '.$fecha.' '.$empresa)
            ->view('mails.caja.flash.modificacion_habilitacion');
    }
}
