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
        $nuevos = (int) ($this->informe['desvios_nuevos_total'] ?? 0);
        $informados = (int) ($this->informe['desvios_informados_total'] ?? 0);

        if ($nuevos > 0) {
            $asunto = sprintf(
                '%s — Factura anticipada vs recepción — %s — ALERTA (%d nuevas)',
                $empresa,
                $fecha,
                $nuevos
            );
        } elseif ($informados > 0) {
            $asunto = sprintf(
                '%s — Factura anticipada vs recepción — %s — OK (%d ya informadas)',
                $empresa,
                $fecha,
                $informados
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
