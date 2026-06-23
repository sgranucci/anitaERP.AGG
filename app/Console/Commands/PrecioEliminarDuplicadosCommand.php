<?php

namespace App\Console\Commands;

use App\Services\Stock\PrecioLimpiezaDuplicadosService;
use Illuminate\Console\Command;

class PrecioEliminarDuplicadosCommand extends Command
{
    protected $signature = 'precio:eliminar-duplicados
                            {--dry-run : Solo muestra cuántos registros se eliminarían}
                            {--force : Ejecutar sin confirmación interactiva}
                            {--chunk=1000 : Tamaño de lote para la eliminación}';

    protected $description = 'Elimina precios duplicados (mismo artículo, lista y vigencia); conserva el id más alto';

    public function handle(PrecioLimpiezaDuplicadosService $service): int
    {
        $resumen = $service->resumenDuplicados();

        if ($resumen['registros_a_eliminar'] === 0) {
            $this->info('No hay precios duplicados por artículo + lista + vigencia.');

            return self::SUCCESS;
        }

        $this->line("Grupos duplicados: {$resumen['grupos_duplicados']}");
        $this->line("Registros a eliminar (conserva mayor id): {$resumen['registros_a_eliminar']}");

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se eliminó ningún registro.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Confirmar eliminación de precios duplicados en ERP?', true)) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        $chunk = (int) $this->option('chunk');
        $resultado = $service->eliminarDuplicados($chunk);

        $this->info("Eliminados {$resultado['eliminados']} registros duplicados de precio.");

        $restante = $service->resumenDuplicados();
        if ($restante['registros_a_eliminar'] > 0) {
            $this->warn("Quedan {$restante['registros_a_eliminar']} duplicados pendientes; revisar manualmente.");
        }

        return self::SUCCESS;
    }
}
