<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorAnitaAuditoriaDiaria extends Mailable
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
        $errores = count($this->informe['errores'] ?? []);
        $reparadas = (int) ($this->informe['reparadas'] ?? 0);

        if (($discrepancias + $errores) > 0) {
            $estado = 'ALERTA';
        } elseif ($reparadas > 0) {
            $estado = 'REPARADAS';
        } else {
            $estado = 'OK';
        }

        $asunto = sprintf(
            '[%s] Facturas proveedor Anita — auditoría %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.compras.comprobante_proveedor_anita_auditoria_diaria');
    }
}
