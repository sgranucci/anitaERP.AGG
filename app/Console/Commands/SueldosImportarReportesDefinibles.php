<?php

namespace App\Console\Commands;

use App\Services\Sueldos\ReporteSueldosDefinibleAnitaTraductorService;
use Illuminate\Console\Command;

class SueldosImportarReportesDefinibles extends Command
{
    protected $signature = 'sueldos:importar-reportes-definibles
                            {--desde= : Nro. listado Anita desde}
                            {--hasta= : Nro. listado Anita hasta}
                            {--listado= : Un solo nro. de listado}
                            {--ejecutar : Persistir (sin este flag es dry-run)}
                            {--no-reemplazar : No borrar columnas previas al reimportar}';

    protected $description = 'Importa listados definibles de sueldos desde Anita (listmae/listcol/listcon)';

    public function handle(ReporteSueldosDefinibleAnitaTraductorService $traductor): int
    {
        $listado = $this->option('listado');
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');

        $d = null;
        $h = null;
        if ($listado !== null && $listado !== '') {
            $d = $h = (int) $listado;
        } else {
            if ($desde !== null && $desde !== '') {
                $d = (int) $desde;
            }
            if ($hasta !== null && $hasta !== '') {
                $h = (int) $hasta;
            }
            if ($d !== null && $h === null) {
                $h = $d;
            }
        }

        $dryRun = ! $this->option('ejecutar');
        if ($dryRun) {
            $this->warn('Modo dry-run (no persiste). Use --ejecutar para grabar.');
        }

        $this->info('Leyendo Anita (listmae / listcol / listcon)…');
        $result = $traductor->importar($d, $h, ! $this->option('no-reemplazar'), $dryRun);

        foreach ($result['errores'] as $err) {
            $this->error($err);
        }
        foreach ($result['advertencias'] as $adv) {
            $this->warn($adv);
        }

        $this->table(
            ['Código', 'Título', 'Tipo', 'Columnas', 'Conceptos'],
            array_map(fn ($p) => [
                $p['codigo'],
                mb_substr($p['titulo'], 0, 40),
                $p['tipo'],
                $p['columnas'],
                $p['conceptos'],
            ], array_slice($result['preview'], 0, 50))
        );
        if (count($result['preview']) > 50) {
            $this->line('… y '.(count($result['preview']) - 50).' listados más.');
        }

        $this->info(sprintf(
            '%s: %d nuevos, %d actualizados, %d columnas, %d conceptos',
            $dryRun ? 'Dry-run' : 'Importado',
            $result['importados'],
            $result['actualizados'],
            $result['columnas'],
            $result['conceptos']
        ));

        return $result['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
