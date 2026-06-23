<?php

namespace App\Mail\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\Configuracion_AsientoContable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AsientoCambioEstado extends Mailable
{
    use Queueable, SerializesModels;

    public Asiento $asiento;

    public string $tipoCambio;

    public ?string $mensaje;

    public Configuracion_AsientoContable $config;

    public function __construct(
        Asiento $asiento,
        string $tipoCambio,
        ?string $mensaje,
        Configuracion_AsientoContable $config,
    ) {
        $this->asiento = $asiento;
        $this->tipoCambio = $tipoCambio;
        $this->mensaje = $mensaje;
        $this->config = $config;

        $default = $tipoCambio === 'rechazado'
            ? 'Asiento contable rechazado'
            : 'Asiento contable aprobado';
        $campo = $tipoCambio === 'rechazado'
            ? 'mail_asunto_rechazado_solicitante'
            : 'mail_asunto_aprobado_solicitante';

        $this->subject($config->{$campo} ?: $default);
    }

    public function build(): self
    {
        return $this->view('mails.contable.asientocambioestado');
    }
}
