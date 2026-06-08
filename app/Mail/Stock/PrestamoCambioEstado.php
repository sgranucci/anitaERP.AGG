<?php

namespace App\Mail\Stock;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use App\Support\Configuracion\PrestamoAvisoPlantillaSupport;
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

    public ?string $textoIntro;

    /**
     * @param  array<string, string>  $placeholders
     */
    public function __construct(
        Prestamo $prestamo,
        string $tipoCambio,
        ?string $mensaje,
        Configuracion_Prestamo $config,
        ?ModuloAvisoTipo $tipoPlantilla = null,
        array $placeholders = [],
    ) {
        $this->prestamo = $prestamo;
        $this->tipoCambio = $tipoCambio;
        $this->mensaje = $mensaje;
        $this->config = $config;

        $campoAsunto = $tipoCambio === 'rechazado' ? 'mail_asunto_rechazado_solicitante' : 'mail_asunto_aprobado_solicitante';
        $defaultAsunto = $tipoCambio === 'rechazado'
            ? 'Préstamo rechazado por el destinatario'
            : 'Préstamo aprobado por el destinatario';

        $this->textoIntro = PrestamoAvisoPlantillaSupport::textoIntro(
            $tipoPlantilla,
            $placeholders,
            $config,
            $tipoCambio === 'rechazado' ? 'mail_texto_rechazado' : 'mail_texto_aprobado'
        );
        $this->subject(PrestamoAvisoPlantillaSupport::asunto(
            $tipoPlantilla,
            $placeholders,
            $config,
            $campoAsunto,
            $defaultAsunto
        ));
    }

    public function build(): self
    {
        return $this->view('mails.stock.prestamocambioestado');
    }
}
