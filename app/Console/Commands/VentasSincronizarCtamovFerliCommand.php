<?php

namespace App\Console\Commands;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\VentaAsientoBackfillFerliSupport;
use Illuminate\Console\Command;

/**
 * Ferli: sincroniza ctamov Anita para FAC/NC ERP (OT/picking) con asiento ERP sin ctamov.
 * Dry-run por defecto.
 */
class VentasSincronizarCtamovFerliCommand extends Command
{
    protected $signature = 'ventas:sincronizar-ctamov-ferli
                            {--desde=2026-09-10 : Fecha created_at desde (Y-m-d)}
                            {--dry-run : Solo informa (default si no hay --ejecutar)}
                            {--ejecutar : Escribe ctamov en Anita desde asientos ERP}';

    protected $description = 'CALZADOS FERLI: sincroniza ctamov Anita de facturas ERP con asiento sin ctamov';

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
            ? "Dry-run ctamov desde {$desde} (sin escribir)…"
            : "Sincronizando ctamov Anita desde {$desde}…");

        $resultado = $support->sincronizarCtamovPendientes($desde, $dryRun);

        foreach ($resultado['candidatos'] as $fila) {
            $this->line(sprintf(
                '  %d %s asiento=%s estado=%s%s',
                $fila['venta_id'],
                $fila['codigo'],
                $fila['numeroasiento'] ?? '-',
                $fila['estado'] ?? '-',
                isset($fila['error']) ? ' ERROR: '.$fila['error'] : ''
            ));
        }

        $this->newLine();
        $this->info('Candidatos: '.count($resultado['candidatos']));
        $this->info('Omitidos (ya tienen ctamov): '.$resultado['omitidos']);
        if (! $dryRun) {
            $this->info('OK: '.$resultado['ok']);
            $this->info('Error: '.$resultado['error']);
        }
        foreach ($resultado['errores'] as $msg) {
            $this->error('• '.$msg);
        }

        if ($dryRun) {
            $pendientes = count(array_filter(
                $resultado['candidatos'],
                static fn ($f) => ($f['estado'] ?? '') === 'pendiente_sync'
            ));
            $this->comment("Pendientes de sync: {$pendientes}");
            $this->comment('Para persistir: php artisan ventas:sincronizar-ctamov-ferli --desde='.$desde.' --ejecutar');
        }

        return $resultado['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
