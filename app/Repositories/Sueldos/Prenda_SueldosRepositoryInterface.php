<?php

namespace App\Repositories\Sueldos;

interface Prenda_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leePrenda($filtros, $flPaginando = null);

    public function findPorCodigo(int $codigo);

    /**
     * @return array{en_anita: int, importados: int, omitidos: int, variantes: int, errores: list<string>}
     */
    public function sincronizarConAnita();
}
