<?php

namespace App\Mail\Stock;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use App\Support\Configuracion\PrestamoAvisoPlantillaSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrestamoRecordatorio extends Mailable
{
    use Queueable, SerializesModels;

    public Prestamo $prestamo;

    public Configuracion_Prestamo $config;

    public bool $vencido;

    public ?string $textoIntro;

    /**
     * @param  array<string, string>  $placeholders
     */
    public function __construct(
        Prestamo $prestamo,
        Configuracion_Prestamo $config,
        bool $vencido = false,
        ?ModuloAvisoTipo $tipoPlantilla = null,
        array $placeholders = [],
    ) {
        $this->prestamo = $prestamo;
        $this->config = $config;
        $this->vencido = $vencido;

        $campoAsunto = $vencido ? 'mail_asunto_devolucion_vencida' : 'mail_asunto_recordatorio';
        $campoTexto = $vencido ? 'mail_texto_devolucion_vencida' : 'mail_texto_recordatorio';
        $defaultAsunto = $vencido
            ? 'Préstamo vencido — devolución pendiente'
            : 'Recordatorio de devolución de préstamo';

        // Asunto del tipo de aviso aplica al recordatorio normal; vencido usa config legacy.
        $tipoPlantillaAsunto = $vencido ? null : $tipoPlantilla;

        $this->textoIntro = PrestamoAvisoPlantillaSupport::textoIntro(
            $tipoPlantilla,
            $placeholders,
            $config,
            $campoTexto
        );
        $this->subject(PrestamoAvisoPlantillaSupport::asunto(
            $tipoPlantillaAsunto,
            $placeholders,
            $config,
            $campoAsunto,
            $defaultAsunto
        ));
    }

    public function build(): self
    {
        return $this->view('mails.stock.prestamorecordatorio');
    }
}
