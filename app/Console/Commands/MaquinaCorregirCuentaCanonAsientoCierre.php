<?php

namespace App\Console\Commands;

use App\Support\Contable\CorregirCuentaCanonAsientoCierreMaquinaSupport;
use Illuminate\Console\Command;

class MaquinaCorregirCuentaCanonAsientoCierre extends Command
{
    protected $signature = 'maquina:corregir-cuenta-canon-asiento-cierre
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill: cánones cierre máquinas 521020 (sala) → 521010 (máquinas) — ERP + ctamov';

    public function handle(CorregirCuentaCanonAsientoCierreMaquinaSupport $support): int
    {
        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info('Alcance: asientos «Cierre rendición máquinas» con cánones en 521020 → 521010.');
        foreach (CorregirCuentaCanonAsientoCierreMaquinaSupport::MAPA_CODIGO as $origen => $destino) {
            $this->line('  '.$origen.' → '.$destino);
        }

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($empresaId);
        $this->newLine();
        $this->line('Rendiciones máquinas cerradas (revisión): '.$resumen['rendiciones_cerradas']);
        $this->line('Asientos con línea en 521020: '.$resumen['asientos']);

        if ($resumen['por_empresa'] !== []) {
            $this->line('Por empresa:');
            foreach ($resumen['por_empresa'] as $emp => $datos) {
                $this->line(sprintf(
                    '  empresa %d — rendiciones %d — asientos a corregir %d',
                    $emp,
                    $datos['rendiciones'],
                    $datos['asientos'],
                ));
            }
        }

        $afectados = $support->asientosAfectados($empresaId);
        if ($afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos de cierre máquinas con cánones en 521020.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Asientos ERP a revisar: '.$afectados->count());

        $resultado = $support->ejecutar($dryRun, $empresaId);

        $this->newLine();
        $this->info('Asientos ERP con líneas actualizadas: '.$resultado['asientos_erp']);
        $this->info('Líneas ERP (asiento_movimiento) actualizadas: '.$resultado['lineas_erp']);
        $this->info('Líneas Anita Informix (ctamov) actualizadas: '.$resultado['lineas_anita']);
        $this->info('Asientos ya conformes: '.$resultado['ya_ok']);

        if ($resultado['errores'] !== []) {
            $this->newLine();
            $this->error('Errores:');
            foreach ($resultado['errores'] as $err) {
                $this->line('  · '.$err);
            }

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Backfill completado en anitaERP y ctamov.');
        }

        return self::SUCCESS;
    }
}
