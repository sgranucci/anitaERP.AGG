<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalReclasificarDeudoresAMedioService;
use Illuminate\Console\Command;

class FacturacionLocalReclasificarDeudoresAMedioCommand extends Command
{
    protected $signature = 'facturacion-local:reclasificar-deudores-a-medio
        {--ejecutar : Reemplaza DEUDORES POR VENTAS por la cuenta del medio en asientos desde el 29/09/2026. Sin este flag solo informa.}';

    protected $description = 'Reclasifica piernas Deudores→medio (un asiento por factura) en ventas Ferli desde 29/09/2026.';

    public function handle(FacturacionLocalReclasificarDeudoresAMedioService $servicio): int
    {
        if (! $this->option('ejecutar')) {
            $resumen = $servicio->previsualizar();
            $this->info('Desde: '.$resumen['desde']);
            $this->info('Asientos: '.$resumen['asientos']);
            $this->info('Ventas: '.$resumen['ventas']);
            $this->info('Líneas: '.$resumen['lineas']);
            $this->info('Monto neto pierna Deudores: '.number_format($resumen['monto'], 2, '.', ''));
            $this->newLine();
            $this->info('Por cuenta destino:');
            foreach ($resumen['por_destino'] as $fila) {
                $this->line(sprintf(
                    '  %s %s — líneas %d — monto %s',
                    $fila['cuenta'],
                    $fila['nombre'],
                    $fila['lineas'],
                    number_format($fila['monto'], 2, '.', '')
                ));
            }
            if ($resumen['omitidas'] !== []) {
                $this->newLine();
                $this->warn('Omitidas (sin medio resoluble): '.count($resumen['omitidas']));
                foreach (array_slice($resumen['omitidas'], 0, 15) as $omi) {
                    $this->line(sprintf(
                        '  asiento %d venta %d pv %s — %s',
                        $omi['asiento_id'],
                        $omi['venta_id'],
                        $omi['pv'],
                        $omi['motivo']
                    ));
                }
                if (count($resumen['omitidas']) > 15) {
                    $this->line('  … y '.(count($resumen['omitidas']) - 15).' más');
                }
            }
            if ($resumen['muestras'] !== []) {
                $this->newLine();
                $this->comment('Muestra (primeras '.count($resumen['muestras']).'):');
                foreach ($resumen['muestras'] as $m) {
                    $this->line(sprintf(
                        '  asiento %d venta %d pv %s monto %s → %s',
                        $m['asiento_id'],
                        $m['venta_id'],
                        $m['pv'],
                        number_format($m['monto'], 2, '.', ''),
                        $m['destino']
                    ));
                }
            }
            $this->newLine();
            $this->comment('No se grabó nada. Para aplicarlo: --ejecutar');

            return self::SUCCESS;
        }

        $resultado = $servicio->aplicar();
        $this->info('Líneas reclasificadas: '.$resultado['lineas']);
        $this->info('Asientos: '.$resultado['asientos']);
        $this->info('Monto: '.number_format($resultado['monto'], 2, '.', ''));
        $this->info('Omitidas: '.$resultado['omitidas']);

        return self::SUCCESS;
    }
}
