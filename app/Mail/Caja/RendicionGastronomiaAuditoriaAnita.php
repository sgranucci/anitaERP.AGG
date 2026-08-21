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
        $clasificacion = (string) ($this->informe['clasificacion_alerta'] ?? '');
        if ($clasificacion === '') {
            $clasificacion = $requiereAlerta ? 'alerta' : 'ok';
        }
        $estado = match ($clasificacion) {
            'aviso_caja_pendiente' => 'AVISO (caja pendiente)',
            'alerta' => 'ALERTA',
            default => 'OK',
        };

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
