<?php

namespace App\Mail\Configuracion;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailDigestMisAprobaciones extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public string $nombreUsuario,
        public array $items,
        public int $total,
        public int $urgentes,
        public string $linkBandeja,
        public Carbon $fecha,
    ) {}

    public function build()
    {
        $asunto = $this->total === 1
            ? 'Anita: tenés 1 aprobación pendiente'
            : 'Anita: tenés '.$this->total.' aprobaciones pendientes';

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($asunto)
            ->view('mails.configuracion.digest_mis_aprobaciones');
    }
}
