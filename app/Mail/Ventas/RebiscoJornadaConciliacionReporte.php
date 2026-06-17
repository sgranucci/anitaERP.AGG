<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RebiscoJornadaConciliacionReporte extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $rutaArchivo,
        public string $nombreArchivo,
    ) {
    }

    public function build(): self
    {
        $fecha = (string) ($this->informe['fecha_jornada'] ?? '');
        $estado = ! empty($this->informe['hay_diferencias']) ? 'DIFERENCIAS' : 'OK';

        return $this->subject(sprintf(
            '[%s] Rebisco RSA jornada %s — ERP/Anita/medios — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        ))
            ->view('mails.ventas.rebisco_jornada_conciliacion_reporte')
            ->attach($this->rutaArchivo, [
                'as' => $this->nombreArchivo,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
