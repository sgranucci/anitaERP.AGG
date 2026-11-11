<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ventas\FacturacionJuegoCorregirAsientosOctubreSupport;
use Illuminate\Console\Command;

/**
 * Corrige CME/CSL/CRE de facturación juego con Debe/Haber invertido y
 * completa la referencia al comprobante en el mayor (ERP).
 */
class FacturacionJuegoCorregirAsientosCreditoCommand extends Command
{
    protected $signature = 'facturacion-juego:corregir-asientos-credito
                            {--desde=2026-10-01 : Fecha flash desde YYYY-MM-DD}
                            {--hasta=2026-10-31 : Fecha flash hasta YYYY-MM-DD}
                            {--sala= : sala_id opcional}
                            {--dry-run : Solo lista el impacto (default si no hay --ejecutar)}
                            {--ejecutar : Persiste inversión de signos y referencias}';

    protected $description = 'Invierte asientos CME/CSL/CRE mal firmados y pone el código del comprobante en el mayor';

    public function handle(FacturacionJuegoCorregirAsientosOctubreSupport $support): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $salaOpt = $this->option('sala');
        $salaId = ($salaOpt !== null && $salaOpt !== '') ? (int) $salaOpt : null;
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde y --hasta (YYYY-MM-DD).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se persisten cambios.');
        }

        $planes = $support->planificar($desde, $hasta, $salaId);
        $resumen = $support->resumen($planes);

        $this->info(sprintf(
            'Alcance flash %s → %s%s',
            $desde,
            $hasta,
            $salaId ? ' sala_id='.$salaId : ' (todas las salas)',
        ));
        $this->line('Planes: '.$resumen['total']);
        $this->line('A invertir signo (créditos): '.$resumen['a_invertir']
            .' — importe ventas |'.number_format($resumen['importe_invertir'], 2, ',', '.').'|');
        $this->line('Solo referencia comprobante: '.$resumen['a_referenciar']);

        if ($resumen['por_sala'] !== []) {
            $this->newLine();
            $this->line('Por sala:');
            foreach ($resumen['por_sala'] as $sala => $datos) {
                $this->line(sprintf(
                    '  · %s — invertir %d / referenciar %d',
                    $sala,
                    $datos['invertir'],
                    $datos['referenciar'],
                ));
            }
        }

        $muestra = array_values(array_filter($planes, fn ($p) => ! empty($p['invertir_signo'])));
        if ($muestra !== []) {
            $this->newLine();
            $this->line('Créditos a invertir (muestra):');
            foreach (array_slice($muestra, 0, 40) as $p) {
                $this->line(sprintf(
                    '  %s %s %s %s asiento#%d ventas %s → %s | %s',
                    $p['fecha'],
                    $p['sala_codigo'],
                    $p['rubro'],
                    $p['codigo'],
                    $p['asiento_id'],
                    number_format((float) $p['monto_ventas_actual'], 2, ',', '.'),
                    number_format((float) $p['monto_ventas_nuevo'], 2, ',', '.'),
                    $p['concepto_nuevo'],
                ));
            }
            if (count($muestra) > 40) {
                $this->line('  … y '.(count($muestra) - 40).' más');
            }
        }

        if ($planes === []) {
            $this->info('Nada para corregir.');

            return self::SUCCESS;
        }

        $res = $support->ejecutar($planes, $dryRun);
        $this->newLine();
        $this->info('Invertidos: '.$res['invertidos']);
        $this->info('Referencias: '.$res['referencias']);
        foreach ($res['errores'] as $err) {
            $this->error($err);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Para persistir: mismo comando con --ejecutar (sin --dry-run).');
        }

        return $res['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
