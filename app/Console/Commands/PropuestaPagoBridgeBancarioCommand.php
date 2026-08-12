<?php

namespace App\Console\Commands;

use App\Models\Compras\PropuestaPago;
use App\Support\Compras\PropuestaPagoBridgeBancarioSupport;
use Illuminate\Console\Command;

/**
 * Reintenta conciliación Interbanking de propuestas ejecutadas recientes.
 */
class PropuestaPagoBridgeBancarioCommand extends Command
{
    protected $signature = 'compras:bridge-bancario-propuestas
                            {--dias=14 : Días hacia atrás a considerar}
                            {--propuesta= : ID de propuesta puntual}';

    protected $description = 'Clearing bancario avanzado (scoring OP ↔ transferencias/extracto IB) para propuestas ejecutadas';

    public function handle(): int
    {
        if (! PropuestaPagoBridgeBancarioSupport::habilitado()) {
            $this->warn('Bridge deshabilitado (PROPUESTA_PAGO_BRIDGE_BANCARIO=false).');

            return self::SUCCESS;
        }

        $propuestaId = (int) $this->option('propuesta');
        if ($propuestaId > 0) {
            $r = PropuestaPagoBridgeBancarioSupport::intentarConciliarLote($propuestaId);
            $this->info($r['mensaje']);

            return self::SUCCESS;
        }

        $dias = max(1, (int) $this->option('dias'));
        $desde = now()->subDays($dias)->startOfDay();

        $ids = PropuestaPago::query()
            ->whereIn('estado', ['EJECUTADA', 'EJECUTADA_PARCIAL'])
            ->where('updated_at', '>=', $desde)
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('id');

        $this->info('Propuestas a reintentar: '.$ids->count());
        foreach ($ids as $id) {
            $r = PropuestaPagoBridgeBancarioSupport::intentarConciliarLote((int) $id);
            $this->line('#'.$id.' — '.$r['mensaje']);
        }

        return self::SUCCESS;
    }
}
