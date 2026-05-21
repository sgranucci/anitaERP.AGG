<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaWsfeCaeaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SolicitarCaeaQuincenal extends Command
{
    protected $signature = 'arca:solicitar-caea-quincenal {--empresa_id= : Solo esta empresa (id)}';

    protected $description = 'Solicita CAEA quincenal vía WSFEv1 para empresas asignadas a usuarios y persiste en MySQL';

    public function handle(ArcaWsfeCaeaService $caeaService): int
    {
        if ((string) config('arca_wsfe.transporte', 'afip_php') !== 'soap') {
            $this->warn('ARCA_WSFE_TRANSPORTE debe ser soap; no se ejecuta.');

            return self::SUCCESS;
        }

        $empresaId = $this->option('empresa_id');
        if ($empresaId !== null && $empresaId !== '') {
            $id = (int) $empresaId;
            $quincenas = \App\Support\Ventas\CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
            $ok = 0;
            $fail = 0;
            foreach ($quincenas as $q) {
                $r = $caeaService->solicitarYGuardar($id, (int) $q['periodo'], (int) $q['orden']);
                $this->line("Empresa {$id} — {$q['periodo']}/Q{$q['orden']}: ".($r['ok'] ? 'OK' : 'ERROR').' — '.$r['mensaje']);
                $r['ok'] ? $ok++ : $fail++;
            }

            return $fail > 0 ? self::FAILURE : self::SUCCESS;
        }

        $resultados = $caeaService->procesarQuincenasEnVentana();
        if ($resultados === []) {
            $this->info('Sin empresas elegibles o sin quincenas en ventana de solicitud.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($resultados as $r) {
            $line = "Empresa {$r['empresa_id']} — {$r['periodo']}/Q{$r['orden']}: "
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
