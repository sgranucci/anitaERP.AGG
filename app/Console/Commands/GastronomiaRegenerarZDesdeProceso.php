<?php

namespace App\Console\Commands;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Regenera el Informe Z de las jornadas cuyo Z MP/QR quedó desactualizado (órdenes tardías)
 * y cuyo recomputo Waitry COINCIDE con lo contabilizado en esas cuentas. Las que no coinciden
 * se listan aparte para revisión del asiento (no se toca el Z).
 */
class GastronomiaRegenerarZDesdeProceso extends Command
{
    protected $signature = 'gastronomia:regenerar-z-desde-proceso
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (default: inicio de mes actual)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: hoy)}
                            {--empresas= : IDs empresa separados por coma (default: config conciliación diaria)}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--aplicar : Persistir cambios (por defecto es dry-run)}';

    protected $description = 'Regenera Informe Z desde el proceso donde el recomputo = contabilizado; lista aparte las jornadas a revisar en el asiento';

    public function handle(GastronomiaCierreTotemInformeZService $service): int
    {
        @set_time_limit(0);
        $config = config('gastronomia.conciliacion_diaria_reporte', []);

        $desde = trim((string) ($this->option('fecha-desde') ?? '')) ?: Carbon::now()->startOfMonth()->toDateString();
        $hasta = trim((string) ($this->option('fecha-hasta') ?? '')) ?: Carbon::today()->toDateString();

        $empresasOpt = trim((string) ($this->option('empresas') ?? ''));
        $empresas = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))))
            : array_values(array_filter(array_map('intval', $config['empresas_ids'] ?? [1, 2, 3])));

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? max(0.0, (float) $toleranciaOpt)
            : max(0.0, (float) ($config['tolerancia'] ?? 0.02));

        $persistir = (bool) $this->option('aplicar');

        $this->line(sprintf(
            '%s | empresas %s | jornada %s → %s | tolerancia %.2f',
            $persistir ? 'APLICAR' : 'DRY-RUN (no graba)',
            implode(', ', $empresas),
            $desde,
            $hasta,
            $tolerancia,
        ));

        $cierres = CierreTotemJornadaGastronomia::query()
            ->whereHas('jornada', function ($q) use ($empresas, $desde, $hasta) {
                $q->whereIn('empresa_id', $empresas)
                    ->whereDate('fecha_jornada', '>=', $desde)
                    ->whereDate('fecha_jornada', '<=', $hasta)
                    ->where('estado', JornadaGastronomia::ESTADO_CERRADA);
            })
            ->with('jornada')
            ->get()
            ->sortBy(fn ($c) => [(int) $c->empresa_id, (string) ($c->jornada?->fecha_jornada?->format('Y-m-d') ?? '')]);

        $regenerar = [];
        $revisar = [];
        $errores = [];

        foreach ($cierres as $cierre) {
            $jornadaId = (int) $cierre->jornada_gastronomia_id;
            try {
                $r = $service->regenerarInformeZDesdeProceso($jornadaId, $tolerancia, $persistir);
            } catch (Throwable $e) {
                $errores[] = ['jornada_id' => $jornadaId, 'error' => $e->getMessage()];

                continue;
            }
            $decision = (string) ($r['decision'] ?? '');
            if ($decision === 'regenerar') {
                $regenerar[] = $r;
            } elseif ($decision === 'revisar_asiento') {
                $revisar[] = $r;
            }
        }

        $this->newLine();
        $this->info(sprintf('== Z REGENERADOS (recomputo Waitry = MP contabilizado)%s: %d ==', $persistir ? '' : ' [dry-run]', count($regenerar)));
        foreach ($regenerar as $r) {
            $this->line(sprintf(
                '  emp %d · %s (j#%d): MP Z %s → recomputo %s  (MP contab %s | total Z %s)%s',
                $r['empresa_id'], $r['fecha_jornada'], $r['jornada_id'],
                $this->fmt($r['mp_z'] ?? $r['z_actual']), $this->fmt($r['z_recomputado']),
                $this->fmt($r['mp_contabilizado'] ?? $r['contabilizado']),
                $this->fmt($r['z_actual']),
                $persistir ? ('  '.(($r['conciliacion_ok'] ?? false) ? 'OK' : 'DIF')) : '',
            ));
        }

        $this->newLine();
        $this->warn(sprintf('== A REVISAR EN EL ASIENTO (recomputo Waitry ≠ MP contabilizado): %d ==', count($revisar)));
        foreach ($revisar as $r) {
            $this->warn(sprintf(
                '  emp %d · %s (j#%d): MP Z %s | recomputo %s | MP contab %s | total Z %s | Δ recomputo↔MP contab %s',
                $r['empresa_id'], $r['fecha_jornada'], $r['jornada_id'],
                $this->fmt($r['mp_z'] ?? null), $this->fmt($r['z_recomputado']),
                $this->fmt($r['mp_contabilizado'] ?? null), $this->fmt($r['z_actual']),
                $this->fmt($r['diff_recomputado_contab']),
            ));
        }

        if ($errores !== []) {
            $this->newLine();
            $this->error('Errores: '.count($errores));
            foreach ($errores as $e) {
                $this->error('  j#'.$e['jornada_id'].': '.$e['error']);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumen: %d regenerad%s, %d a revisar asiento, %d error(es).',
            count($regenerar), $persistir ? 'os' : 'os (simulado)', count($revisar), count($errores),
        ));

        return self::SUCCESS;
    }

    private function fmt(mixed $v): string
    {
        return '$ '.number_format((float) $v, 2, ',', '.');
    }
}
