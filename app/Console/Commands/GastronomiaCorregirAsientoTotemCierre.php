<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CorregirAsientoTotemVentasCierreJornadaSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirAsientoTotemCierre extends Command
{
    protected $signature = 'gastronomia:corregir-asiento-totem-cierre
                            {--empresa= : Limitar a empresa_id (1=Biyemas, 2=Kandiko, 3=Rebisco)}
                            {--dry-run : Solo muestra qué se actualizaría}
                            {--listar : Lista asientos TOTEM desbalanceados sin corregir}';

    protected $description = 'Corrige asiento TOTEM (ventas/IVA en haber) del cierre Waitry cuando quedó en cero';

    public function handle(CorregirAsientoTotemVentasCierreJornadaSupport $support): int
    {
        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');
        $listar = (bool) $this->option('listar');

        $afectados = $support->asientosTotemDesbalanceados($empresaId);
        $this->info('Asientos TOTEM desbalanceados: '.$afectados->count());

        foreach ($afectados as $item) {
            $jornada = $item['jornada'];
            $this->line(sprintf(
                '  · jornada %s empresa %d asiento #%d debe $ %s haber $ %s',
                (string) $jornada->fecha_jornada,
                (int) $jornada->empresa_id,
                (int) $item['asiento_id'],
                number_format((float) $item['resumen_debe'], 2, ',', '.'),
                number_format((float) $item['resumen_haber'], 2, ',', '.'),
            ));
        }

        if ($listar) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        if ($afectados->isEmpty()) {
            return self::SUCCESS;
        }

        $resultado = $support->ejecutar($dryRun, $empresaId);

        $this->newLine();
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
