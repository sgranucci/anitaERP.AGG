<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;

/**
 * Altas de artículos en el ERP desde Anita (stkmae) vía {@see ApiAnita}, mismo criterio que el listado histórico.
 *
 * @see Articulo::sincronizarConAnita()
 */
class ArticuloAnitaSyncService
{
    /**
     * @return array{en_anita:int, importados:int, omitidos_ya_en_erp:int, advertencias:list<string>}
     */
    public function sincronizarDesdeAnita(): array
    {
        return (new Articulo)->sincronizarConAnita();
    }
}
