<?php

namespace App\Repositories\Presupuesto;

interface PartidagastoRepositoryInterface extends RepositoryInterface
{

    public function createDesdeAnita(array $data);

    /**
     * Partidas del último presupuesto para empresa (modal requisición).
     * Si se indica centrocostodestino_id, solo partidas con ese centro de costo.
     *
     * @return array{data: string}
     */
    public function consultaPartidagasto($consulta, $empresa_id, $centrocostodestino_id = null);
    
}

