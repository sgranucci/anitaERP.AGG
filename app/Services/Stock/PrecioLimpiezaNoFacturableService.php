<?php

namespace App\Services\Stock;

use App\Support\Stock\PrecioSoloFacturableSupport;
use Illuminate\Support\Facades\DB;

class PrecioLimpiezaNoFacturableService
{
  /**
   * Elimina precios de venta de artículos no facturables solo en ERP (no Anita).
   *
   * @return int Registros eliminados
   */
  public function eliminarPreciosNoFacturables(): int
  {
    return DB::table('precio')
      ->join('articulo', 'articulo.id', '=', 'precio.articulo_id')
      ->where('articulo.nofactura', PrecioSoloFacturableSupport::NOFACTURA_NO_FACTURABLE)
      ->delete();
  }
}
