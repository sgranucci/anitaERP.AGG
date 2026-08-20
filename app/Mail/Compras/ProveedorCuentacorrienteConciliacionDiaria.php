<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorCuentacorrienteConciliacionDiaria extends Mailable
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
        $estado = ! empty($this->informe['requiere_alerta']) ? 'ALERTA' : 'OK';
        $asunto = sprintf(
            '[%s] CC proveedores — conciliación ficha/deuda/mayor %s — %s',
            config('app.name', 'anitaERP'),
            $this->informe['fecha_calendario'] ?? now()->format('d/m/Y'),
            $estado
        );

        return $this->subject($asunto)
            ->view('mails.compras.proveedor_cuentacorriente_conciliacion_diaria');
    }
}
