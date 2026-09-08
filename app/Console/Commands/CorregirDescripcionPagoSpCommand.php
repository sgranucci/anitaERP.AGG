<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Caja\CorregirDescripcionPagoSpSupport;
use Illuminate\Console\Command;

class CorregirDescripcionPagoSpCommand extends Command
{
    protected $signature = 'caja:corregir-descripcion-pago-sp
                            {--empresa= : Solo una empresa_id ERP}
                            {--dry-run : Solo informa, no actualiza ERP ni ctamov}';

    protected $description = 'Reemplaza «Pago SP N» por el detalle de la SP en ERP y ctamov Anita';

    public function handle(CorregirDescripcionPagoSpSupport $support): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        if ($dryRun) {
            $this->warn('Dry-run: no se modificarán ERP ni ctamov.');
        }

        $r = $support->ejecutar($dryRun, $empresaId > 0 ? $empresaId : null);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Candidatos (asiento↔SP)', (string) $r['candidatos']],
                ['ERP asiento upd', (string) $r['erp_asiento']],
                ['ERP movimiento upd', (string) $r['erp_movimiento']],
                ['ERP caja upd', (string) $r['erp_caja']],
                ['ctamov asientos', (string) $r['ctamov_asientos']],
                ['ctamov líneas', (string) $r['ctamov_lineas']],
                ['Omitidos sin detalle SP', (string) $r['omitidos_sin_detalle']],
                ['Omitidos sin ctamov Pago SP', (string) $r['omitidos_sin_ctamov']],
                ['Errores', (string) count($r['errores'])],
            ],
        );

        foreach (array_slice($r['errores'], 0, 20) as $err) {
            $this->error($err);
        }

        return $r['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
