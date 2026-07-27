<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Auditoría mensual por MEDIO DE COBRO: Z ↔ contabilizado (ERP, sin ctamov) del mes en curso.
 */
class GastronomiaAuditoriaMediosMensual extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $resumen  salida de resumenMensualMediosDirecto()
     */
    public function __construct(
        public array $resumen,
        public string $fechaDesde,
        public string $fechaHasta,
        public float $tolerancia,
        public bool $hayDiferencias,
        public string $csvContenido,
        public string $nombreArchivoCsv,
    ) {
    }

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Auditoría gastronomía por medio de cobro (Z↔contab) %s → %s — %s',
            config('app.name', 'anitaERP'),
            $this->fechaDesde,
            $this->fechaHasta,
            $this->hayDiferencias ? 'DIFERENCIAS' : 'OK',
        );

        return $this->subject($asunto)
            ->view('mails.ventas.gastronomia_auditoria_medios_mensual')
            ->attachData($this->csvContenido, $this->nombreArchivoCsv, ['mime' => 'text/csv']);
    }
}
