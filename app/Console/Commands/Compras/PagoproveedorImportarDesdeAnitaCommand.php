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
                            {--tipos=OPP,OPA : Tipos Anita separados por coma (OPP, OPA, OPV)}
                            {--ejecutar : Persiste documentos sin cuenta corriente; default dry-run}';

    protected $description = 'Importa OPP/OPA/OPV desde Anita che_ban.pago como documentos (sin cuenta corriente)';

    public function handle(PagoproveedorImportarDesdeAnitaService $service): int
    {
        $dryRun = ! (bool) $this->option('ejecutar');
        $usuario = $this->option('usuario-id');
        $usuarioId = ($usuario !== null && $usuario !== '') ? (int) $usuario : null;

        $tipos = array_values(array_filter(array_map(
            static fn ($t) => strtoupper(trim((string) $t)),
            explode(',', (string) $this->option('tipos'))
        )));

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' pagoproveedor desde Anita (sin cuenta corriente) tipos '.implode(',', $tipos));

        $stats = $service->importar(
            (string) $this->option('desde'),
            (string) $this->option('hasta'),
            $dryRun,
            $usuarioId,
            $tipos,
        );

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita', $stats['en_anita']],
            ['A crear', $stats['a_crear']],
            ['Creados', $stats['creados']],
            ['Omitidos (ya ERP)', $stats['omitidos']],
            ['Con asiento ya importado', $stats['con_asiento']],
            ['Sin asiento', $stats['sin_asiento']],
            ['Con detalle auxpag', $stats['con_auxpag']],
            ['Sin auxpag', $stats['sin_auxpag']],
            ['Proveedor 000000 a crear', $stats['proveedor_varios']],
            ['Sin proveedor ERP', $stats['sin_proveedor']],
            ['Sin empresa', $stats['sin_empresa']],
            ['Cuenta corriente escrita', $stats['cuenta_corriente']],
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
