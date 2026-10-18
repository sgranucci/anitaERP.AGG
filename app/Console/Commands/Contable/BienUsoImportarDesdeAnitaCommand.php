<?php

namespace App\Console\Commands\Contable;

use App\Services\Contable\BienUsoAnitaImportService;
use Illuminate\Console\Command;

class BienUsoImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'bien-uso:importar-anita
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (lee Anita, no escribe Anita)}
                            {--solo-cuenta : Solo sincroniza cuenta automática desde impcont 301}';

    protected $description = 'Importa bienes de uso desde Anita acncadmin (biemae/bierub/biesubr/indmae/indmov/amormov/revmov/bieubic) y carga cuenta impcont 301';

    public function handle(BienUsoAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' import bienes de uso desde Anita (acncadmin)');

        if ((bool) $this->option('solo-cuenta')) {
            $ret = $service->sincronizarCuentaAmortizacionDesdeImpcont(
                $dryRun,
                fn (string $m) => $this->line($m)
            );
            $this->table(['Métrica', 'Valor'], [
                ['Código cuenta (impcont 301)', $ret['codigo'] ?? '—'],
                ['Detalle', $ret['detalle'] ?? '—'],
                ['Empresas actualizadas', $ret['actualizadas']],
            ]);
            if ($dryRun) {
                $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar --solo-cuenta para persistir.');
            }

            return self::SUCCESS;
        }

        $stats = $service->importar($dryRun, fn (string $m) => $this->line($m));

        $cuenta = $stats['cuenta_automatica'] ?? [];
        $this->table(['Bloque', 'Leídos', 'Upsert/Insert', 'Omitidos'], [
            ['Rubros', $stats['rubros']['leidos'], $stats['rubros']['upsert'], '—'],
            ['Subrubros', $stats['subrubros']['leidos'], $stats['subrubros']['upsert'], '—'],
            ['Índices', $stats['indices']['leidos'], $stats['indices']['upsert'], '—'],
            ['Coeficientes', $stats['coeficientes']['leidos'], $stats['coeficientes']['upsert'], '—'],
            ['Bienes', $stats['bienes']['leidos'], $stats['bienes']['upsert'], $stats['bienes']['omitidos']],
            ['Amortizaciones', $stats['amortizaciones']['leidos'], $stats['amortizaciones']['upsert'], $stats['amortizaciones']['omitidos']],
            ['Revaluos', $stats['revaluos']['leidos'], $stats['revaluos']['upsert'], $stats['revaluos']['omitidos']],
            ['Ubicaciones', $stats['ubicaciones']['leidos'], $stats['ubicaciones']['upsert'], $stats['ubicaciones']['omitidos']],
        ]);

        $this->info(sprintf(
            'Cuenta automática impcont 301 → %s (%s) | empresas: %d | reemplazos: %d',
            $cuenta['codigo'] ?? '—',
            $cuenta['detalle'] ?? '—',
            (int) ($cuenta['actualizadas'] ?? 0),
            (int) ($stats['reemplazos']['actualizados'] ?? 0)
        ));

        foreach ($stats['errores'] ?? [] as $err) {
            $this->error($err);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: php artisan bien-uso:importar-anita --ejecutar');
        }

        return ($stats['errores'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }
}
