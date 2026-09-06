<?php

namespace App\Console\Commands;

use App\Services\Wigos\WigosServidorActivoMonitorService;
use App\Support\Wigos\WigosActiveServerStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorearServidorActivoWigos extends Command
{
    protected $signature = 'wigos:monitorear-servidor-activo';

    protected $description = 'Comprueba SQL Server Wigos (A/B por empresa) y publica el alias ONLINE en runtime';

    public function handle(WigosServidorActivoMonitorService $monitor): int
    {
        if (! filter_var(config('wigos.habilitado', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->line('WIGOS_HABILITADO=false — nada que monitorear.');

            return self::SUCCESS;
        }

        if (! filter_var(config('wigos.monitor_servidor_activo.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->line('Monitor Wigos deshabilitado (WIGOS_MONITOR_SERVIDOR_ACTIVO=false).');

            return self::SUCCESS;
        }

        $resultados = $monitor->ejecutarChequeos();
        if ($resultados === []) {
            $this->warn('Sin empresas/conexiones Wigos para monitorear.');

            return self::SUCCESS;
        }

        foreach ($resultados as $r) {
            $emp = (int) $r['empresa_id'];
            $activo = $r['activo'] ?? '?';
            $preferido = $r['preferido'] ?? '?';
            $line = "empresa={$emp} activo={$activo} preferido_config={$preferido}";

            foreach (['A', 'B'] as $alias) {
                $a = $r['aliases'][$alias] ?? null;
                if (! is_array($a)) {
                    continue;
                }
                if (! empty($a['skipped'])) {
                    $line .= " | {$alias}=omitido";

                    continue;
                }
                $estado = ! empty($a['ok']) ? 'OK' : 'FALLO';
                $host = $a['host'] ?? '';
                $line .= " | {$alias}={$estado}";
                if ($host !== '') {
                    $line .= "({$host})";
                }
                if (empty($a['ok']) && ! empty($a['error'])) {
                    $line .= ' '.mb_substr((string) $a['error'], 0, 120);
                }
            }

            $this->line($line);
            Log::info('wigos:monitorear-servidor-activo — '.$line);
        }

        $snapshot = WigosActiveServerStore::snapshot();
        if ($snapshot !== []) {
            $this->line('Estado: '.json_encode($snapshot, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
