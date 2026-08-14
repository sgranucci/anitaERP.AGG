<?php

namespace App\Repositories\Caja;

interface PerdidaPersonalRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leePerdidaPersonal($filtros, bool $paginar = false);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    /**
     * UPSERT histórico por número (actualiza existentes e inserta faltantes).
     *
     * @return array{
     *     en_anita: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarConAnita(?int $numero = null, bool $actualizarExistentes = true): array;
}
