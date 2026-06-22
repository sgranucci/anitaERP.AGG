<?php

namespace App\Mail\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ColaPicoAlerta extends Mailable
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
        $status = (string) ($this->informe['status'] ?? 'ALERTA');
        $timestamp = (string) ($this->informe['timestamp'] ?? now()->toDateTimeString());

        $asunto = sprintf(
            '[%s] Cola Laravel — %s — %s',
            config('app.name', 'anitaERP'),
            $status,
            $timestamp,
        );

        return $this->subject($asunto)
            ->view('mails.queue.cola_pico_alerta');
    }
}
