<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaCaeaQuincenalOrquestadorService;
use App\Support\Ventas\CaeaQuincenaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SolicitarCaeaQuincenal extends Command
{
    protected $signature = 'arca:solicitar-caea-quincenal {--empresa_id= : Solo esta empresa (id)}';

    protected $description = 'Solicita CAEA quincenal vía ARCA (WSFEv1 o WSMTXCA) y persiste en arca_caea';

    public function handle(ArcaCaeaQuincenalOrquestadorService $orquestador): int
    {
        $wsfeSoap = (string) config('arca_wsfe.transporte', 'afip_php') === 'soap';
        $mtxcaSoap = (string) config('arca_mtxca.transporte', 'afip_php') === 'soap';

        if (! $wsfeSoap && ! $mtxcaSoap) {
            $this->warn('Ningún transporte SOAP activo (ARCA_WSFE_TRANSPORTE / ARCA_MTXCA_TRANSPORTE).');

            return self::SUCCESS;
        }

        $empresaId = $this->option('empresa_id');
        if ($empresaId !== null && $empresaId !== '') {
            $id = (int) $empresaId;
            $webservice = $orquestador->webserviceCaeaEmpresa($id);
            $quincenas = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
            $ok = 0;
            $fail = 0;
            foreach ($quincenas as $q) {
                $r = $orquestador->solicitarPorWebservice($webservice, $id, (int) $q['periodo'], (int) $q['orden']);
                $this->line("Empresa {$id} ({$webservice}) — {$q['periodo']}/Q{$q['orden']}: ".($r['ok'] ? 'OK' : 'ERROR').' — '.$r['mensaje']);
                $r['ok'] ? $ok++ : $fail++;
            }

            return $fail > 0 ? self::FAILURE : self::SUCCESS;
        }

        $resultados = $orquestador->procesarQuincenasEnVentana();
        if ($resultados === []) {
            $this->info('Sin empresas elegibles o sin quincenas en ventana de solicitud.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($resultados as $r) {
            $line = "Empresa {$r['empresa_id']} ({$r['webservice']}) — {$r['periodo']}/Q{$r['orden']}: "
                .($r['ok'] ? 'OK' : 'ERROR').' — '.$r['mensaje'];
            $this->line($line);
            if (! $r['ok']) {
                Log::info('arca:solicitar-caea-quincenal — '.$line);
            }
            $r['ok'] ? $ok++ : $fail++;
        }

        $this->info("Finalizado: {$ok} correctos, {$fail} con error.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
