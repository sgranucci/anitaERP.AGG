<?php

namespace App\Repositories\Receptivo;

use App\Models\Compras\Proveedor;
use App\Models\Receptivo\Proveedor_Servicioterrestre;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Proveedor_ServicioterrestreRepository implements Proveedor_ServicioterrestreRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Servicioterrestre $proveedor_servicioterrestre)
    {
        $this->model = $proveedor_servicioterrestre;
    }

    public function all()
    {
        return $this->model->with('proveedores')->with('servicioterrestres')->with('monedas')->orderBy('proveedor_id','ASC')->get();
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
    	$proveedor_servicioterrestre = $this->model->find($id);

        $proveedor_servicioterrestre = $this->model->destroy($id);

		return $proveedor_servicioterrestre;
    }

    public function find($id)
    {
        if (null == $proveedor_servicioterrestre = $this->model->with('proveedores')->with('servicioterrestres')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor_servicioterrestre;
    }

    public function findOrFail($id)
    {
        if (null == $proveedor_servicioterrestre = $this->model->with('proveedores')->with('servicioterrestres')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor_servicioterrestre;
    }

    public function leeCosto($servicioterrestre_id, $proveedor_id)
    {
        $proveedor_servicioterrestre = $this->model->where('servicioterrestre_id', $servicioterrestre_id)
                            ->where('proveedor_id', $proveedor_id)->first();

        return($proveedor_servicioterrestre);
    }

    public function leeProveedor($servicioterrestre_id)
    {
        $proveedor_servicioterrestre = $this->model->select('proveedor_id', 'proveedor.nombre as nombreproveedor')
                                            ->join('proveedor', 'proveedor.id', 'proveedor_servicioterrestre.proveedor_id')
                                            ->where('servicioterrestre_id', $servicioterrestre_id)->get();

        return($proveedor_servicioterrestre);
    }
}
