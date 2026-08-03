<?php

namespace App\Repositories\Sueldos;

interface Novedad_SueldosRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeNovedad($filtros, $flPaginando = null);

    public function findOrFail($id);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    /**
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    /**
     * @param  array{empresa_id?: int, numeros_liquidacion?: list<int>}|null  $filtros
     */
    public function sincronizarConAnita(?array $filtros = null): array;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{importados: int, omitidos: int, errores: list<string>}
     */
    public function importarFilas(array $filas, string $origen = 'import'): array;
}
