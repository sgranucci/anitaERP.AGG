<?php

namespace App\Mail\Stock;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferenciaMercaderiaTitoPrecioAviso extends Mailable
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
        $codigo = (string) ($this->informe['codigo'] ?? '');
        $fecha = (string) ($this->informe['fecha'] ?? '');
        $lineas = count($this->informe['lineas'] ?? []);

        $asunto = sprintf(
            '[%s] TRA TITO precio promedio — %s — %s (%d ítem%s)',
            config('app.name', 'anitaERP'),
            $codigo !== '' ? $codigo : 'sin código',
            $fecha !== '' ? $fecha : now()->format('Y-m-d'),
            $lineas,
            $lineas === 1 ? '' : 's',
        );

        return $this->subject($asunto)
            ->view('mails.stock.transferencia_mercaderia_tito_precio_aviso');
    }
}
