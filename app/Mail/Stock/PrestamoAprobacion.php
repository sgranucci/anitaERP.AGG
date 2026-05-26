<?php

namespace App\Mail\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
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

    /**
     * @param  array{aprobar:string, rechazar:string, visualizar:string}  $links
     */
    public function __construct(Prestamo $prestamo, Usuario $destinatario, array $links, Configuracion_Prestamo $config)
    {
        $this->prestamo = $prestamo;
        $this->destinatario = $destinatario;
        $this->links = $links;
        $this->config = $config;
    }

    public function build(): self
    {
        return $this->subject($this->config->mail_asunto_aprobacion ?: 'Préstamo de materiales: pendiente de aprobación')
            ->view('mails.stock.prestamoaprobacion');
    }
}
