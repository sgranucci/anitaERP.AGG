<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorImputacionApDiaria extends Mailable
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
        $borradores = (int) (($this->informe['totales']['en_borrador'] ?? 0));
        if ($alerta) {
            $estado = 'ALERTA ('.$desvios.' desvíos)';
        } elseif ($borradores > 0) {
            $estado = 'OK · '.$borradores.' en borrador';
        } else {
            $estado = 'OK';
        }

        $asunto = sprintf(
            '[%s] CC / asiento / ctamov por factura — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.compras.comprobante_proveedor_imputacion_ap_diaria');
    }
}
