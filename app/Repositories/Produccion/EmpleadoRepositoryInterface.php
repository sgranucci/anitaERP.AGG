<?php

namespace App\Repositories\Produccion;

interface EmpleadoRepositoryInterface extends RepositoryInterface
{

    public function all();

    public function find($id);

    /** HTML filas del modal de consulta (legajo + nombre + Elegir/Consultar). */
    public function consultaModal(?string $texto): array;

    /** JSON para resolver por legajo/id. */
    public function findParaConsulta($id): ?array;

}

