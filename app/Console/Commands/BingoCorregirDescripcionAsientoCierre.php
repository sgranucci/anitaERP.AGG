<?php

namespace App\Console\Commands;

use App\Support\Contable\CorregirDescripcionAsientoCierreBingoSupport;
use Illuminate\Console\Command;

class BingoCorregirDescripcionAsientoCierre extends Command
{
    protected $signature = 'bingo:corregir-descripcion-asiento-cierre
                            {--desde=2026-08-01 : Fecha jornada inclusive desde (Y-m-d)}
                            {--hasta=2026-08-31 : Fecha jornada inclusive hasta (Y-m-d)}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill: leyendas p-vtabingo (Pago de premios / Dev. pozo / Canon) en asientos cierre bingo — ERP + ctamov';

    public function handle(CorregirDescripcionAsientoCierreBingoSupport $support): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde=YYYY-MM-DD y --hasta=YYYY-MM-DD');

            return self::FAILURE;
        }

        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info(sprintf(
            'Alcance: cierre bingo jornada %s → %s — leyendas cortas p-vtabingo.c.',
            $desde,
            $hasta,
        ));

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($desde, $hasta, $empresaId);
        $this->newLine();
        $this->line('Rendiciones cerradas: '.$resumen['rendiciones_cerradas']);
        $this->line('Asientos del cierre: '.$resumen['asientos']);
        $this->line('Cabeceras ERP a corregir: '.$resumen['a_corregir']);

        if ($resumen['por_leyenda'] !== []) {
            $this->line('Por leyenda objetivo:');
            foreach ($resumen['por_leyenda'] as $leyenda => $cant) {
                $this->line(sprintf('  · [%d] %s', $cant, $leyenda));
            }
        }

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

        $afectados = $support->asientosAfectados($desde, $hasta, $empresaId);
        if ($afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos del cierre bingo en ese rango.');

            return self::SUCCESS;
        }

        $resultado = $support->ejecutar($desde, $hasta, $dryRun, $empresaId);

        if ($resultado['detalle'] !== []) {
            $this->newLine();
            $this->line('Muestra cambios cabecera ERP (máx. 30):');
            foreach (array_slice($resultado['detalle'], 0, 30) as $linea) {
                $this->line('  · '.$linea);
            }
            if (count($resultado['detalle']) > 30) {
                $this->line('  · … y '.(count($resultado['detalle']) - 30).' más');
            }
        }

        $this->newLine();
        $this->info('Cabeceras ERP a actualizar: '.$resultado['asientos_erp']);
        $this->info('Líneas ERP (asiento_movimiento) a actualizar: '.$resultado['lineas_erp']);
        $this->info('Líneas Anita Informix (ctamov) a actualizar: '.$resultado['lineas_anita']);
        $this->info('Asientos ya conformes: '.$resultado['ya_ok']);
        if ($resultado['sin_leyenda'] > 0) {
            $this->warn('Asientos sin leyenda resoluble: '.$resultado['sin_leyenda']);
        }

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
