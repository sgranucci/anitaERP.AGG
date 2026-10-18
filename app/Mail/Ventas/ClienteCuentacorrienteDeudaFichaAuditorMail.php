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
        $alerta = ! empty($this->informe['requiere_alerta']);
        $estado = $alerta ? 'ALERTA' : 'OK';
        $cantidad = (int) ($this->informe['grupos_total'] ?? count($this->informe['grupos'] ?? []));

        // Nombre de empresa primero: en la bandeja se distinguen aunque el resto del asunto se vea igual.
        $asunto = $alerta
            ? sprintf('%s — Deuda vs ficha clientes — %s — %s (%d)', $empresa, $fecha, $estado, $cantidad)
            : sprintf('%s — Deuda vs ficha clientes — %s — %s · todo cuadrado', $empresa, $fecha, $estado);

        return $this->subject($asunto)
            ->view('mails.ventas.cliente_cuentacorriente_deuda_ficha_auditor');
    }
}
