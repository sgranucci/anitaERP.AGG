<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuitecrmNotaAuditoriaSemanalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fechaDesde,
        public string $fechaHasta,
        public int $totalNotas,
        public string $pdfContenido,
        public string $nombreArchivoPdf,
    ) {}

    public function build(): self
    {
        $asunto = sprintf(
            '[%s] Auditoría de notas CRM %s → %s (%d notas)',
            config('app.name', 'anitaERP'),
            $this->fechaDesde,
            $this->fechaHasta,
            $this->totalNotas,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.suitecrm_nota_auditoria_semanal')
            ->attachData(
                $this->pdfContenido,
                $this->nombreArchivoPdf,
                ['mime' => 'application/pdf'],
            );
    }
}
