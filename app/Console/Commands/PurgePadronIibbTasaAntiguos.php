<?php

namespace App\Console\Commands;

use App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgePadronIibbTasaAntiguos extends Command
{
    protected $signature = 'padron-iibb-tasa:purge';

    protected $description = 'Elimina filas de padron_iibb_tasa cuya hastafecha es anterior a hace 2 meses (vigencia cerrada)';

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
            'fecha_corte_hastafecha' => $corte->toDateString(),
        ]);

        $this->info("Eliminados {$eliminados} registro(s) con hastafecha anterior a {$corte->format('Y-m-d')} (vigencia finalizada antes del corte).");

        return self::SUCCESS;
    }
}
