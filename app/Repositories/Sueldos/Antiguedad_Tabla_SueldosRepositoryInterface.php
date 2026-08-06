<?php

namespace App\Repositories\Sueldos;

interface Antiguedad_Tabla_SueldosRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     * @return mixed
     */
    public function leeAntiguedadTabla($filtros, $flPaginando = null);

    /** @param  array<string, mixed>  $data */
    public function create(array $data);

    /** @param  array<string, mixed>  $data */
    public function update(array $data, $id);

    public function delete($id);

    public function findOrFail($id);

    /**
     * @return array{en_anita: int, tablas: int, tramos: int, errores: list<string>}
     */
    public function sincronizarConAnita(): array;
}
