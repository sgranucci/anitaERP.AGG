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
        $alertaIe = ! empty($this->informe['alerta_ie']);
        $variacion = $this->informe['delta_mayor']['variacion_conjunto'] ?? null;
        $variacionTxt = $variacion === null
            ? 'base'
            : number_format((float) $variacion, 2, ',', '.');
        $cantidad = (int) ($this->informe['grupos_total'] ?? count($this->informe['grupos'] ?? []));
        $ieCantidad = (int) ($this->informe['ie_cuentas_controladas_total'] ?? 0);

        // Nombre de empresa primero: en la bandeja se distinguen aunque el resto del asunto se vea igual.
        $partesAlerta = [];
        if ($alertaFicha) {
            $partesAlerta[] = 'ficha '.$cantidad;
        }
        if ($alertaDelta) {
            $partesAlerta[] = 'delta '.$variacionTxt;
        }
        if ($alertaIe) {
            $partesAlerta[] = 'I/E '.$ieCantidad;
        }
        if ($partesAlerta !== []) {
            $asunto = sprintf(
                '%s — Deuda vs ficha / delta / I/E — %s — ALERTA (%s)',
                $empresa,
                $fecha,
                implode(', ', $partesAlerta)
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
