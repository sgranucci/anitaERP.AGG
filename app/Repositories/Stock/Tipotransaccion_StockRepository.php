<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Tipotransaccion_StockRepository implements Tipotransaccion_StockRepositoryInterface
{
    protected $model;

    public function __construct(Tipotransaccion_Stock $tipotransaccion_stock)
    {
        $this->model = $tipotransaccion_stock;
    }

    public function all($operacion = null, $estado = null)
    {
        $query = $this->model->newQuery();

        if ($operacion && $operacion != '*') {
            $query->whereIn('operacion', (array) $operacion);
        }

        if ($estado) {
            $query->whereIn('estado', (array) $estado);
        }

        return $query->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $tipotransaccion = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $tipotransaccion;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function resolveIdFromLegacy(int $id): int
    {
        if ($id <= 0) {
            return $id;
        }

        if ($this->model->newQuery()->whereKey($id)->exists()) {
            return $id;
        }

        if (Schema::hasTable('tipotransaccion_stock_map')) {
            $mapped = DB::table('tipotransaccion_stock_map')
                ->where('tipotransaccion_id', $id)
                ->value('tipotransaccion_stock_id');

            if ($mapped) {
                return (int) $mapped;
            }
        }

        return $id;
    }

    public function findIdPorAbreviatura(string $abreviatura): int
    {
        $id = $this->model->newQuery()
            ->where('abreviatura', $abreviatura)
            ->where('estado', 'A')
            ->value('id');

        if ($id === null) {
            throw new ModelNotFoundException('Tipo de transacción de stock no encontrado: '.$abreviatura);
        }

        return (int) $id;
    }
}
