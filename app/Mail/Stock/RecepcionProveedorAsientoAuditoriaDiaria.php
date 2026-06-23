<?php

namespace App\Mail\Stock;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecepcionProveedorAsientoAuditoriaDiaria extends Mailable
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
        $discrepancias = count($this->informe['discrepancias'] ?? []);
        $errores = count($this->informe['errores_lectura'] ?? []);
        $estado = ($discrepancias + $errores) > 0 ? 'ALERTA' : 'OK';

        $asunto = sprintf(
            '[%s] Recepción proveedor COM — asientos %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.stock.recepcion_proveedor_asiento_auditoria_diaria');
    }
}
