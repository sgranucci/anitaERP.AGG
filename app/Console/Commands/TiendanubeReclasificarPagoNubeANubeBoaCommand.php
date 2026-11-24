<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\Tiendanube\TiendanubeReclasificarPagoNubeANubeBoaService;
use Illuminate\Console\Command;

class TiendanubeReclasificarPagoNubeANubeBoaCommand extends Command
{
    protected $signature = 'tiendanube:reclasificar-pago-nube-a-nube-boa
        {--ejecutar : Pasa L-PAGO NUBE → L-NUBE BOA en asientos PV 26 desde 16/10/2026. Sin este flag solo informa.}';

    protected $description = 'Reclasifica piernas Pago Nube→Nube Boa en PV 26 (API, cambios/NC; Ferli).';

    public function handle(TiendanubeReclasificarPagoNubeANubeBoaService $servicio): int
    {
        if (! $this->option('ejecutar')) {
            $resumen = $servicio->previsualizar();
            $this->info('Desde: '.$resumen['desde']);
            $this->info('PV: '.$resumen['pv']);
            $this->info('Origen: '.$resumen['origen'].' → Destino: '.$resumen['destino']);
            $this->info('Asientos: '.$resumen['asientos']);
            $this->info('Ventas: '.$resumen['ventas']);
            $this->info('Líneas: '.$resumen['lineas']);
            $this->info('Monto: '.number_format($resumen['monto'], 2, '.', ''));
            $this->info('Medios caja 609→612: '.(int) ($resumen['caja_medios'] ?? 0));
            if ($resumen['muestras'] !== []) {
                $this->newLine();
                $this->comment('Muestra (primeras '.count($resumen['muestras']).'):');
                foreach ($resumen['muestras'] as $m) {
                    $this->line(sprintf(
                        '  asiento %d venta %d %s %s monto %s',
                        $m['asiento_id'],
                        $m['venta_id'],
                        $m['fecha'],
                        $m['comprobante'],
                        number_format($m['monto'], 2, '.', '')
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
        $this->info('Ventas: '.$resultado['ventas']);
        $this->info('Monto: '.number_format($resultado['monto'], 2, '.', ''));
        $this->info('Medios caja actualizados: '.(int) ($resultado['caja_medios'] ?? 0));

        return self::SUCCESS;
    }
}
