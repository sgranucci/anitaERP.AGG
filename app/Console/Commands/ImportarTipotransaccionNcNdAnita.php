<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\TipotransaccionNcNdAnitaImportService;
use Illuminate\Console\Command;

class ImportarTipotransaccionNcNdAnita extends Command
{
    protected $signature = 'ventas:importar-tipos-nc-nd-anita
        {--dry-run : Solo analiza, no persiste}
        {--ejecutar : Crea NC/ND faltantes y asigna concepto}';

    protected $description = 'Importa de Anita (t_comp) los tipos NC/ND que faltan para facturar y les asigna concepto_venta.';

    public function handle(TipotransaccionNcNdAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run: no se persiste nada.' : 'Importando NC/ND faltantes desde Anita.');

        try {
            $ret = $dryRun ? $service->analizar() : $service->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['En Anita', 'A crear', 'Completar concepto', 'Ya OK', 'Omitidos', 'Errores'],
            [[
                $ret['en_anita'],
                $ret['crear'],
                $ret['completar_concepto'],
                $ret['ya_ok'],
                $ret['omitidos'],
                count($ret['errores']),
            ]]
        );

        if ($ret['detalle'] !== []) {
            $this->table(
                ['Abrev.', 'Nombre', 'AFIP', 'Anita', 'Concepto', 'Acción'],
                array_map(fn (array $d) => [
                    $d['abreviatura'],
                    $d['nombre'],
                    $d['afip'],
                    $d['anita'],
                    $d['concepto'],
                    $d['accion'],
                ], $ret['detalle'])
            );
        }

        foreach ($ret['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Para persistir: php artisan ventas:importar-tipos-nc-nd-anita --ejecutar');
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
