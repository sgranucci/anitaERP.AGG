<?php

namespace App\Console\Commands;

use App\Support\Caja\ChequeTerceroNegociableBackfillSupport;
use Illuminate\Console\Command;

class ChequeBackfillNegociableChtCommand extends Command
{
    protected $signature = 'cheque:backfill-negociable-cht
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste negociable/nro_echeq desde Anita cter_interior}';

    protected $description = 'Backfill CHT: negociable E/N y nro_echeq desde Anita (cter_interior=3 → e-cheq)';

    public function handle(): int
    {
        $persistir = (bool) $this->option('ejecutar');
        if ($persistir && $this->option('dry-run')) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $modo = $persistir ? 'EJECUTAR' : 'DRY-RUN';
        $this->info("cheque:backfill-negociable-cht [{$modo}]");

        $r = ChequeTerceroNegociableBackfillSupport::ejecutar($persistir);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Filas Anita (rango ERP)', $r['anita_filas']],
                ['CHT ERP con nro_interno', $r['erp_total']],
                ['Sin match Anita', $r['sin_match']],
                ['Ya OK', $r['ya_ok']],
                ['Pasarían a e-cheq (E)', $r['a_e']],
                ['Pasarían a físico (N)', $r['a_n']],
                ['Actualizados', $r['actualizados']],
            ]
        );

        if ($r['ejemplos_e'] !== []) {
            $this->line('Ejemplos → E:');
            foreach ($r['ejemplos_e'] as $e) {
                $this->line('  '.json_encode($e, JSON_UNESCAPED_UNICODE));
            }
        }
        if ($r['ejemplos_n'] !== []) {
            $this->line('Ejemplos → N:');
            foreach ($r['ejemplos_n'] as $e) {
                $this->line('  '.json_encode($e, JSON_UNESCAPED_UNICODE));
            }
        }

        if (! $persistir) {
            $this->warn('Sin cambios. Para persistir: php artisan cheque:backfill-negociable-cht --ejecutar');
        }

        return self::SUCCESS;
    }
}
