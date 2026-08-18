<?php

namespace App\Console\Commands;

use App\Support\Contable\CorregirCentrocostoAsientoCierreBingoSupport;
use Illuminate\Console\Command;

class BingoCorregirCentrocostoAsientoCierre extends Command
{
    protected $signature = 'bingo:corregir-centrocosto-asiento-cierre
                            {--desde=2026-08-01 : Fecha inclusive desde la cual corregir (Y-m-d)}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill: centros de costo en asientos cierre bingo (ccosvalid) — ERP + ctamov';

    public function handle(CorregirCentrocostoAsientoCierreBingoSupport $support): int
    {
        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $this->error('Indique --desde=YYYY-MM-DD');

            return self::FAILURE;
        }

        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info(sprintf(
            'Alcance: cierre bingo desde %s — CC por cuenta (manejaccosto + cuentacontable_centrocosto).',
            $desde,
        ));

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($desde, $empresaId);
        $this->newLine();
        $this->line('Rendiciones cerradas: '.$resumen['rendiciones_cerradas']);
        $this->line('Asientos del cierre: '.$resumen['asientos']);

        if ($resumen['por_empresa'] !== []) {
            $this->line('Por empresa:');
            foreach ($resumen['por_empresa'] as $emp => $datos) {
                $this->line(sprintf(
                    '  · empresa %d: %d rendición(es), %d asiento(s)',
                    $emp,
                    $datos['rendiciones'],
                    $datos['asientos'],
                ));
            }
        }

        $afectados = $support->asientosAfectados($desde, $empresaId);
        if ($afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos del cierre bingo para revisar desde esa fecha.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Asientos ERP a revisar: '.$afectados->count());

        $resultado = $support->ejecutar($desde, $dryRun, $empresaId);

        if ($resultado['detalle'] !== []) {
            $this->newLine();
            $this->line('Detalle líneas ERP:');
            foreach ($resultado['detalle'] as $linea) {
                $this->line('  · '.$linea);
            }
        }

        $this->newLine();
        $this->info('Asientos ERP con líneas actualizadas: '.$resultado['asientos_erp']);
        $this->info('Líneas ERP (asiento_movimiento) a actualizar: '.$resultado['lineas_erp']);
        $this->info('Líneas Anita Informix (ctamov) a actualizar: '.$resultado['lineas_anita']);
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
