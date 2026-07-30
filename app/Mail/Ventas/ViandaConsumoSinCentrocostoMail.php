<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ViandaConsumoSinCentrocostoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public array $datos,
    ) {
    }

    public function build(): self
    {
        $empresa = (string) ($this->datos['empresa_nombre'] ?? 'Empresa');
        $codigo = (string) ($this->datos['codigo_retiro'] ?? '');
        $empleado = (string) ($this->datos['nombre_usuario'] ?? '');

        $asunto = sprintf(
            '[%s] Vianda sin centro de costo — %s — %s %s',
            config('app.name', 'anitaERP'),
            $empresa,
            $codigo,
            $empleado,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.vianda_consumo_sin_centrocosto');
    }
}
