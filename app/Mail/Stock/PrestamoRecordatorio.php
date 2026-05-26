<?php

namespace App\Mail\Stock;

use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrestamoRecordatorio extends Mailable
{
    use Queueable, SerializesModels;

    public Prestamo $prestamo;

    public Configuracion_Prestamo $config;

    public bool $vencido;

    public function __construct(Prestamo $prestamo, Configuracion_Prestamo $config, bool $vencido = false)
    {
        $this->prestamo = $prestamo;
        $this->config = $config;
        $this->vencido = $vencido;
    }

    public function build(): self
    {
        $asunto = $this->vencido
            ? ($this->config->mail_asunto_devolucion_vencida ?: 'Préstamo vencido — devolución pendiente')
            : ($this->config->mail_asunto_recordatorio ?: 'Recordatorio de devolución de préstamo');

        return $this->subject($asunto)
            ->view('mails.stock.prestamorecordatorio');
    }
}
