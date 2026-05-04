<?php

namespace App\Repositories\Presupuesto;

interface CapexRepositoryInterface extends RepositoryInterface
{

    public function createDesdeAnita(array $data);

    /**
     * Proyectos CAPEX del último presupuesto para empresa (modal requisición u otros).
     * Si se indica centrocostodestino_id, solo registros con ese centro de costo.
     *
     * @return array{data: string}
     */
    public function consultaCapex($consulta, $empresa_id, $centrocostodestino_id = null);
}

