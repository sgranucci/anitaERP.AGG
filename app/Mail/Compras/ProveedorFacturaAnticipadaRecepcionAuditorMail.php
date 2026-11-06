<?php

declare(strict_types=1);

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorFacturaAnticipadaRecepcionAuditorMail extends Mailable
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
        $empresa = trim((string) ($this->informe['empresa_nombre'] ?? ''));
        if ($empresa === '') {
            $empresa = 'Empresa #'.(int) ($this->informe['empresa_id'] ?? 0);
        }
        $fecha = (string) ($this->informe['fecha_fmt'] ?? $this->informe['fecha'] ?? '');
        $desvios = (int) ($this->informe['desvios_total'] ?? 0);

        if ($desvios > 0) {
            $asunto = sprintf(
                '%s — Factura anticipada vs recepción — %s — ALERTA (%d OC)',
                $empresa,
                $fecha,
                $desvios
            );
        } else {
            $asunto = sprintf(
                '%s — Factura anticipada vs recepción — %s — OK',
                $empresa,
                $fecha
            );
        }

        return $this->subject($asunto)
            ->view('mails.compras.proveedor_factura_anticipada_recepcion_auditor');
    }
}
