<?php

namespace App\Repositories\Solicitudpago;

interface FormapagosolRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);

    public function guardarAnita(array $data): void;

    public function actualizarAnita(array $data, int $codigo): void;

    public function eliminarAnita(int $codigo): void;
}
