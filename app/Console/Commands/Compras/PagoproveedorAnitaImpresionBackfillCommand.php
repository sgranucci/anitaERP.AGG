<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\PagoproveedorAnitaImpresionBackfillService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PagoproveedorAnitaImpresionBackfillCommand extends Command
{
    protected $signature = 'pagoproveedor:backfill-impresion-anita
                            {--desde= : Fecha ISO desde (default: hace 3 meses)}
                            {--hasta= : Fecha ISO hasta (default: hoy)}
                            {--ejecutar : Persiste snapshot + retenciones; default dry-run}';

    protected $description = 'Completa impresión de OP importadas Anita (auxpag + ret*mov). No toca OP ERP ni escribe Anita.';

    public function handle(PagoproveedorAnitaImpresionBackfillService $service): int
    {
        $hasta = (string) ($this->option('hasta') ?: Carbon::today()->toDateString());
        $desde = (string) ($this->option('desde') ?: Carbon::parse($hasta)->subMonths(3)->toDateString());
        $dryRun = ! (bool) $this->option('ejecutar');

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR')." impresión Anita {$desde} → {$hasta}");
        $this->comment('Solo stubs "Importado desde Anita (sin cuenta corriente)". OP con asiento/caja/cheques ERP se omiten.');

        $stats = $service->backfill($desde, $hasta, $dryRun);

        $this->table(['Métrica', 'Cantidad'], [
            ['OP en rango', $stats['candidatas']],
            ['Elegibles (stubs Anita)', $stats['elegibles']],
            ['Omitidas (ERP / no stub)', $stats['omitidas_erp']],
            ['Actualizadas', $stats['actualizadas']],
            ['Sin auxpag ni ret*', $stats['sin_auxpag']],
            ['Retenciones creadas', $stats['retenciones_creadas']],
            ['Retenciones omitidas (había ERP)', $stats['retenciones_omitidas']],
            ['Errores', count($stats['errores'])],
            ['Errores bridge', count($stats['errores_bridge'])],
        ]);

        foreach (array_slice($stats['errores'], 0, 15) as $e) {
            $this->warn($e);
        }
        foreach (array_slice($stats['errores_bridge'], 0, 10) as $e) {
            $this->warn('bridge: '.$e);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return $stats['errores'] === []
            ? self::SUCCESS
            : self::FAILURE;
    }
}
