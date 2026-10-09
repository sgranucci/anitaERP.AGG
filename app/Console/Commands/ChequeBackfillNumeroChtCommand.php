<?php

namespace App\Console\Commands;

use App\Support\Caja\ChequeTerceroNumeroChequeBackfillSupport;
use Illuminate\Console\Command;

class ChequeBackfillNumeroChtCommand extends Command
{
    protected $signature = 'cheque:backfill-numero-cht
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Graba numerocheque de CHT importados que repitieron el interno}';

    protected $description = 'Corrige el número de cheque de terceros importados de Anita (cter_nro_cheque / cter_nro_e_cheq)';

    public function handle(): int
    {
        $persistir = (bool) $this->option('ejecutar');
        if ($persistir && $this->option('dry-run')) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $modo = $persistir ? 'EJECUTAR' : 'DRY-RUN';
        $this->info("cheque:backfill-numero-cht [{$modo}]");

        $r = ChequeTerceroNumeroChequeBackfillSupport::ejecutar($persistir);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['CHT con número = interno', $r['candidatos']],
                ['A actualizar', $r['a_actualizar']],
                ['Desde nro_echeq ya importado', $r['desde_nro_echeq']],
                ['Desde Anita (sin nro_echeq útil)', $r['desde_anita']],
                ['Sin número en Anita (se dejan)', $r['sin_numero']],
                ['Actualizados', $r['actualizados']],
            ]
        );

        foreach ($r['ejemplos'] as $e) {
            $this->line('  '.json_encode($e, JSON_UNESCAPED_UNICODE));
        }

        if (! $persistir) {
            $this->warn('Sin cambios. Para persistir: php artisan cheque:backfill-numero-cht --ejecutar');
        }

        return self::SUCCESS;
    }
}
