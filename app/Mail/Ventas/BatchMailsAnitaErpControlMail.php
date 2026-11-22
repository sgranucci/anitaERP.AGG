<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BatchMailsAnitaErpControlMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $bloques
     */
    public function __construct(
        public string $fecha,
        public array $bloques,
    ) {
    }

    public function build(): self
    {
        $fechaTxt = date('d/m/Y', strtotime($this->fecha)) ?: $this->fecha;
        $n = 0;
        foreach ($this->bloques as $b) {
            if (! empty($b['problema'])) {
                $n += (int) ($b['diffs'] ?? 0);
            }
        }
        $asunto = sprintf('Control Anita↔ERP batch mails %s [%d DIFF]', $fechaTxt, $n);

        return $this->subject($asunto)
            ->view('mails.ventas.batch_mails_anita_erp_control');
    }
}
