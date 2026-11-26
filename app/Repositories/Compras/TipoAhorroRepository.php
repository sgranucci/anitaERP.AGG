<?php

namespace App\Repositories\Compras;

use App\Models\Compras\TipoAhorro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TipoAhorroRepository implements TipoAhorroRepositoryInterface
{
    public function __construct(private TipoAhorro $model)
    {
    }

    public function paginar(int $porPagina = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->orderBy('orden')->orderBy('nombre')->paginate($porPagina);
    }

    public function activos(): Collection
    {
        return $this->model->newQuery()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function create(array $data)
    {
        return $this->model->create($this->payload($data));
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($this->payload($data));
    }

    public function delete($id)
    {
        // Instancia a instancia: dispara el evento deleted y queda en `audits`.
        return $this->model->findOrFail($id)->delete();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function lineasQueLoUsan(int $id): int
    {
        return (int) DB::table('requisicion_articulo')->where('tipoahorro_id', $id)->count()
            + (int) DB::table('ordencompra_articulo')->where('tipoahorro_id', $id)->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nombre: string, orden: int, activo: bool}
     */
    private function payload(array $data): array
    {
        return [
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'orden' => (int) ($data['orden'] ?? 0),
            'activo' => ! empty($data['activo']),
        ];
    }
}
