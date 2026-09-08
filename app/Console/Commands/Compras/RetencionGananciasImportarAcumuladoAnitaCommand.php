<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\RetencionGananciasImportarAcumuladoAnitaService;
use Illuminate\Console\Command;

class RetencionGananciasImportarAcumuladoAnitaCommand extends Command
{
    protected $signature = 'retencionganancia:importar-acumulado-anita
                            {--desde=2026-09-01 : Fecha ISO desde}
                            {--hasta=2026-09-30 : Fecha ISO hasta}
                            {--empresas=1,2,3 : Empresas Anita}
                            {--usuario-id= : Usuario para estados de OP creadas}
                            {--ejecutar : Persiste; default dry-run}';

    protected $description = 'Importa retmov Anita (Ganancias) a pagoproveedor_retencion para acumulado mensual RG 830';

    public function handle(RetencionGananciasImportarAcumuladoAnitaService $service): int
    {
        $dryRun = ! (bool) $this->option('ejecutar');
        $usuario = $this->option('usuario-id');
        $usuarioId = ($usuario !== null && $usuario !== '') ? (int) $usuario : null;
        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas'))
        )));

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' acumulado Ganancias desde Anita retmov');

        $stats = $service->importar(
            (string) $this->option('desde'),
            (string) $this->option('hasta'),
            $dryRun,
            $usuarioId,
            $empresas !== [] ? $empresas : [1, 2, 3],
        );

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita (retmov OPP)', $stats['en_anita']],
            ['Retenciones a crear/creadas', $stats['creados']],
            ['Retenciones actualizadas', $stats['actualizados']],
            ['OP cabecera creadas', $stats['pagos_creados']],
            ['Omitidos', $stats['omitidos']],
            ['Sin proveedor ERP', $stats['sin_proveedor']],
            ['Errores', count($stats['errores'])],
            ['Errores bridge', count($stats['errores_bridge'])],
        ]);

        foreach (array_slice($stats['errores'], 0, 20) as $e) {
            $this->warn($e);
        }
        foreach (array_slice($stats['errores_bridge'], 0, 10) as $e) {
            $this->error($e);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return ($stats['errores'] === [] && $stats['errores_bridge'] === [])
            ? self::SUCCESS
            : self::FAILURE;
    }
}
