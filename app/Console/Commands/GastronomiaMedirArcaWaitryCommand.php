<?php

namespace App\Console\Commands;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaArcaWaitryMedicionSupport;
use Illuminate\Console\Command;

class GastronomiaMedirArcaWaitryCommand extends Command
{
    protected $signature = 'gastronomia:medir-arca-waitry
                            {--cfg-id=3 : configuracion_puntoventa_gastronomia (Tomasso PV3 = 3)}
                            {--empresa=1 : empresa_id}
                            {--repeticiones=3 : Repeticiones probe ARCA}
                            {--waitry-repeticiones=2 : Repeticiones getOrdersPOS}
                            {--renovar-token : Mide también login OAuth Waitry (invalida cache)}
                            {--ultimas=25 : Emisiones del log a analizar}
                            {--log= : Ruta al laravel.log}
                            {--solo-log : Solo analiza log, sin probes en vivo}';

    protected $description = 'Mide latencia ARCA (FECompUltimoAutorizado) y Waitry (token + getOrdersPOS) y resume emisiones del log';

    public function handle(GastronomiaArcaWaitryMedicionSupport $medicion): int
    {
        $cfgId = max(1, (int) $this->option('cfg-id'));
        $empresaId = max(1, (int) $this->option('empresa'));
        $logPath = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->with(['puntoventaCae', 'empresa'])
            ->find($cfgId);

        if ($cfg === null) {
            $this->error('No existe configuracion_puntoventa_gastronomia #'.$cfgId);

            return self::FAILURE;
        }

        $pv = $cfg->puntoventaCae;
        $this->info(sprintf(
            'Medición ARCA + Waitry · %s · PV %s · cfg #%d · empresa %s',
            $cfg->descripcion ?? '?',
            $pv?->codigo ?? '?',
            $cfgId,
            $cfg->empresa->nombre ?? (string) $empresaId,
        ));
        $this->newLine();

        if (! $this->option('solo-log')) {
            $this->medirArcaEnVivo($medicion, $empresaId, (int) ($pv?->id ?? 0));
            $this->newLine();
            $this->medirWaitryEnVivo($medicion, $empresaId);
            $this->newLine();
        }

        $this->analizarLog($medicion, $logPath, $cfgId);

        return self::SUCCESS;
    }

    private function medirArcaEnVivo(GastronomiaArcaWaitryMedicionSupport $medicion, int $empresaId, int $puntoventaId): void
    {
        $this->comment('── ARCA en vivo (FECompUltimoAutorizado) ──');

        if ($puntoventaId <= 0) {
            $this->warn('Sin puntoventa_cae_id en la configuración.');

            return;
        }

        if ((string) config('arca_wsfe.transporte', 'afip_php') !== 'soap') {
            $this->warn('ARCA_WSFE_TRANSPORTE ≠ soap — probe WSFE omitido.');
        }

        $reps = max(1, (int) $this->option('repeticiones'));
        $r = $medicion->medirArcaUltimoAutorizado($empresaId, $puntoventaId, $reps);

        if (! ($r['ok'] ?? false)) {
            $this->error('ARCA: '.($r['error'] ?? 'fallo'));
        } else {
            $this->info(sprintf(
                'ARCA OK · PV %s · cbte_tipo %d · último nro %s',
                $r['puntoventa_codigo'],
                $r['cbte_tipo'],
                $r['ultimo_nro'] !== null ? (string) $r['ultimo_nro'] : '?',
            ));
        }

        $this->line(sprintf(
            'Latencia probe: min %.0f ms · prom %.0f ms · max %.0f ms (%d muestras)',
            $r['min_ms'],
            $r['promedio_ms'],
            $r['max_ms'],
            count($r['muestras_ms']),
        ));
        $this->line('Muestras: '.implode(', ', array_map(static fn (float $v): string => number_format($v, 0, ',', '.').' ms', $r['muestras_ms'])));
        $this->line('Failover CAEA runtime: '.(! empty($r['failover_activo']) ? 'ACTIVO' : 'off'));
        $this->line('Nota: en emisión real también corre FECAESolicitar (~similar orden de magnitud que «arca_cae» del log).');
    }

