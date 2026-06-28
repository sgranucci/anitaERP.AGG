<?php

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitryCierreJornadaProcesoAutomatico extends Mailable
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
        $resumen = $this->informe['resumen'] ?? [];
        $procesadas = (int) ($resumen['procesadas'] ?? 0);
        $errores = (int) ($resumen['errores'] ?? 0);
        $estado = $errores > 0 ? 'CON ERRORES' : ($procesadas > 0 ? 'OK' : 'SIN PENDIENTES');

        $asunto = sprintf(
            '[%s] Cierre automático Waitry — %s',
            config('app.name', 'anitaERP'),
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.caja.waitry_cierre_jornada_proceso_automatico');
    }
}
