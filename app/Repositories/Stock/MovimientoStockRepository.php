<?php

namespace App\Repositories\Stock;

use App\Models\Stock\MovimientoStock;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MovimientoStockRepository implements MovimientoStockRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(MovimientoStock $movimientostock)
    {
        $this->model = $movimientostock;
    }

    public function estadoEnum()
    {
        return $this->model->estadoEnum();
    }

    public function latest($campo)
    {
        return $this->model->latest($campo)->first();
    }

    public function all()
    {
        $ret = $this->model->with('articulos_movimiento')->with('tipostransaccion')->with('mventas')->get();

        return $ret;
    }

    public function find($id)
    {
        if (null == $movimientostock = $this->model->where('id', $id)->with('articulos_movimiento')->with('tipostransaccion')->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $movimientostock;
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
    	$movimientostock = $this->model->destroy($id);

		return $movimientostock;
    }

    public function deletePorId($id)
    {
        return $this->model->where('id', $id)->delete();
    }

}
