<?php

declare(strict_types=1);

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProveedorCuentacorrienteDeudaFichaAuditorMail extends Mailable
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
        $alertaFicha = ! empty($this->informe['alerta_ficha']);
        $alertaDelta = ! empty($this->informe['alerta_delta']);
        $alertaAnticipos = ! empty($this->informe['alerta_anticipos']);
        $variaciones = (int) ($this->informe['delta_mayor']['variaciones'] ?? 0);
        $variacionesAnticipos = (int) ($this->informe['delta_mayor']['variaciones_anticipos'] ?? 0);
        $cantidad = (int) ($this->informe['grupos_total'] ?? count($this->informe['grupos'] ?? []));

        // Nombre de empresa primero: en la bandeja se distinguen aunque el resto del asunto se vea igual.
        if ($alertaFicha && $alertaDelta) {
            $asunto = sprintf(
                '%s — Deuda vs ficha y delta mayor — %s — ALERTA (ficha %d, delta %d)',
                $empresa,
                $fecha,
                $cantidad,
                $variaciones
            );
        } elseif ($alertaDelta && $alertaAnticipos) {
            $asunto = sprintf(
                '%s — Proveedores y anticipos — %s — ALERTA (delta %d, anticipos %d)',
                $empresa,
                $fecha,
                $variaciones,
                $variacionesAnticipos
            );
        } elseif ($alertaDelta) {
            $asunto = sprintf(
                '%s — Delta mayor vs deuda proveedores — %s — ALERTA (%d)',
                $empresa,
                $fecha,
                $variaciones
            );
        } elseif ($alertaFicha && $alertaAnticipos) {
            $asunto = sprintf(
                '%s — Deuda vs ficha y anticipos — %s — ALERTA (ficha %d, anticipos %d)',
                $empresa,
                $fecha,
                $cantidad,
                $variacionesAnticipos
            );
        } elseif ($alertaAnticipos) {
            $asunto = sprintf(
                '%s — Anticipos a proveedores — %s — ALERTA (%d)',
                $empresa,
                $fecha,
                $variacionesAnticipos
            );
        } elseif ($alertaFicha) {
            $asunto = sprintf(
                '%s — Deuda vs ficha proveedores — %s — ALERTA (%d)',
                $empresa,
                $fecha,
                $cantidad
            );
        } else {
            $asunto = sprintf(
                '%s — Deuda vs ficha proveedores — %s — OK · todo cuadrado',
                $empresa,
                $fecha
            );
        }

        return $this->subject($asunto)
            ->view('mails.compras.proveedor_cuentacorriente_deuda_ficha_auditor');
    }
}
