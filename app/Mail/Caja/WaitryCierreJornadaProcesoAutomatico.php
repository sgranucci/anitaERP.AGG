<?php

namespace App\Mail\Caja;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class WaitryCierreJornadaProcesoAutomatico extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public bool $urgente = false,
    ) {
        if (! $this->urgente) {
            $this->urgente = CierreJornadaProcesoAutomaticoSupport::informeTieneErrores($this->informe);
        }
    }

    public function build(): self
    {
        $resumen = $this->informe['resumen'] ?? [];
        $procesadas = (int) ($resumen['procesadas'] ?? 0);
        $errores = (int) ($resumen['errores'] ?? 0);
        $urgente = $this->urgente || $errores > 0;

        if ($urgente) {
            $estado = 'URGENTE — FALLO PROCESO';
        } elseif ($procesadas > 0) {
            $estado = 'OK';
        } else {
            $estado = 'SIN PENDIENTES';
        }

        $asunto = sprintf(
            '[%s] Cierre automático Waitry — %s',
            config('app.name', 'anitaERP'),
            $estado,
        );

        $mail = $this->subject($asunto)
            ->view('mails.caja.waitry_cierre_jornada_proceso_automatico')
            ->with([
                'urgente' => $urgente,
            ]);

        if (! $urgente) {
            return $mail;
        }

        return $mail->withSymfonyMessage(function (Email $message): void {
            $message->priority(Email::PRIORITY_HIGHEST);
            $headers = $message->getHeaders();
            if (! $headers->has('X-Priority')) {
                $headers->addTextHeader('X-Priority', '1 (Highest)');
            }
            if (! $headers->has('Importance')) {
                $headers->addTextHeader('Importance', 'high');
            }
            if (! $headers->has('X-MSMail-Priority')) {
                $headers->addTextHeader('X-MSMail-Priority', 'High');
            }
        });
    }
}
