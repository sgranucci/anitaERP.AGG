<?php

namespace App\Repositories\Ventas;

interface CaiRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita();

    public function guardarAnita(array $data): void;

    public function actualizarAnita(array $data, int $orden): void;

    public function eliminarAnita(int $orden): void;
}
