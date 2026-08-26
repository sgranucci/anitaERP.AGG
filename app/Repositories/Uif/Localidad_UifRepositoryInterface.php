<?php

namespace App\Repositories\Uif;

interface Localidad_UifRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorCodigo($codigo);

    /**
     * @return array{insertados: int, actualizados: int, eliminados: int, omitidos_con_clientes: int}
     */
    public function resincronizarConAnita(): array;

    /**
     * @return array{actualizados: int, sin_cambio: int, sin_provincia_uif: int, omitidos_sin_local: int}
     */
    public function reasignarProvinciasDesdeAnita(): array;
}

