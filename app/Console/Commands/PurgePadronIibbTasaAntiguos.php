<?php

namespace App\Console\Commands;

use App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgePadronIibbTasaAntiguos extends Command
{
    protected $signature = 'padron-iibb-tasa:purge';

    protected $description = 'Elimina padron_iibb_tasa con vigencia vencida (hastafecha anterior a hoy menos 2 meses; rango desdefecha–hastafecha)';

    public function __construct(
        private Padron_Iibb_TasaRepositoryInterface $padron_iibb_tasaRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $corte = now()->subMonthsNoOverflow(2)->startOfDay();

        $eliminados = $this->padron_iibb_tasaRepository->eliminarPorHastafechaAnteriorA($corte);

        Log::info('padron_iibb_tasa:purge', [
            'eliminados' => $eliminados,
            'corte_hastafecha_lt' => $corte->toDateString(),
            'criterio' => 'hastafecha < corte (periodo desdefecha..hastafecha ya finalizado)',
        ]);

        $this->info("Eliminados {$eliminados} registro(s): hastafecha anterior a {$corte->format('Y-m-d')} (retención 2 meses desde desdefecha/hastafecha).");

        return self::SUCCESS;
    }
}
