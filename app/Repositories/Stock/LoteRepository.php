<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Lote;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class LoteRepository implements LoteRepositoryInterface
{
    protected $model;
    
    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Lote $lote)
    {
        $this->model = $lote;
    }

    public function all()
    {
        $ret = $this->model->get();

        return $ret;
    }

    public function create(array $data)
    {
        $data['usuario_id'] = Auth::id();

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data['usuario_id'] = Auth::id();
        
        return $this->model->findOrFail($id)->update($data);
	}

    public function delete($id)
    {
        return $this->model->destroy($id);
	}

    public function find($id)
    {
        if (null == $lote = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $lote;
    }

    public function findOrFail($id)
    {
        if (null == $lote = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $lote;
    }
}
