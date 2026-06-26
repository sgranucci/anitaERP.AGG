<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GastronomiaConciliacionDiariaReporte extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $csvContenido,
        public string $nombreArchivoCsv,
        public ?string $excelContenido = null,
        public ?string $nombreArchivoExcel = null,
    ) {
    }

    public function build(): self
    {
        $desde = (string) ($this->informe['fecha_desde'] ?? '');
        $hasta = (string) ($this->informe['fecha_hasta'] ?? '');
        $hayDif = (bool) ($this->informe['hay_diferencias'] ?? false);
        $estado = $hayDif ? 'DIFERENCIAS' : 'OK';

        $asunto = sprintf(
            '[%s] Conciliación gastronomía PC/PV %s%s — %s',
            config('app.name', 'anitaERP'),
            $desde,
            $hasta !== $desde ? ' → '.$hasta : '',
            $estado,
        );

        $mail = $this->subject($asunto)
            ->view('mails.ventas.gastronomia_conciliacion_diaria_reporte');

        if ($this->excelContenido !== null && $this->nombreArchivoExcel !== null) {
            $mail->attachData(
                $this->excelContenido,
                $this->nombreArchivoExcel,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
        }

        return $mail->attachData(
            $this->csvContenido,
            $this->nombreArchivoCsv,
            ['mime' => 'text/csv'],
        );
    }
}
