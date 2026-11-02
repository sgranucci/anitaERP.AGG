<?php

namespace App\Mail\Stock;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecepcionProveedorCostoAuditorMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $informe */
    public function __construct(public array $informe) {}

    public function build(): self
    {
        $n = count($this->informe['hallazgos'] ?? []) + count($this->informe['recetas'] ?? []);
        $estado = $n > 0 ? 'ALERTA ('.$n.')' : 'OK';

        return $this->subject(sprintf(
            '[%s] Auditor costos de compra y recetas — %s — %s',
            config('app.name', 'anitaERP'),
            (string) ($this->informe['fecha_calendario'] ?? ''),
            $estado,
        ))->view('mails.stock.recepcion_proveedor_costo_auditor');
    }
}
