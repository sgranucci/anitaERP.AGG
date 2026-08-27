<?php

namespace App\Console\Commands;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Regenera el Informe Z solo si el recomputo del proceso coincide con la venta Waitry del cierre
 * (MP/QR). No usa el asiento: a las 07:45 todavía no está. Si el recomputo da $0 y hay venta
 * Waitry/ERP, no se toca el Z.
 */
class GastronomiaRegenerarZDesdeProceso extends Command
{
    protected $signature = 'gastronomia:regenerar-z-desde-proceso
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (default: inicio de mes actual)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: hoy)}
                            {--empresas= : IDs empresa separados por coma (default: config conciliación diaria)}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--aplicar : Persistir cambios (por defecto es dry-run)}';

    protected $description = 'Regenera Informe Z si el recomputo = venta Waitry del cierre; no pisa a $0 si hay venta Waitry/ERP';

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
        $omitidosCero = [];
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
            } elseif ($decision === 'omitido_recomputo_cero') {
                $omitidosCero[] = $r;
            } elseif (in_array($decision, ['revisar_venta', 'revisar_asiento'], true)) {
                $revisar[] = $r;
            }
        }

        $this->newLine();
        $this->info(sprintf('== Z REGENERADOS (recomputo = venta Waitry)%s: %d ==', $persistir ? '' : ' [dry-run]', count($regenerar)));
        foreach ($regenerar as $r) {
            $this->line(sprintf(
                '  emp %d · %s (j#%d): Z %s → recomputo %s  (Waitry %s | ERP %s)%s',
                $r['empresa_id'], $r['fecha_jornada'], $r['jornada_id'],
                $this->fmt($r['mp_z'] ?? $r['z_actual']), $this->fmt($r['z_recomputado']),
                $this->fmt($r['venta_waitry'] ?? null),
                $this->fmt($r['venta_erp'] ?? null),
                $persistir ? ('  '.(($r['conciliacion_ok'] ?? false) ? 'OK' : 'DIF')) : '',
            ));
        }

        $this->newLine();
        $this->warn(sprintf('== NO SE PISÓ EL Z (recomputo $0 pero hay venta Waitry/ERP): %d ==', count($omitidosCero)));
        foreach ($omitidosCero as $r) {
            $this->warn(sprintf(
                '  emp %d · %s (j#%d): recomputo %s | Waitry %s | ERP %s | Z actual %s',
                $r['empresa_id'], $r['fecha_jornada'], $r['jornada_id'],
                $this->fmt($r['z_recomputado'] ?? null),
                $this->fmt($r['venta_waitry'] ?? null),
                $this->fmt($r['venta_erp'] ?? null),
                $this->fmt($r['mp_z'] ?? $r['z_actual'] ?? null),
            ));
        }

        $this->newLine();
        $this->warn(sprintf('== A REVISAR (recomputo ≠ venta Waitry): %d ==', count($revisar)));
        foreach ($revisar as $r) {
            $this->warn(sprintf(
                '  emp %d · %s (j#%d): Z %s | recomputo %s | Waitry %s | ERP %s | Δ recomputo↔Waitry %s',
                $r['empresa_id'], $r['fecha_jornada'], $r['jornada_id'],
                $this->fmt($r['mp_z'] ?? null), $this->fmt($r['z_recomputado']),
                $this->fmt($r['venta_waitry'] ?? null),
                $this->fmt($r['venta_erp'] ?? null),
                $this->fmt($r['diff_recomputado_waitry'] ?? null),
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
            'Resumen: %d regenerad%s, %d omitidos por recomputo $0, %d a revisar, %d error(es).',
            count($regenerar), $persistir ? 'os' : 'os (simulado)', count($omitidosCero), count($revisar), count($errores),
        ));

        return self::SUCCESS;
    }

    private function fmt(mixed $v): string
    {
        return '$ '.number_format((float) $v, 2, ',', '.');
    }
}
