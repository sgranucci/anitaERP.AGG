<?php

namespace App\Mail\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\Configuracion_AsientoContable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AsientoPendienteAprobacion extends Mailable
{
    use Queueable, SerializesModels;

    public Asiento $asiento;

    /** @var array{aprobar:string, rechazar:string, visualizar:string} */
    public array $links;

    public Configuracion_AsientoContable $config;

    /**
     * @param  array{aprobar:string, rechazar:string, visualizar:string}  $links
     */
    public function __construct(Asiento $asiento, array $links, Configuracion_AsientoContable $config)
    {
        $this->asiento = $asiento;
        $this->links = $links;
        $this->config = $config;
        $this->subject($config->mail_asunto_aprobacion ?: 'Asiento contable pendiente de aprobación');
    }

    public function build(): self
    {
        return $this->view('mails.contable.asientopendienteaprobacion');
    }
}
