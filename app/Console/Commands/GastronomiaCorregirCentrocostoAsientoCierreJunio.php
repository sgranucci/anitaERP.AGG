<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosCentrocostoSupport;
use App\Support\Ventas\Gastronomia\CorregirCentrocostoAsientoCierreJornadaJunioSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirCentrocostoAsientoCierreJunio extends Command
{
    protected $signature = 'gastronomia:corregir-centrocosto-asiento-cierre-junio
                            {--anio=2026 : Año del mes junio}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill junio: centro de costo 85 en asientos cierre Waitry (cuentas con maneja CC) — ERP + ctamov';

    public function handle(CorregirCentrocostoAsientoCierreJornadaJunioSupport $support): int
    {
        $anio = max(2000, (int) $this->option('anio'));
        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        $this->info(sprintf(
            'Alcance: jornadas junio/%d — centro de costo «%s» en cuentas con maneja CC.',
            $anio,
            CierreJornadaProcesoAsientosCentrocostoSupport::codigoCentrocostoConfigurado(),
        ));

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        $resumen = $support->resumenAlcance($anio, $empresaId);
        $this->newLine();
        $this->line('Jornadas del mes: '.$resumen['jornadas_mes']);
        $this->line('Jornadas con asientos grabados (snapshot): '.$resumen['jornadas_con_snapshot']);
        $this->line('Asientos del cierre Waitry en el mes: '.$resumen['asientos_snapshot']);
        $this->line('Promedio asientos por jornada: '.$resumen['promedio_asientos_por_jornada']);

        if ($resumen['distribucion_por_cantidad_asientos'] !== []) {
            $this->line('Distribución por jornada:');
            foreach ($resumen['distribucion_por_cantidad_asientos'] as $cant => $jornadas) {
                $this->line(sprintf('  · %d asiento(s): %d jornada(s)', $cant, $jornadas));
            }
        }

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

        $afectados = $support->asientosAfectados($anio, $empresaId);
        if ($afectados->isEmpty()) {
            $this->newLine();
            $this->info('No hay asientos del cierre Waitry de junio para revisar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Asientos ERP a revisar: '.$afectados->count());

        $resultado = $support->ejecutar($anio, $dryRun, $empresaId);

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
