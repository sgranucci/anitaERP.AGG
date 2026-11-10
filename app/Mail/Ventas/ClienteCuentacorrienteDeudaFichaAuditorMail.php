<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClienteCuentacorrienteDeudaFichaAuditorMail extends Mailable
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
        $variacion = $this->informe['delta_mayor']['variacion_conjunto'] ?? null;
        $variacionTxt = $variacion === null
            ? 'base'
            : number_format((float) $variacion, 2, ',', '.');
        $cantidad = (int) ($this->informe['grupos_total'] ?? count($this->informe['grupos'] ?? []));

        if ($alertaFicha && $alertaDelta) {
            $asunto = sprintf(
                '%s — Deuda vs ficha y delta clientes — %s — ALERTA (ficha %d, delta %s)',
                $empresa,
                $fecha,
                $cantidad,
                $variacionTxt
            );
        } elseif ($alertaDelta) {
            $asunto = sprintf(
                '%s — Delta clientes — %s — ALERTA (%s)',
                $empresa,
                $fecha,
                $variacionTxt
            );
        } elseif ($alertaFicha) {
            $asunto = sprintf(
                '%s — Deuda vs ficha clientes — %s — ALERTA (%d)',
                $empresa,
                $fecha,
                $cantidad
            );
        } else {
            $asunto = sprintf(
                '%s — Deuda vs ficha clientes — %s — OK · todo cuadrado',
                $empresa,
                $fecha
            );
        }

        return $this->subject($asunto)
            ->view('mails.ventas.cliente_cuentacorriente_deuda_ficha_auditor');
    }
}
