<?php

namespace App\Mail\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporteSueldosDefinibleDistribucion extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{path:string,nombre:string,mime:string}>  $adjuntos
     */
    public function __construct(
        public ReporteSueldosDefinibleSuscripcion $suscripcion,
        public ReporteSueldosDefinible $reporte,
        public ReporteSueldosDefinibleEjecucion $ejecucion,
        public string $segmento,
        public array $adjuntos,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Sueldos — %s%s',
            config('app.name', 'anitaERP'),
            $this->reporte->titulo,
            $this->segmento !== '' ? ' — '.$this->segmento : ''
        );
        $mail = $this->subject($asunto)->view('mails.sueldos.reporte_definible_distribucion');

        foreach ($this->adjuntos as $adjunto) {
            if (is_file($adjunto['path'])) {
                $mail->attach($adjunto['path'], [
                    'as' => $adjunto['nombre'],
                    'mime' => $adjunto['mime'],
                ]);
            }
        }

        return $mail;
    }
}
