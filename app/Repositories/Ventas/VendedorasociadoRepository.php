<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Vendedorasociado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class VendedorasociadoRepository implements VendedorasociadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Vendedorasociado $vendedorasociado)
    {
        $this->model = $vendedorasociado;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $vendedorasociado = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $vendedorasociado = $this->model->findOrFail($id)
            ->update($data);

		return $vendedorasociado;
    }

    public function delete($id)
    {
    	$vendedorasociado = $this->model->find($id);

        $vendedorasociado = $this->model->destroy($id);

		return $vendedorasociado;
    }

    public function find($id)
    {
        if (null == $vendedorasociado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $vendedorasociado;
    }

    public function findPorId($id)
    {
        $vendedorasociado = $this->model->where('id', $id)->first();

        return $vendedorasociado;
    }

    public function findPorCodigo($codigo)
    {
        $vendedorasociado = $this->model->where('codigo', $codigo)->first();

        return $vendedorasociado;
    }

    public function findOrFail($id)
    {
        if (null == $vendedorasociado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $vendedorasociado;
    }


    public function deletePorVendedor($id)
    {
        $vendedorasociado = $this->model->where('vendedor_id', $id)->delete();
    }

}
