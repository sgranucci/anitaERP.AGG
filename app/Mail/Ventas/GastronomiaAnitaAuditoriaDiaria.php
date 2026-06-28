<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GastronomiaAnitaAuditoriaDiaria extends Mailable
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
        $fecha = (string) ($this->informe['fecha_jornada'] ?? $this->informe['fecha_calendario'] ?? '');
        $estado = 'OK';

        foreach (['gastro', 'estacionamiento'] as $circuito) {
            $post = $this->informe[$circuito]['post']['resumen_global'] ?? [];
            $rep = $this->informe[$circuito]['replicacion'] ?? [];
            $faltantesFinal = (int) ($post['conteo']['solo_erp'] ?? 0);
            $delta = (float) ($post['delta_totales']['total'] ?? 0);
            $replicadas = (int) ($rep['replicadas'] ?? 0);
            $errores = count($rep['errores'] ?? []);

            if ($faltantesFinal > 0 || $errores > 0 || abs($delta) > 0.02) {
                $estado = 'ALERTA';
            } elseif ($replicadas > 0 && $estado !== 'ALERTA') {
                $estado = 'REPARADO';
            }
        }

        $asunto = sprintf(
            '[%s] Anita jornada — %s — %s',
            config('app.name', 'anitaERP'),
            $fecha,
            $estado,
        );

        return $this->subject($asunto)
            ->view('mails.ventas.gastronomia_anita_auditoria_diaria');
    }
}
