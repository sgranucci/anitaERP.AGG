<?php

namespace App\Console\Commands;

use App\Services\Contable\ReporteDefinibleAnitaTraductorService;
use Illuminate\Console\Command;

class ContableImportarReportesDefinibles extends Command
{
    protected $signature = 'contable:importar-reportes-definibles
                            {--desde= : Nro. informe Anita desde}
                            {--hasta= : Nro. informe Anita hasta}
                            {--informe= : Un solo nro. de informe}
                            {--no-reemplazar : No borrar estructura previa al reimportar}';

    protected $description = 'Importa definiciones de informes contables desde Anita (infomae*) hacia anitaERP';

    public function handle(ReporteDefinibleAnitaTraductorService $traductor): int
    {
        $informe = $this->option('informe');
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');

        $d = null;
        $h = null;
        if ($informe !== null && $informe !== '') {
            $d = $h = (int) $informe;
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

        $this->info('Leyendo Anita (infomae / infomov / infocta / infoccos)…');
        $result = $traductor->importar($d, $h, ! $this->option('no-reemplazar'));

        foreach ($result['errores'] as $err) {
            $this->error($err);
        }
        foreach ($result['advertencias'] as $adv) {
            $this->warn($adv);
        }

        $this->info(sprintf(
            'Listo: %d nuevos, %d actualizados, %d rubros, %d cuentas, %d c.costo',
            $result['importados'],
            $result['actualizados'],
            $result['rubros'],
            $result['cuentas'],
            $result['ccostos']
        ));

        return $result['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
