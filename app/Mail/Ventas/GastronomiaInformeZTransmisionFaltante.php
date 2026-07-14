<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GastronomiaInformeZTransmisionFaltante extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $analisis
     */
    public function __construct(
        public array $analisis,
    ) {
    }

    public function build(): self
    {
        $empresa = (string) ($this->analisis['empresa_nombre'] ?? 'Empresa');
        $fecha = (string) ($this->analisis['fecha_jornada_fmt'] ?? ($this->analisis['fecha_jornada'] ?? ''));
        $cantidad = (int) ($this->analisis['cantidad_comandas'] ?? 0);
        $total = number_format((float) ($this->analisis['total_faltante'] ?? 0), 2, ',', '.');

        $asunto = sprintf(
            '[%s] Informe Z: comandas no transmitidas al cierre — %s %s — %d comanda(s) $ %s',
            config('app.name', 'anitaERP'),
            $empresa,
            $fecha,
            $cantidad,
            $total,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.gastronomia_informe_z_transmision_faltante');
    }
}
