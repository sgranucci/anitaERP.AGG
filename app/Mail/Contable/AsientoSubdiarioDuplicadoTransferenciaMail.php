<?php

declare(strict_types=1);

namespace App\Mail\Contable;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AsientoSubdiarioDuplicadoTransferenciaMail extends Mailable
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
        $cantidad = (int) ($this->informe['cantidad'] ?? 0);
        $importe = number_format((float) ($this->informe['importe_total'] ?? 0), 2, ',', '.');

        if (! empty($this->informe['requiere_alerta'])) {
            $asunto = sprintf(
                '%s — Asientos Anita duplicados (subdiario vs transferencia) — %s — ALERTA (%d / $%s)',
                $empresa,
                $fecha,
                $cantidad,
                $importe
            );
        } else {
            $asunto = sprintf(
                '%s — Asientos Anita duplicados (subdiario vs transferencia) — %s — OK',
                $empresa,
                $fecha
            );
        }

        return $this->subject($asunto)
            ->view('mails.contable.asiento_subdiario_duplicado_transferencia');
    }
}
