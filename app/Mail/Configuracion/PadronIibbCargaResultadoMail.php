<?php

declare(strict_types=1);

namespace App\Mail\Configuracion;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PadronIibbCargaResultadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   ok: bool,
     *   origen: string,
     *   archivo?: string,
     *   mensaje: string,
     *   stats?: array<string, mixed>,
     *   error?: string|null
     * }  $resultado
     */
    public function __construct(
        public readonly array $resultado,
    ) {
        $ok = (bool) ($resultado['ok'] ?? false);
        $origen = trim((string) ($resultado['origen'] ?? 'Padrón IIBB'));
        $this->subject(($ok ? 'OK' : 'ERROR') . ' — carga ' . $origen);
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.configuracion.padron_iibb_carga_resultado', [
                'resultado' => $this->resultado,
            ]);
    }
}
