<?php

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IngresoEgresoImputacionDiaria extends Mailable
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
        $fecha = (string) ($this->informe['fecha_calendario'] ?? '');
        $alerta = ! empty($this->informe['requiere_alerta']);
        $desvios = (int) (($this->informe['totales']['con_desvio'] ?? 0));
        $estado = $alerta ? 'ALERTA ('.$desvios.' desvíos)' : 'OK';

        $asunto = sprintf(
            '[%s] I/E caja / cheques / asiento — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.caja.ingresoegreso_imputacion_diaria');
    }
}
