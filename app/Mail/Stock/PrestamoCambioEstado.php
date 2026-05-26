<?php

namespace App\Mail\Stock;

use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrestamoCambioEstado extends Mailable
{
    use Queueable, SerializesModels;

    public Prestamo $prestamo;

    public string $tipoCambio;

    public ?string $mensaje;

    public Configuracion_Prestamo $config;

    public function __construct(Prestamo $prestamo, string $tipoCambio, ?string $mensaje, Configuracion_Prestamo $config)
    {
        $this->prestamo = $prestamo;
        $this->tipoCambio = $tipoCambio;
        $this->mensaje = $mensaje;
        $this->config = $config;
    }

    public function build(): self
    {
        $asunto = $this->tipoCambio === 'rechazado'
            ? ($this->config->mail_asunto_rechazado_solicitante ?: 'Préstamo rechazado por el destinatario')
            : ($this->config->mail_asunto_aprobado_solicitante ?: 'Préstamo aprobado por el destinatario');

        return $this->subject($asunto)
            ->view('mails.stock.prestamocambioestado');
    }
}
