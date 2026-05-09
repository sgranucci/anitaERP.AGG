<?php

namespace App\Console\Commands;

use App\Repositories\Configuracion\Padron_Iibb_CabaRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgePadronIibbCabaAntiguos extends Command
{
    protected $signature = 'padron-iibb-caba:purge';

    protected $description = 'Elimina filas de padron_iibb_caba cuya hastafecha es anterior a hace 2 meses (vigencia cerrada)';

    public function __construct(
        private Padron_Iibb_CabaRepositoryInterface $padron_iibb_cabaRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $corte = now()->subMonthsNoOverflow(2)->startOfDay();

        $eliminados = $this->padron_iibb_cabaRepository->eliminarPorHastafechaAnteriorA($corte);

        Log::info('padron_iibb_caba:purge', [
            'eliminados' => $eliminados,
            'fecha_corte_hastafecha' => $corte->toDateString(),
        ]);

        $this->info("Eliminados {$eliminados} registro(s) con hastafecha anterior a {$corte->format('Y-m-d')} (vigencia finalizada antes del corte).");

        return self::SUCCESS;
    }
}
