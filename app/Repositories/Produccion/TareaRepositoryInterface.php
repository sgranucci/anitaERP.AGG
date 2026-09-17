<?php

namespace App\Repositories\Produccion;

interface TareaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function create(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function find($id);
    public function findOrFail($id);

    /** HTML filas del modal de consulta (id + nombre + Elegir/Consultar). */
    public function consultaModal(?string $texto): array;

    /** JSON para resolver por id (código Anita = id). */
    public function findParaConsulta($id): ?array;

}

