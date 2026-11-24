<?php

namespace App\Console\Commands;

use App\Support\Caja\ChequeRechazadoAsientoDescripcionBackfillSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ChequeBackfillDescripcionNdRechazoCommand extends Command
{
    protected $signature = 'cheque:backfill-descripcion-nd-rechazo
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste observación de asiento/movimientos}';

    protected $description = 'Ferli: backfill de observación de asientos NDR por cheque rechazado (Rech.#nro int.banco)';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Solo Ferli. EMPRESA actual: '.EntornoEmpresaSupport::codigo());

            return self::FAILURE;
        }

        $persistir = (bool) $this->option('ejecutar');
        if ($persistir && $this->option('dry-run')) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $modo = $persistir ? 'EJECUTAR' : 'DRY-RUN';
        $this->info("cheque:backfill-descripcion-nd-rechazo [{$modo}] DB=".config('database.connections.mysql.database'));

        try {
            $r = ChequeRechazadoAsientoDescripcionBackfillSupport::ejecutar($persistir);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Cheques con NDR', $r['cheques']],
                ['Asientos', $r['asientos']],
                ['Cabeceras a actualizar', $r['cabeceras_a_actualizar']],
                ['Movimientos a actualizar', $r['movimientos_a_actualizar']],
                ['Cabeceras actualizadas', $r['cabeceras_actualizadas']],
                ['Movimientos actualizados', $r['movimientos_actualizados']],
                ['Sin asiento', $r['sin_asiento']],
            ]
        );

        foreach ($r['filas'] as $f) {
            if (! empty($f['sin_asiento'])) {
                $this->warn(sprintf(
                    '  cheque=%d venta=%d %s SIN ASIENTO (nuevo=[%s])',
                    $f['cheque_id'],
                    $f['venta_id'],
                    $f['codigo'],
                    $f['obs_nueva']
                ));
                continue;
            }
            $this->line(sprintf(
                '  %s asiento=%d nro=%s fecha=%s | [%s] → [%s] cab=%s movs=%d/%d',
                $f['codigo'],
                $f['asiento_id'],
                $f['numeroasiento'] ?? '',
                $f['fecha'] ?? '',
                $f['obs_actual'],
                $f['obs_nueva'],
                ! empty($f['cab_update']) ? 'SI' : 'no',
                (int) ($f['movs_update'] ?? 0),
                (int) ($f['movs_total'] ?? 0),
            ));
        }

        if (! $persistir) {
            $this->warn('Sin cambios. Para persistir (tras OK del operador): php artisan cheque:backfill-descripcion-nd-rechazo --ejecutar');
        }

        return self::SUCCESS;
    }
}
