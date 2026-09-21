<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\RetencionCobranzaAnitaImportService;
use Illuminate\Console\Command;

class ImportarRetencionCobranzaAnitaCommand extends Command
{
    protected $signature = 'configuracion:importar-retencion-cobranza-anita
        {--dry-run : Solo analiza, no persiste (default si no hay --ejecutar)}
        {--ejecutar : Persiste retenciones de cobranza desde Anita tctes}';

    protected $description = 'Importa retenciones de cobranza (clientes) desde Anita che_ban.tctes. Dry-run por defecto.';

    public function handle(RetencionCobranzaAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run: no se persiste nada.' : 'Ejecutando importación (Anita tctes → retencion_cobranza).');

        try {
            $ret = $dryRun ? $service->analizar() : $service->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['En Anita', 'Candidatos', 'Crear', 'Actualizar', 'Omitidos'],
            [[
                (string) $ret['en_anita'],
                (string) $ret['candidatos'],
                (string) $ret['crear'].($dryRun ? ' (sim)' : ''),
                (string) $ret['actualizar'].($dryRun ? ' (sim)' : ''),
                (string) $ret['omitidos'],
            ]]
        );

        if ($ret['filas'] !== []) {
            $this->newLine();
            $this->table(
                ['Acción', 'Clave', 'Nombre', 'Tipo', 'Provincia', 'Cuenta contable'],
                array_map(static function (array $f): array {
                    return [
                        (string) ($f['accion'] ?? ''),
                        (string) ($f['clave'] ?? ''),
                        (string) ($f['nombre'] ?? ''),
                        (string) ($f['tiporetencion'] ?? ''),
                        (string) ($f['provincia'] ?? ($f['motivo'] ?? '')),
                        (string) ($f['cuentacontable'] ?? ''),
                    ];
                }, $ret['filas'])
            );
        }

        foreach ($ret['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Para persistir: php artisan configuracion:importar-retencion-cobranza-anita --ejecutar');
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
