<?php

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CorregirImporteAsientoTotemVsVentaSupport;
use Illuminate\Console\Command;

class GastronomiaCorregirImporteAsientoTotemVsVenta extends Command
{
    protected $signature = 'gastronomia:corregir-importe-asiento-totem-vs-venta
                            {--empresa= : empresa_id (1=Biyemas, 3=Rebisco)}
                            {--fecha= : Fecha jornada YYYY-MM-DD}
                            {--dry-run : Solo muestra el impacto}
                            {--ejecutar : Persiste ERP y resincroniza ctamov Anita}';

    protected $description = 'Recuadra asientos 3/4 TOTEM al venta.total ERP y sincroniza ctamov';

    public function handle(CorregirImporteAsientoTotemVsVentaSupport $support): int
    {
        $empresaId = (int) $this->option('empresa');
        $fecha = trim((string) $this->option('fecha'));
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($empresaId <= 0 || $fecha === '') {
            $this->error('Debe indicar --empresa y --fecha.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se persisten cambios en anitaERP ni en ctamov.');
        }

        try {
            $resultado = $support->ejecutar($empresaId, $fecha, $dryRun);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $plan = $resultado['plan'];
        $this->info(sprintf(
            'Empresa %d jornada %s | facturas TOTEM %d | venta ERP $ %s',
            (int) $plan['empresa_id'],
            (string) $plan['fecha_jornada'],
            (int) $plan['cantidad_facturas'],
            number_format((float) $plan['total_venta_erp'], 2, ',', '.'),
        ));

        foreach ($plan['asientos'] as $asiento) {
            $this->newLine();
            $this->line(sprintf(
                '  %s asiento #%d nro %s Anita %s | actual $ %s → $ %s',
                (string) $asiento['codigo'],
                (int) $asiento['asiento_id'],
                (string) $asiento['numeroasiento'],
                (string) ($asiento['anita_nro_asiento'] ?? '-'),
                number_format((float) $asiento['debe_actual'], 2, ',', '.'),
                number_format((float) $asiento['total_esperado'], 2, ',', '.'),
            ));
            foreach ($asiento['cambios'] as $cambio) {
                $this->line(sprintf(
                    '    · mov #%d %s  %s → %s',
                    (int) $cambio['movimiento_id'],
                    trim((string) $cambio['cuenta']),
                    number_format((float) $cambio['monto_actual'], 2, ',', '.'),
                    number_format((float) $cambio['monto_esperado'], 2, ',', '.'),
                ));
            }
            if ($asiento['cambios'] === []) {
                $this->line('    · ya conforme');
            }
        }

        $this->newLine();
        $this->info('Asientos a corregir: '.$resultado['asientos']);
        $this->info('Líneas ERP: '.$resultado['lineas_erp']);
        $this->info('ctamov resincronizados: '.$resultado['ctamov']);
        $this->info('Ya conformes: '.$resultado['ya_ok']);

        if ($resultado['errores'] !== []) {
            $this->newLine();
            $this->error('Errores:');
            foreach ($resultado['errores'] as $err) {
                $this->line('  · '.$err);
            }

            return self::FAILURE;
        }

        if ($dryRun && $plan['requiere_cambio']) {
            $this->comment('Para persistir: php artisan gastronomia:corregir-importe-asiento-totem-vs-venta --empresa='.$empresaId.' --fecha='.$fecha.' --ejecutar');
        }

        return self::SUCCESS;
    }
}
