<?php

declare(strict_types=1);

namespace App\Jobs\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaCaeaJornadaVerificacionSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tras cerrar la jornada: verifica la integridad de los comprobantes CAEA del día
 * (número, emisión gastro/estac, identificador_pc, PV compartido). Solo lectura.
 * No reemplaza a los conciliadores de siempre; documenta lo específico del failover CAEA.
 */
class GastronomiaVerificarCaeaCierreJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 300;

    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $jornadaId,
    ) {
        $this->onQueue((string) config('gastronomia.verificar_caea_cierre.cola', 'default'));
    }

    public function uniqueId(): string
    {
        return 'gastronomia-verificar-caea-jornada-'.$this->jornadaId;
    }

    public function handle(GastronomiaCaeaJornadaVerificacionSupport $support): void
    {
        if (! filter_var(config('gastronomia.verificar_caea_cierre.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $jornada = JornadaGastronomia::find($this->jornadaId);
        if ($jornada === null) {
            return;
        }

        $empresaId = (int) $jornada->empresa_id;
        $fecha = (string) $jornada->fecha_jornada;

        $r = $support->verificar($empresaId, $fecha);
        $vc = $r['ventas_caea'];

        if ((int) $vc['total_cant'] === 0) {
            return;
        }

        $contexto = [
            'jornada_id' => $this->jornadaId,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fecha,
            'caea_total' => $vc['total_cant'],
            'sin_cae' => $vc['sin_cae'],
            'huerfanas' => count($vc['huerfanas']),
            'gastro_sin_pc' => $r['gastro_sin_pc'],
            'estacionamiento_compartido' => $vc['estacionamiento']['cant'],
            'problemas' => array_map(static fn ($p) => $p['nivel'].': '.$p['texto'], $r['problemas']),
        ];

        if ($r['ok']) {
            Log::info('gastronomia.verificar_caea_cierre.ok', $contexto);

            return;
        }

        Log::warning('gastronomia.verificar_caea_cierre.observaciones', $contexto);

        $this->avisarPorMail($r, $contexto);
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array<string, mixed>  $contexto
     */
    private function avisarPorMail(array $r, array $contexto): void
    {
        if (! filter_var(config('gastronomia.verificar_caea_cierre.mail', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $destino = trim((string) config('gastronomia.verificar_caea_cierre.email', ''));
        if ($destino === '') {
            return;
        }

        $cuerpo = sprintf(
            "Verificación CAEA jornada %s (empresa %d — %s)\n\nProblemas detectados:\n- %s\n\nResumen: %d comprobantes CAEA, sin nro %d, huérfanas %d, salón sin PC %d.",
            $r['fecha_jornada'],
            $r['empresa_id'],
            $r['empresa_nombre'],
            implode("\n- ", $contexto['problemas'] ?: ['(sin detalle)']),
            $contexto['caea_total'],
            $contexto['sin_cae'],
            $contexto['huerfanas'],
            $contexto['gastro_sin_pc'],
        );

        try {
            Mail::raw($cuerpo, static function ($m) use ($destino, $r): void {
                $m->to($destino)->subject(sprintf('CAEA jornada %s empresa %d: observaciones', $r['fecha_jornada'], $r['empresa_id']));
            });
        } catch (Throwable $e) {
            Log::error('gastronomia.verificar_caea_cierre.mail_error', [
                'error' => $e->getMessage(),
                'jornada_id' => $this->jornadaId,
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('gastronomia.verificar_caea_cierre.job_failed', [
            'jornada_id' => $this->jornadaId,
            'error' => $e?->getMessage(),
        ]);
    }
}
