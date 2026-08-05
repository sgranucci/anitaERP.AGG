<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosCentrocostoSupport;
use App\Support\Ventas\Gastronomia\CorregirCentrocostoAsientoCierreJornadaSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirCentrocostoAsientoCierre extends Command
{
    protected $signature = 'gastronomia:corregir-centrocosto-asiento-cierre
                            {--desde=2026-07-01 : Fecha jornada inclusive desde la cual corregir (Y-m-d)}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--solo-pendientes : Solo asientos con líneas ERP sin CC (más rápido)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill: centro de costo 85 en asientos cierre Waitry (cuentas con maneja CC) — ERP + ctamov';

    public function handle(CorregirCentrocostoAsientoCierreJornadaSupport $support): int
    {
        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $this->error('Indique --desde=YYYY-MM-DD');

            return self::FAILURE;
        }

        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');
        $soloPendientes = (bool) $this->option('solo-pendientes');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info(sprintf(
            'Alcance: cierre Waitry desde %s — centro de costo «%s» en cuentas con maneja CC.',
            $desde,
            CierreJornadaProcesoAsientosCentrocostoSupport::codigoCentrocostoConfigurado(),
        ));

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($desde, $empresaId);
        $this->newLine();
        $this->line('Jornadas desde la fecha: '.$resumen['jornadas']);
        $this->line('Jornadas con snapshot de asientos: '.$resumen['jornadas_con_snapshot']);
        $this->line('Asientos del cierre Waitry: '.$resumen['asientos_snapshot']);

        if ($resumen['por_empresa'] !== []) {
            $this->line('Por empresa:');
            foreach ($resumen['por_empresa'] as $emp => $datos) {
                $this->line(sprintf(
                    '  · empresa %d: %d jornada(s), %d asiento(s)',
                    $emp,
                    $datos['jornadas'],
                    $datos['asientos'],
                ));
            }
        }

        if ($resumen['por_dia_faltantes'] !== []) {
            $this->newLine();
            $this->warn('Días con líneas sin CC '.CierreJornadaProcesoAsientosCentrocostoSupport::codigoCentrocostoConfigurado().':');
            foreach ($resumen['por_dia_faltantes'] as $fecha => $datos) {
                $this->line(sprintf(
                    '  · %s: %d línea(s) en %d asiento(s)',
                    $fecha,
                    $datos['lineas_sin_cc'],
                    $datos['asientos'],
                ));
            }
        } else {
            $this->newLine();
            $this->info('No hay líneas ERP pendientes de CC en el alcance.');
        }

        $afectados = $soloPendientes
            ? null
            : $support->asientosAfectados($desde, $empresaId);

        if (! $soloPendientes && $afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos del cierre Waitry para revisar desde esa fecha.');

            return self::SUCCESS;
        }

        if (! $soloPendientes) {
            $this->newLine();
            $this->info('Asientos ERP a revisar: '.$afectados->count());
        } else {
            $this->newLine();
            $this->info('Modo solo-pendientes: se omiten asientos ERP ya conformes.');
        }

        $resultado = $soloPendientes
            ? $support->ejecutarSoloPendientes($desde, $dryRun, $empresaId)
            : $support->ejecutar($desde, $dryRun, $empresaId);

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
