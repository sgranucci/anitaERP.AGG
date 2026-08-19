<?php

namespace App\Mail\Caja\Flash;

use App\Models\Caja\Flash\FlashReporteSuscripcion;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FlashReporteAggDistribucion extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{path: string, nombre: string, mime: string, dias?: int, empresas?: list<string>}  $archivo
     */
    public function __construct(
        public FlashReporteSuscripcion $suscripcion,
        public Carbon $desde,
        public Carbon $hasta,
        public array $archivo,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Flash Report AGG — %s al %s',
            config('app.name', 'anitaERP'),
            $this->suscripcion->nombre,
            $this->hasta->format('d/m/Y')
        );

        $mail = $this->subject($asunto)->view('mails.caja.flash.reporte_agg_distribucion');

        if (is_file($this->archivo['path'])) {
            $mail->attach($this->archivo['path'], [
                'as' => $this->archivo['nombre'],
                'mime' => $this->archivo['mime'],
            ]);
        }

        return $mail;
    }
}
