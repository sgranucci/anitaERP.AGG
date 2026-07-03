<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabacionSupport;
use App\Support\Ventas\Gastronomia\CorregirDescripcionAsientoCierreJornadaJulioSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirDescripcionAsientoCierreJulio extends Command
{
    protected $signature = 'gastronomia:corregir-descripcion-asiento-cierre-julio
                            {--anio=2026 : Año del mes julio}
                            {--empresa= : Limitar a empresa_id (opcional)}
                            {--desde-config : Todas las jornadas con cierre desde fecha inicio por empresa (junio+)}
                            {--dry-run : Solo muestra qué se actualizaría}';

    protected $description = 'Backfill julio: leyenda «Venta gastronomia» en asientos cierre Waitry post jornada (ERP + ctamov)';

    public function handle(CorregirDescripcionAsientoCierreJornadaJulioSupport $support): int
    {
        $anio = max(2000, (int) $this->option('anio'));
        $empresaRaw = $this->option('empresa');
        $empresaId = ($empresaRaw !== null && $empresaRaw !== '') ? (int) $empresaRaw : null;
        $desdeConfig = (bool) $this->option('desde-config');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }

        if ($desdeConfig) {
            $desde = (array) config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
            $this->info(sprintf(
                'Alcance: jornadas con cierre grabado desde %s — descripción «%s».',
                json_encode($desde, JSON_UNESCAPED_UNICODE),
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
            ));
            $afectados = $support->asientosAfectadosDesdeConfig($empresaId);
            $resultado = $support->ejecutarDesdeConfig($dryRun, $empresaId);
        } else {
            $this->info(sprintf(
                'Alcance: jornadas julio/%d — descripción objetivo «%s».',
                $anio,
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
            ));
            $afectados = $support->asientosAfectados($anio, $empresaId);
            $resultado = $support->ejecutar($anio, $dryRun, $empresaId);
        }

        if ($empresaId !== null && $empresaId > 0) {
            $this->line('Filtro empresa_id: '.$empresaId);
        }

        if ($afectados->isEmpty()) {
            $this->info('No hay asientos del cierre Waitry para revisar.');

            return self::SUCCESS;
        }

        $this->info('Asientos ERP a revisar: '.$afectados->count());
        foreach ($afectados as $asiento) {
            $cabecera = trim((string) ($asiento->observacion ?? ''));
            $estado = $support->requiereActualizacionCabecera($cabecera) ? 'actualizar' : 'cabecera OK';
            $this->line(sprintf(
                '  #%d Anita %s | %s | %s',
                $asiento->id,
                $asiento->numeroasiento,
                $estado,
                mb_strimwidth($cabecera, 0, 72, '…'),
            ));
        }

        $this->newLine();
        $this->info('Cabeceras ERP actualizadas: '.$resultado['asientos_erp']);
        $this->info('Líneas ERP actualizadas: '.$resultado['lineas_erp']);
        $this->info('Líneas Anita (ctamov) actualizadas: '.$resultado['lineas_anita']);
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
