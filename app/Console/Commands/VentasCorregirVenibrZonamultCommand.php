<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Support\Ventas\ClienteAnitaZonamultSupport;
use App\Support\Ventas\VenibrZonamultCorreccionSupport;
use Illuminate\Console\Command;

/**
 * Corrige venibr.veni_provincia AFIP (901/915…) → zonamult Anita (1/4…).
 */
class VentasCorregirVenibrZonamultCommand extends Command
{
    protected $signature = 'ventas:corregir-venibr-zonamult
                            {--desde= : Fecha venta Anita Y-m-d}
                            {--hasta= : Fecha venta Anita Y-m-d}
                            {--dry-run : Solo lista (default si no hay --ejecutar)}
                            {--ejecutar : Persiste UPDATE en Anita venibr}';

    protected $description = 'Corrige venibr.veni_provincia de jurisdicción AFIP a zonamult (ibrxprov)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('ejecutar');
        if ((bool) $this->option('dry-run') && (bool) $this->option('ejecutar')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        if (! (bool) $this->option('dry-run') && ! (bool) $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar corre en simulación.');
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde=Y-m-d y --hasta=Y-m-d');

            return self::FAILURE;
        }

        ClienteAnitaZonamultSupport::resetCache();

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Fecha %s → %s | %s',
            $desde,
            $hasta,
            $dryRun ? 'SIMULACIÓN' : 'EJECUTAR'
        ));

        try {
            $filas = VenibrZonamultCorreccionSupport::listarIncorrectas($desde, $hasta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($filas === []) {
            $this->info('No hay venibr con provincia AFIP (>=900) en el rango.');

            return self::SUCCESS;
        }

        $this->table(
            ['Comprobante', 'Cliente', 'Prov actual', 'Prov correcta', '%', 'Importe', 'Fecha'],
            array_map(static function (array $f): array {
                return [
                    sprintf('%s %s-%05d-%08d', $f['tipo'], $f['letra'], $f['sucursal'], $f['nro']),
                    trim($f['cliente'].' '.$f['nombre']),
                    $f['provincia_actual'],
                    $f['provincia_correcta'],
                    number_format($f['porcentaje'], 2, '.', ''),
                    number_format($f['importe'], 2, '.', ''),
                    $f['fecha'],
                ];
            }, $filas)
        );

        $this->info('Filas a corregir: '.count($filas));

        $ok = 0;
        $fail = 0;
        foreach ($filas as $fila) {
            $res = VenibrZonamultCorreccionSupport::corregirFila($fila, $dryRun);
            if ($res['ok']) {
                $ok++;
                $this->line($res['mensaje']);
            } else {
                $fail++;
                $this->error($res['mensaje']);
            }
        }

        $this->line(sprintf('Resultado: ok=%d fail=%d', $ok, $fail));
        if ($dryRun) {
            $this->comment(
                'Para persistir: php artisan ventas:corregir-venibr-zonamult'
                .' --desde='.$desde.' --hasta='.$hasta.' --ejecutar'
            );
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
