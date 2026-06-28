<?php

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RendicionGastronomiaAuditoriaAnita extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
    ) {
    }

    public function build(): self
    {
        $fecha = (string) ($this->informe['fecha_jornada'] ?? '');
        $requiereAlerta = (bool) ($this->informe['requiere_alerta'] ?? false);
        $estado = $requiereAlerta ? 'ALERTA' : 'OK';

        $asunto = sprintf(
            '[%s] Rendgastro gastronomía — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.caja.rendicion_gastronomia_auditoria_anita');
    }
}
