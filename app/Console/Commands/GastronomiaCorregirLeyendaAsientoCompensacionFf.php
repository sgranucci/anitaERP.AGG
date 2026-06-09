<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CorregirLeyendaAsientoCompensacionFfSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirLeyendaAsientoCompensacionFf extends Command
{
    protected $signature = 'gastronomia:corregir-leyenda-asiento-compensacion-ff
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Corrige leyenda «Reduccion FF Maquinas» en asientos compensación FF ya grabados (ERP + Anita ctamov)';

    public function handle(CorregirLeyendaAsientoCompensacionFfSupport $support): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $afectados = $support->asientosAfectados();
        if ($afectados->isEmpty()) {
            $this->info('No hay asientos con la leyenda antigua de compensación FF.');

            return self::SUCCESS;
        }

        $this->info('Asientos ERP a revisar: '.$afectados->count());
        foreach ($afectados as $asiento) {
            $nueva = $support->corregirObservacionCabecera((string) ($asiento->observacion ?? ''));
            $this->line(sprintf(
                '  #%d Anita %s → %s',
                $asiento->id,
                $asiento->numeroasiento,
                $nueva ?? '(cabecera ya OK)',
            ));
        }

        $resultado = $support->ejecutar($dryRun);

        $this->newLine();
        $this->info('Cabeceras ERP actualizadas: '.$resultado['asientos_erp']);
        $this->info('Líneas ERP actualizadas: '.$resultado['lineas_erp']);
        $this->info('Líneas Anita (ctamov) actualizadas: '.$resultado['lineas_anita']);
        $this->info('Snapshots corregidos: '.$resultado['snapshots']);

        if ($resultado['errores'] !== []) {
            $this->newLine();
            $this->error('Errores:');
            foreach ($resultado['errores'] as $err) {
                $this->line('  · '.$err);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
