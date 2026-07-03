<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GastronomiaControlCuadreJornadaAnita extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $csvContenido,
        public string $nombreArchivoCsv,
    ) {
    }

    public function build(): self
    {
        $desde = (string) ($this->informe['fecha_desde'] ?? '');
        $hasta = (string) ($this->informe['fecha_hasta'] ?? '');
        $hayDif = (bool) ($this->informe['hay_diferencias'] ?? false);
        $estado = $hayDif ? 'DIFERENCIAS' : 'OK';

        $asunto = sprintf(
            '[%s] Cuadre jornada contabilidad/rendiciones/ventas %s%s — %s',
            config('app.name', 'anitaERP'),
            $desde,
            $hasta !== $desde ? ' → '.$hasta : '',
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.gastronomia_control_cuadre_jornada_anita')
            ->attachData(
                $this->csvContenido,
                $this->nombreArchivoCsv,
                ['mime' => 'text/csv'],
            );
    }
}
