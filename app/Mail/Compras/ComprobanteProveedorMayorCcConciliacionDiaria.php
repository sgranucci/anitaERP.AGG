<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorMayorCcConciliacionDiaria extends Mailable
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
        $estado = $alerta ? 'ALERTA' : 'OK';

        $asunto = sprintf(
            '[%s] Mayor Anita vs CC proveedores MN/ME — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.compras.comprobante_proveedor_mayor_cc_conciliacion_diaria');
    }
}
