<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class Articulo_Movimiento_TalleRepository implements Articulo_Movimiento_TalleRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Movimiento_Talle $articulo_movimiento_talle)
    {
        $this->model = $articulo_movimiento_talle;
    }

    public function all()
    {
        $ret = $this->model->get();

        return $ret;
    }

    public function find($id)
    {
        if (null == $articulo_movimiento_talle = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_movimiento_talle;
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
    	$articulo_movimiento_talle = $this->model->destroy($id);

		return $articulo_movimiento_talle;
    }

}
