<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalReclasificarCuentaVentaService;
use Illuminate\Console\Command;

class FacturacionLocalReclasificarCuentaVentaCommand extends Command
{
    protected $signature = 'facturacion-local:reclasificar-cuenta-venta
        {--ejecutar : Pasa el neto de F-VENTAS a L-VENTAS en los asientos de locales desde el 21/09/2026. Sin este flag solo informa.}';

    protected $description = 'Reclasifica a L-VENTAS el neto de locales que quedó en F-VENTAS.';

    public function handle(FacturacionLocalReclasificarCuentaVentaService $servicio): int
    {
        if (! $this->option('ejecutar')) {
            $resumen = $servicio->previsualizar();
            $this->info('Comprobantes: '.$resumen['comprobantes']);
            $this->info('Asientos: '.$resumen['asientos']);
            $this->info('Líneas F-VENTAS: '.$resumen['lineas']);
            $this->info('Neto: '.number_format($resumen['neto'], 2, '.', ''));
            $this->info('Líneas con centro de costo: '.$resumen['con_centrocosto']);
            if ($resumen['otras_cuentas'] !== []) {
                $this->warn('Otras cuentas de ingreso: '.implode(', ', $resumen['otras_cuentas']));
            }
            $this->comment('No se grabó nada. Para aplicarlo: --ejecutar');

            return self::SUCCESS;
        }

        $resultado = $servicio->aplicar();
        $this->info('Asientos reclasificados: '.$resultado['asientos']);
        $this->info('Líneas: '.$resultado['lineas']);
        $this->info('Neto pasado a L-VENTAS: '.number_format($resultado['neto'], 2, '.', ''));

        return self::SUCCESS;
    }
}
