<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteProveedorAuditorFarDiferenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $informe */
    public function __construct(public array $informe) {}

    public function build(): self
    {
        $fecha = (string) ($this->informe['fecha_calendario'] ?? '');
        $n = count($this->informe['desvios'] ?? []);
        $estado = $n > 0 ? 'ALERTA ('.$n.')' : 'OK';

        return $this->subject(sprintf(
            '[%s] Auditor FAR vs OC (P1) — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        ))->view('mails.compras.comprobante_proveedor_auditor_far_diferencia');
    }
}
