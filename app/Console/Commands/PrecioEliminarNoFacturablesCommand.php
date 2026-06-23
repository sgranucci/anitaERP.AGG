<?php

namespace App\Console\Commands;

use App\Services\Stock\PrecioLimpiezaNoFacturableService;
use Illuminate\Console\Command;

class PrecioEliminarNoFacturablesCommand extends Command
{
  protected $signature = 'precio:eliminar-no-facturables {--dry-run : Solo muestra cuántos registros se eliminarían}';

  protected $description = 'Elimina precios de venta de artículos no facturables en ERP (no modifica Anita)';

  public function handle(PrecioLimpiezaNoFacturableService $service): int
  {
    if ($this->option('dry-run')) {
      $count = \DB::table('precio')
        ->join('articulo', 'articulo.id', '=', 'precio.articulo_id')
        ->where('articulo.nofactura', '1')
        ->count();
      $this->info("Se eliminarían {$count} registros de precio (artículos no facturables).");

      return self::SUCCESS;
    }

    $eliminados = $service->eliminarPreciosNoFacturables();
    $this->info("Eliminados {$eliminados} registros de precio de artículos no facturables.");

    return self::SUCCESS;
  }
}
