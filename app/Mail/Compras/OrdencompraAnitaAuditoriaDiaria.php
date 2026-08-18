<?php

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdencompraAnitaAuditoriaDiaria extends Mailable
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
        $pendmovp = (int) ($this->informe['pendmovp_cobertura_reparadas'] ?? 0);

        if (($discrepancias + $errores) > 0) {
            $estado = 'ALERTA';
        } elseif ($reparadas > 0) {
            $estado = $pendmovp > 0
                ? 'REPARADAS (pendmovp '.$pendmovp.')'
                : 'REPARADAS';
        } else {
            $estado = 'OK';
        }

        $asunto = sprintf(
            '[%s] Órdenes de compra Anita — auditoría %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.compras.ordencompra_anita_auditoria_diaria');
    }
}
