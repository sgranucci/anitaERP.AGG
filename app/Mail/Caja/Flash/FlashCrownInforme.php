<?php

namespace App\Mail\Caja\Flash;

use App\Support\Caja\Flash\FlashCrownInformeSupport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FlashCrownInforme extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $formato,
    ) {}

    public function build(): self
    {
        $fecha = $this->informe['fecha'] instanceof Carbon
            ? $this->informe['fecha']
            : Carbon::parse((string) ($this->informe['fecha'] ?? now()));

        $asunto = match ($this->formato) {
            FlashCrownInformeSupport::FORMATO_GAMING => 'Gaming Revenues '.$fecha->format('d-m-y'),
            FlashCrownInformeSupport::FORMATO_DAILY => 'Daily Flash Report '.$fecha->format('d-m-y'),
            default => 'Flash Crown '.$fecha->format('d-m-y'),
        };

        return $this->subject($asunto)->view('mails.caja.flash.informe_crown', [
            'informe' => $this->informe,
            'formato' => $this->formato,
            'fecha' => $fecha,
        ]);
    }
}
