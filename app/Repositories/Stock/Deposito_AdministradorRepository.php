<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Deposito_Administrador;

class Deposito_AdministradorRepository implements Deposito_AdministradorRepositoryInterface
{
    public function all()
    {
        return Deposito_Administrador::query()
            ->with(['depositos:id,nombre,codigo', 'usuarios:id,nombre,email'])
            ->orderBy('deposito_id')
            ->get();
    }

    public function find(int $id)
    {
        return Deposito_Administrador::query()
            ->with(['depositos:id,nombre', 'usuarios:id,nombre,email'])
            ->findOrFail($id);
    }

    public function porDeposito(int $depositoId)
    {
        return Deposito_Administrador::query()
            ->where('deposito_id', $depositoId)
            ->where('recibe_avisos', true)
            ->with('usuarios:id,nombre,email')
            ->get();
    }

    public function porUsuario(int $usuarioId)
    {
        return Deposito_Administrador::query()
            ->where('usuario_id', $usuarioId)
            ->with('depositos:id,nombre,codigo')
            ->get();
    }

    public function create(array $data)
    {
        return Deposito_Administrador::create($data);
    }

    public function update(int $id, array $data)
    {
        $row = Deposito_Administrador::findOrFail($id);
        $row->fill($data)->save();

        return $row;
    }

    public function delete(int $id): bool
    {
        $row = Deposito_Administrador::find($id);
        if (! $row) {
            return false;
        }

        return (bool) $row->delete();
    }
}
