<?php

declare(strict_types=1);

namespace App\Mail\Contable;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuentacontableSaldoMesIntegridad extends Mailable
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
        $periodos = (int) ($this->informe['resumen']['periodos_con_desvio'] ?? 0);
        $estado = $periodos > 0 ? 'ALERTA' : 'OK';

        $asunto = sprintf(
            '[%s] Saldos mensuales por cuenta — integridad %s — %s',
            config('app.name', 'anitaERP'),
            now()->format('d/m/Y'),
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.contable.cuentacontable_saldo_mes_integridad');
    }
}
