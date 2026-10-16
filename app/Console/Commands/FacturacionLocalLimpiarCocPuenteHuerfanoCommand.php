<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalLimpiarCocPuenteHuerfanoService;
use Illuminate\Console\Command;

class FacturacionLocalLimpiarCocPuenteHuerfanoCommand extends Command
{
    protected $signature = 'facturacion-local:limpiar-coc-puente-huerfano
        {--ejecutar : Borra los asientos ERP (subdiario) COC ML↔puente del 29–30/09. Sin este flag solo informa.}';

    protected $description = 'Elimina COC huérfanos de cuenta puente TFC (29–30/09) importados del Anita viejo.';

    public function handle(FacturacionLocalLimpiarCocPuenteHuerfanoService $servicio): int
    {
        if (! $this->option('ejecutar')) {
            $r = $servicio->previsualizar();
            $this->info('Desde: '.$r['desde'].'  Hasta: '.$r['hasta']);
            $this->info('Asientos a borrar: '.$r['asientos']);
            $this->info('Monto puente (Haber): '.number_format($r['monto_puente'], 2, '.', ''));
            $this->info('Monto L-MERCADOLIBRE (Debe): '.number_format($r['monto_mercadolibre'], 2, '.', ''));
            $this->info('Por fecha: '.json_encode($r['por_fecha']));
            if ($r['muestras'] !== []) {
                $this->newLine();
                $this->comment('Muestra:');
                foreach ($r['muestras'] as $m) {
                    $this->line(sprintf(
                        '  id %d nro %s %s %s puente %s',
                        $m['id'],
                        (string) $m['numeroasiento'],
                        $m['fecha'],
                        $m['observacion'],
                        number_format($m['monto_puente'], 2, '.', '')
                    ));
                }
            }
            $this->newLine();
            $this->comment('No se borró nada. Para aplicar: --ejecutar');

            return self::SUCCESS;
        }

        $r = $servicio->aplicar();
        $this->info('Asientos borrados: '.$r['asientos']);
        $this->info('Monto puente revertido: '.number_format($r['monto_puente'], 2, '.', ''));

        return self::SUCCESS;
    }
}
