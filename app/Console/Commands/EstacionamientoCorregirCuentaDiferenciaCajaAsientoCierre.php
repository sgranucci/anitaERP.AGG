<?php

namespace App\Console\Commands;

use App\Support\Contable\CorregirCuentaDiferenciaCajaAsientoCierreEstacionamientoSupport;
use Illuminate\Console\Command;

class EstacionamientoCorregirCuentaDiferenciaCajaAsientoCierre extends Command
{
    protected $signature = 'estacionamiento:corregir-cuenta-diferencia-caja-asiento-cierre
                            {--fecha=2026-07-01 : Fecha del cierre (Y-m-d)}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill: cuenta diferencia de caja (521280004) en asientos cierre estacionamiento — ERP + ctamov';

    public function handle(CorregirCuentaDiferenciaCajaAsientoCierreEstacionamientoSupport $support): int
    {
        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $this->error('Indique --fecha=YYYY-MM-DD');

            return self::FAILURE;
        }

        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info(sprintf(
            'Alcance: cierre estacionamiento %s — cuenta diferencia de caja %s → %s.',
            $fecha,
            CorregirCuentaDiferenciaCajaAsientoCierreEstacionamientoSupport::CODIGO_CUENTA_ANTIGUA,
            CorregirCuentaDiferenciaCajaAsientoCierreEstacionamientoSupport::CODIGO_CUENTA_NUEVA,
        ));

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($fecha, $empresaId);
        $this->newLine();
        $this->line('Rendiciones cerradas: '.$resumen['rendiciones_cerradas']);
        $this->line('Asientos del cierre: '.$resumen['asientos']);

        $afectados = $support->asientosAfectados($fecha, $empresaId);
        if ($afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos del cierre estacionamiento para revisar en esa fecha.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Asientos ERP a revisar: '.$afectados->count());

        $resultado = $support->ejecutar($fecha, $dryRun, $empresaId);

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