    private function medirWaitryEnVivo(GastronomiaArcaWaitryMedicionSupport $medicion, int $empresaId): void
    {
        $this->comment('── Waitry en vivo (token + getOrdersPOS) ──');

        $reps = max(1, (int) $this->option('waitry-repeticiones'));
        $r = $medicion->medirWaitry($empresaId, $reps, (bool) $this->option('renovar-token'));

        if (! ($r['ok'] ?? false)) {
            $this->error('Waitry: '.($r['error'] ?? 'fallo'));
        } else {
            $this->info('Waitry OK · placeId '.$r['place_id']);
        }

        $this->line('Token (cache): '.number_format($r['token_cache_ms'], 0, ',', '.').' ms');
        if ($r['token_fresco_ms'] !== null) {
            $this->line('Token (login fresco): '.number_format($r['token_fresco_ms'], 0, ',', '.').' ms');
        }

        if ($r['get_orders_ms'] !== []) {
            $this->line(sprintf(
                'getOrdersPOS: min %.0f ms · prom %.0f ms · max %.0f ms',
                min($r['get_orders_ms']),
                $r['get_orders_promedio_ms'],
                max($r['get_orders_ms']),
            ));
            $this->line('Muestras: '.implode(', ', array_map(static fn (float $v): string => number_format($v, 0, ',', '.').' ms', $r['get_orders_ms'])));
        }

        $this->line('Nota: pushExternalOrder al facturar (papelito) suele tardar ~similar al «waitry_gap» del log, no a getOrdersPOS.');
    }

    private function analizarLog(GastronomiaArcaWaitryMedicionSupport $medicion, string $logPath, int $cfgId): void
    {
        $this->comment('── Histórico emisiones (log) cfg #'.$cfgId.' ──');

        if (! is_readable($logPath)) {
            $this->warn('No se puede leer '.$logPath);

            return;
        }

        $limite = max(1, (int) $this->option('ultimas'));
        $r = $medicion->resumirDesdeLog($logPath, $cfgId, $limite);

        if ($r['muestras'] === 0) {
            $this->warn('Sin emisiones profile para cfg #'.$cfgId.' en el log.');
            $this->line('Verifique GASTRONOMIA_EMISION_PROFILE=true y facturas recientes del PV.');

            return;
        }

        $this->info('Emisiones analizadas: '.$r['muestras']);

        $filasResumen = [
            ['ARCA FECompUltimoAutorizado (log)', $this->fmtStats($r['arca_ultimo_numero'])],
            ['ARCA FECAESolicitar (log)', $this->fmtStats($r['arca_solicita_cae'])],
            ['Emisión total servidor (log)', $this->fmtStats($r['emision_total'])],
            ['Waitry post-emisión → ok (log)', $this->fmtStats($r['waitry_post_emision']).' · n='.$r['waitry_post_emision']['muestras']],
            ['Impresión ncjetdirect (log)', $this->fmtStats($r['ticket_imprimir']).' · n='.$r['ticket_imprimir']['muestras']],
        ];

        $this->table(['Etapa', 'min / prom / max ms'], $filasResumen);

        $estimadoPapel = $r['emision_total']['prom'] + $r['waitry_post_emision']['prom'] + $r['ticket_imprimir']['prom'];
        $this->newLine();
        $this->line(sprintf(
            'Estimado F5 → papel (promedio log): ~%.0f ms (ARCA emisión %.0f + Waitry %.0f + impresora %.0f)',
            $estimadoPapel,
            $r['arca_ultimo_numero']['prom'] + $r['arca_solicita_cae']['prom'],
            $r['waitry_post_emision']['prom'],
            $r['ticket_imprimir']['prom'],
        ));

        if ($r['filas'] !== []) {
            $this->newLine();
            $tabla = [];
            foreach ($r['filas'] as $f) {
                $tabla[] = [
                    $f['fecha'],
                    $f['venta_id'] ?? '—',
                    number_format((float) $f['total_ms'], 0, ',', '.'),
                    $f['arca_nro_ms'] !== null ? number_format((float) $f['arca_nro_ms'], 0, ',', '.') : '—',
                    $f['arca_cae_ms'] !== null ? number_format((float) $f['arca_cae_ms'], 0, ',', '.') : '—',
                    $f['waitry_gap_ms'] !== null ? number_format((float) $f['waitry_gap_ms'], 0, ',', '.') : '—',
                    $f['imp_ms'] !== null ? number_format((float) $f['imp_ms'], 0, ',', '.') : '—',
                ];
            }

            $this->table(
                ['Fecha', 'Venta', 'Total ms', 'ARCA nro', 'ARCA CAE', 'Waitry gap', 'Imp ms'],
                array_slice($tabla, 0, 15),
            );

            if (count($tabla) > 15) {
                $this->line('… '.(count($tabla) - 15).' filas más en el log.');
            }
        }
    }

    /**
     * @param  array{prom:float,min:float,max:float}  $s
     */
    private function fmtStats(array $s): string
    {
        return sprintf(
            '%s / %s / %s',
            number_format($s['min'], 0, ',', '.'),
            number_format($s['prom'], 0, ',', '.'),
            number_format($s['max'], 0, ',', '.'),
        );
    }
}
