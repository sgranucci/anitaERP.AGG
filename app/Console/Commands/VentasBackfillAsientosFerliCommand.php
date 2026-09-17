<?php

namespace App\Console\Commands;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\VentaAsientoBackfillFerliSupport;
use Illuminate\Console\Command;

/**
 * Ferli: genera asientos ERP faltantes de FAC/NC emitidas por el ERP (OT/picking).
 * Dry-run por defecto. No toca el lote importado Anita (sin venta_emision).
 */
class VentasBackfillAsientosFerliCommand extends Command
{
    protected $signature = 'ventas:backfill-asientos-ferli
                            {--desde=2026-09-10 : Fecha created_at desde (Y-m-d)}
                            {--dry-run : Solo informa (default si no hay --ejecutar)}
                            {--ejecutar : Persiste asientos ERP (sin ctamov Anita)}';

    protected $description = 'CALZADOS FERLI: backfill asientos de facturas ERP de la semana sin contabilidad';

    public function handle(VentaAsientoBackfillFerliSupport $support): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Solo aplica a EMPRESA=Calzados Ferli.');

            return self::FAILURE;
        }

        if ($this->option('ejecutar') && $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $this->error('--desde debe ser Y-m-d');

            return self::FAILURE;
        }

        $dryRun = ! (bool) $this->option('ejecutar');
        $this->info($dryRun
            ? "Dry-run desde {$desde} (sin escribir)…"
            : "Generando asientos ERP desde {$desde}…");

        $resultado = $support->ejecutar($desde, $dryRun);

        foreach ($resultado['candidatos'] as $fila) {
            $err = $fila['error'] ?? null;
            $this->line(sprintf(
                '  %d %s %s total=%s líneas=%d%s',
                $fila['venta_id'],
                $fila['codigo'],
                $fila['created_at'],
                number_format((float) $fila['total'], 2, ',', '.'),
                (int) ($fila['lineas_asiento'] ?? 0),
                $err ? ' ERROR: '.$err : ''
            ));
        }

        $this->newLine();
        $this->info('Candidatos: '.count($resultado['candidatos']));
        if (! $dryRun) {
            $this->info('OK: '.$resultado['ok']);
            $this->info('Error: '.$resultado['error']);
        }
        foreach ($resultado['errores'] as $msg) {
            $this->error('• '.$msg);
        }

        if ($dryRun) {
            $this->comment('Para persistir: php artisan ventas:backfill-asientos-ferli --desde='.$desde.' --ejecutar');
        }

        return $resultado['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
