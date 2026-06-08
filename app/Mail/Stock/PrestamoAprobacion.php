<?php

namespace App\Mail\Stock;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use App\Support\Configuracion\PrestamoAvisoPlantillaSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrestamoAprobacion extends Mailable
{
    use Queueable, SerializesModels;

    public Prestamo $prestamo;

    public Usuario $destinatario;

    /** @var array{aprobar:string, rechazar:string, visualizar:string} */
    public array $links;

    public Configuracion_Prestamo $config;

    public ?string $textoIntro;

    /**
     * @param  array{aprobar:string, rechazar:string, visualizar:string}  $links
     * @param  array<string, string>  $placeholders
     */
    public function __construct(
        Prestamo $prestamo,
        Usuario $destinatario,
        array $links,
        Configuracion_Prestamo $config,
        ?ModuloAvisoTipo $tipoPlantilla = null,
        array $placeholders = [],
    ) {
        $this->prestamo = $prestamo;
        $this->destinatario = $destinatario;
        $this->links = $links;
        $this->config = $config;
        $this->textoIntro = PrestamoAvisoPlantillaSupport::textoIntro(
            $tipoPlantilla,
            $placeholders,
            $config,
            'mail_texto_aprobacion'
        );
        $this->subject(PrestamoAvisoPlantillaSupport::asunto(
            $tipoPlantilla,
            $placeholders,
            $config,
            'mail_asunto_aprobacion',
            'Préstamo de materiales: pendiente de aprobación'
        ));
    }

    public function build(): self
    {
        return $this->view('mails.stock.prestamoaprobacion');
    }
}
