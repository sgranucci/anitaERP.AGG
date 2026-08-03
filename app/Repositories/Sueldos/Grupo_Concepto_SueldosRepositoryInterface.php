<?php

namespace App\Repositories\Sueldos;

interface Grupo_Concepto_SueldosRepositoryInterface
{
    /** @param  array<string, mixed>|string|null  $filtros */
    public function leeGrupo($filtros, $flPaginando = null);

    public function findOrFail($id);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    /**
     * @return array{en_anita: int, grupos: int, items: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita(): array;

    /** Relaciona emp_grp* códigos ya guardados con FKs de grupo. */
    public function vincularEmpleadosConGrupos(): int;
}
