<?php

namespace App\Repositories\Stock;

interface Deposito_AdministradorRepositoryInterface
{
    public function all();

    public function find(int $id);

    public function porDeposito(int $depositoId);

    public function porUsuario(int $usuarioId);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id): bool;
}
