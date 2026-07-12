<?php

namespace App\Repositories\Caja\Bingo;

interface TurnoBingoRepositoryInterface
{
    public function all();

    public function listarParaSelect(?int $empresaId = null);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
