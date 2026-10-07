<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\FacturanteIvaNormalizacionService;
use Illuminate\Console\Command;

class FacturanteNormalizarIvaCommand extends Command
{
    protected $signature = 'facturacion-local:normalizar-facturante-iva
        {--ejecutar : Graba precio con IVA, gravado/IVA y el asiento. Sin este flag solo informa.}';

    protected $description = 'Deja las facturas de Facturante con precio con IVA y gravado/IVA, como una emisión del local.';

    public function handle(FacturanteIvaNormalizacionService $servicio): int
    {
        if (! $this->option('ejecutar')) {
            $resumen = $servicio->previsualizar();
            $this->info('Revisadas: '.$resumen['revisadas']);
            $this->info('A corregir: '.$resumen['corregibles']);
            $this->info('Omitidas: '.$resumen['omitidas']);
            foreach ($resumen['muestras'] as $linea) {
                $this->line($linea);
            }
            foreach ($resumen['omitidas_detalle'] as $linea) {
                $this->warn($linea);
            }
            $this->comment('No se grabó nada. Para aplicarlo: --ejecutar');

            return self::SUCCESS;
        }

        $resultado = $servicio->aplicar();
        $this->info('Corregidas: '.$resultado['aplicadas']);
        foreach ($resultado['omitidas'] as $linea) {
            $this->warn($linea);
        }

        return self::SUCCESS;
    }
}
