<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\PagoproveedorImportarDesdeAnitaService;
use Illuminate\Console\Command;

class PagoproveedorImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'pagoproveedor:importar-desde-anita
                            {--desde=2025-01-01 : Fecha ISO desde}
                            {--hasta=2025-12-31 : Fecha ISO hasta}
                            {--usuario-id= : Usuario para estados}
                            {--ejecutar : Persiste cabeceras OPP/OPA sin CC; default dry-run}';

    protected $description = 'Importa OPP/OPA desde Anita che_ban.pago como documentos (sin cuenta corriente)';

    public function handle(PagoproveedorImportarDesdeAnitaService $service): int
    {
        $dryRun = ! (bool) $this->option('ejecutar');
        $usuario = $this->option('usuario-id');
        $usuarioId = ($usuario !== null && $usuario !== '') ? (int) $usuario : null;

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' pagoproveedor desde Anita (sin CC)');

        $stats = $service->importar(
            (string) $this->option('desde'),
            (string) $this->option('hasta'),
            $dryRun,
            $usuarioId,
        );

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita (OPP+OPA)', $stats['en_anita']],
            ['A crear', $stats['a_crear']],
            ['Creados', $stats['creados']],
            ['Omitidos (ya ERP)', $stats['omitidos']],
            ['Sin proveedor ERP', $stats['sin_proveedor']],
            ['Sin empresa', $stats['sin_empresa']],
            ['Errores', count($stats['errores'])],
            ['Errores bridge', count($stats['errores_bridge'])],
        ]);

        foreach (array_slice($stats['errores'], 0, 15) as $e) {
            $this->warn($e);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return $stats['errores'] === [] && $stats['errores_bridge'] === []
            ? self::SUCCESS
            : self::FAILURE;
    }
}
