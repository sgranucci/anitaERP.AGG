<?php

namespace App\Repositories\Ventas;

interface CamionRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita();

    /**
     * @return array<string, mixed>
     */
    public function resincronizarDesdeAnita(bool $dryRun = true, bool $actualizarExistentes = true): array;

    public function traerRegistroDeAnita($key);

    public function guardarAnita($request);

    public function actualizarAnita($request, $id);

    public function eliminarAnita($id);

    public function consultaCamion(string $consulta): string;

    public function findPorCodigo(string $codigo);
}
