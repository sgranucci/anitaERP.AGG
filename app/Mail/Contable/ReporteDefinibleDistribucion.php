<?php

declare(strict_types=1);

namespace App\Mail\Contable;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContablePublicacion;
use App\Models\Contable\ReporteContableSuscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporteDefinibleDistribucion extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $resultado
     * @param  list<array{path: string, nombre: string, mime: string}>  $adjuntos
     */
    public function __construct(
        public ReporteContableSuscripcion $suscripcion,
        public ReporteContable $reporte,
        public string $periodoTexto,
        public array $resultado,
        public array $adjuntos = [],
        public ?ReporteContablePublicacion $publicacion = null,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] %s — %s',
            config('app.name', 'anitaERP'),
            trim((string) $this->reporte->nombre) !== '' ? $this->reporte->nombre : 'Informe contable',
            $this->periodoTexto !== '' ? $this->periodoTexto : now()->format('m/Y'),
        );

        $mail = $this->subject($asunto)->view('mails.contable.reporte_definible_distribucion');

        foreach ($this->adjuntos as $adjunto) {
            if (! is_file($adjunto['path'])) {
                continue;
            }
            $mail->attach($adjunto['path'], [
                'as' => $adjunto['nombre'],
                'mime' => $adjunto['mime'],
            ]);
        }

        return $mail;
    }
}
