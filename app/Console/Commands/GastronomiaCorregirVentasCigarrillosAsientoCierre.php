<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CorregirVentasCigarrillosAsientoCierreJornadaSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirVentasCigarrillosAsientoCierre extends Command
{
    protected $signature = 'gastronomia:corregir-ventas-cigarrillos-asiento-cierre
                            {--empresa= : Limitar a empresa_id (1=Biyemas, 2=Kandiko, 3=Rebisco)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill ventas cigarrillos: importes en facturas mixtas + cuenta 414020001 (ERP + ctamov)';

    public function handle(CorregirVentasCigarrillosAsientoCierreJornadaSupport $support): int
    {
        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $desde = (array) config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $this->info('Alcance: jornadas con cierre grabado desde '.json_encode($desde, JSON_UNESCAPED_UNICODE));

        $jornadas = $support->jornadasAfectadas($empresaId);
        $this->info('Jornadas a revisar: '.$jornadas->count());

        $resultado = $support->ejecutar($dryRun, $empresaId);

        $this->newLine();
        $this->info('Jornadas procesadas: '.$resultado['jornadas']);
        $this->info('Asientos ERP actualizados: '.$resultado['asientos']);
        $this->info('Líneas ERP actualizadas: '.$resultado['lineas_erp']);
        $this->info('Asientos ctamov resincronizados: '.$resultado['ctamov']);
        $this->info('Asientos ya conformes: '.$resultado['ya_ok']);

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
