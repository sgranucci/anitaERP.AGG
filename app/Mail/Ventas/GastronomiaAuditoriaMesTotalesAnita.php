<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GastronomiaAuditoriaMesTotalesAnita extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $excelContenido,
        public string $nombreArchivoExcel,
    ) {
    }

    public function build(): self
    {
        $desde = (string) ($this->informe['fecha_desde'] ?? '');
        $hasta = (string) ($this->informe['fecha_hasta'] ?? '');
        $hayAlerta = (bool) ($this->informe['hay_alertas'] ?? false);
        $estado = $hayAlerta ? 'ALERTA' : 'OK';

        $asunto = sprintf(
            '[%s] Auditoría Anita mensual %s%s — %s',
            config('app.name', 'anitaERP'),
            $desde,
            $hasta !== $desde ? ' → '.$hasta : '',
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.gastronomia_auditoria_mes_totales_anita')
            ->attachData(
                $this->excelContenido,
                $this->nombreArchivoExcel,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
    }
}
