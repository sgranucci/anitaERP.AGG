<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaConectividadMonitorService;
use App\Support\Arca\ArcaFailoverStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorearConectividadArca extends Command
{
    protected $signature = 'arca:monitorear-conectividad';

    protected $description = 'Comprueba conectividad ARCA (último comprobante autorizado) y activa/desactiva failover CAEA en runtime';

    public function handle(ArcaConectividadMonitorService $monitor): int
    {
        if (! filter_var(config('arca.monitor_conectividad.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->line('Monitor ARCA deshabilitado (ARCA_MONITOR_CONECTIVIDAD=false).');

            return self::SUCCESS;
        }

        $wsfeSoap = (string) config('arca_wsfe.transporte', 'afip_php') === 'soap';
        $mtxcaSoap = (string) config('arca_mtxca.transporte', 'afip_php') === 'soap';

        if (! $wsfeSoap && ! $mtxcaSoap) {
            $this->warn('Sin transporte SOAP (ARCA_WSFE_TRANSPORTE / ARCA_MTXCA_TRANSPORTE). Nada que monitorear.');

            return self::SUCCESS;
        }

        $resultados = $monitor->ejecutarChequeos();

        if ($resultados === []) {
            $this->line('Sin webservices SOAP activos para monitorear.');

            return self::SUCCESS;
        }

        foreach ($resultados as $r) {
            $ws = $r['webservice'];
            if ($r['skipped']) {
                $line = "{$ws}: OMITIDO — ".($r['skip_reason'] ?? 'sin parámetros');
                $this->warn($line);
                Log::warning('arca:monitorear-conectividad — '.$line);

                continue;
            }

            $estado = $r['ok'] ? 'OK' : 'FALLO';
            $failover = $r['failover_active'] ? 'failover CAEA ON' : 'failover CAEA off';
            $ultimo = $r['ultimo_nro'] !== null ? ' ultimo='.$r['ultimo_nro'] : '';
            $line = "{$ws}: {$estado} ({$failover}){$ultimo}";
            if (! $r['ok'] && ! empty($r['error'])) {
                $line .= ' — '.mb_substr((string) $r['error'], 0, 200);
            }
            if (! empty($r['probe'])) {
                $line .= ' ['.$r['probe'].']';
            }

            $r['ok'] ? $this->info($line) : $this->error($line);
            Log::info('arca:monitorear-conectividad — '.$line);
        }

        $snapshot = ArcaFailoverStore::snapshot();
        if ($snapshot !== []) {
            $this->line('Estado failover: '.json_encode($snapshot, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
