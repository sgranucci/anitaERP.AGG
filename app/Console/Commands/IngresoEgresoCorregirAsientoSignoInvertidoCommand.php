<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Caja\IngresoEgresoCorregirAsientoSignoInvertidoSupport;
use Illuminate\Console\Command;

class IngresoEgresoCorregirAsientoSignoInvertidoCommand extends Command
{
    protected $signature = 'ingresoegreso:corregir-asiento-signo-invertido
                            {--desde= : Fecha desde YYYY-MM-DD}
                            {--hasta= : Fecha hasta YYYY-MM-DD}
                            {--empresa= : empresa_id opcional}
                            {--solo-caja : Solo corrige signos de caja sin tocar asientos}
                            {--dry-run : Solo lista el impacto}
                            {--ejecutar : Persiste ERP y resincroniza ctamov Anita}';

    protected $description = 'Corrige IE EGR/ING con Debe/Haber invertido en asiento (y signos de caja) y sync ctamov';

    public function handle(IngresoEgresoCorregirAsientoSignoInvertidoSupport $support): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $empresaOpt = $this->option('empresa');
        $empresaId = $empresaOpt !== null && $empresaOpt !== '' ? (int) $empresaOpt : null;
        $soloCaja = (bool) $this->option('solo-caja');
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($desde === '' || $hasta === '') {
            $this->error('Debe indicar --desde y --hasta (YYYY-MM-DD).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se persisten cambios en anitaERP ni en ctamov.');
        }

        if ($soloCaja) {
            $planes = $support->listarCajaSignoIncorrecto($desde, $hasta, $empresaId);
            $this->info(sprintf('IE con signo de caja incorrecto: %d', count($planes)));
            foreach ($planes as $plan) {
                $this->line(sprintf(
                    '  %s nro %s %s | %s',
                    $plan['fecha'],
                    $plan['numerotransaccion'],
                    $plan['abreviatura'],
                    $plan['detalle']
                ));
                foreach ($plan['caja_cambios'] as $c) {
                    $this->line(sprintf(
                        '    caja #%d  %s → %s',
                        $c['id'],
                        number_format($c['monto_actual'], 2, ',', '.'),
                        number_format($c['monto_nuevo'], 2, ',', '.')
                    ));
                }
            }

            $res = $support->ejecutarSoloCaja($planes, $dryRun);
            $this->newLine();
            $this->info('Corregidos: '.$res['corregidos']);
            foreach ($res['errores'] as $err) {
                $this->error($err);
            }

            return $res['errores'] === [] ? self::SUCCESS : self::FAILURE;
        }

        $planes = $support->listarInvertidos($desde, $hasta, $empresaId);
        $this->info(sprintf('IE con asiento invertido: %d', count($planes)));
        foreach ($planes as $plan) {
            $this->newLine();
            $this->line(sprintf(
                '  %s nro %s %s asiento %s | %s',
                $plan['fecha'],
                $plan['numerotransaccion'],
                $plan['abreviatura'],
                $plan['numeroasiento'],
                $plan['detalle']
            ));
            foreach ($plan['lineas_asiento'] as $l) {
                $this->line(sprintf(
                    '    %s  %s → %s',
                    $l['cuenta'],
                    number_format($l['monto_actual'], 2, ',', '.'),
                    number_format($l['monto_nuevo'], 2, ',', '.')
                ));
            }
            foreach ($plan['caja_cambios'] as $c) {
                $this->line(sprintf(
                    '    caja #%d  %s → %s',
                    $c['id'],
                    number_format($c['monto_actual'], 2, ',', '.'),
                    number_format($c['monto_nuevo'], 2, ',', '.')
                ));
            }
        }

        $res = $support->ejecutar($planes, $dryRun);
        $this->newLine();
        $this->info('Asientos corregidos: '.$res['corregidos']);
        $this->info('ctamov resincronizados: '.$res['ctamov']);
        $this->info('Líneas caja ajustadas: '.$res['caja']);
        foreach ($res['errores'] as $err) {
            $this->error($err);
        }

        // También alinear signos de caja en IE cuyo asiento ya estaba bien
        $cajaPlanes = $support->listarCajaSignoIncorrecto($desde, $hasta, $empresaId);
        if ($cajaPlanes !== []) {
            $this->newLine();
            $this->info(sprintf('IE adicionales con solo signo de caja incorrecto: %d', count($cajaPlanes)));
            foreach ($cajaPlanes as $plan) {
                $this->line(sprintf(
                    '  %s nro %s %s | %s',
                    $plan['fecha'],
                    $plan['numerotransaccion'],
                    $plan['abreviatura'],
                    $plan['detalle']
                ));
            }
            $cajaRes = $support->ejecutarSoloCaja($cajaPlanes, $dryRun);
            $this->info('Signos de caja corregidos: '.$cajaRes['corregidos']);
            foreach ($cajaRes['errores'] as $err) {
                $this->error($err);
            }
            if ($cajaRes['errores'] !== []) {
                return self::FAILURE;
            }
        }

        return $res['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
