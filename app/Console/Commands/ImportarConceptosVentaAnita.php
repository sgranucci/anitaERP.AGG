<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\ConceptoVentaAnitaImportService;
use Illuminate\Console\Command;

class ImportarConceptosVentaAnita extends Command
{
    protected $signature = 'ventas:importar-conceptos-anita
        {--dry-run : Solo analiza, no persiste}
        {--ejecutar : Persiste master y cuentas}';

    protected $description = 'Importa conceptos de comprobante (concepto/concod/concta) desde Anita al catálogo de mostrador.';

    public function handle(ConceptoVentaAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run: no se persiste nada.' : 'Ejecutando importación.');

        try {
            $ret = $dryRun ? $service->analizar() : $service->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['En Anita', 'A crear', 'A actualizar', 'Cuentas', 'Omitidos', 'Errores'],
            [[
                $ret['en_anita'],
                $ret['crear'],
                $ret['actualizar'],
                $ret['cuentas'],
                $ret['omitidos'],
                count($ret['errores']),
            ]]
        );

        if ($ret['detalle'] !== []) {
            $this->table(
                ['Anita', 'Código', 'Nombre', 'GTIN', 'Acción', 'Cuentas'],
                array_map(fn (array $d) => [
                    $d['codigo_anita'],
                    $d['codigo'],
                    $d['nombre'],
                    $d['gtin'] ?? '',
                    $d['accion'],
                    $d['cuentas'],
                ], $ret['detalle'])
            );
        }

        foreach ($ret['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Para persistir: php artisan ventas:importar-conceptos-anita --ejecutar');
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
