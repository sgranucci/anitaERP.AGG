<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Origenvoucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrigenvoucherRepository implements OrigenvoucherRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Origenvoucher $origenvoucher)
    {
        $this->model = $origenvoucher;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $origenvoucher = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $origenvoucher = $this->model->findOrFail($id)->update($data);

		return $origenvoucher;
    }

    public function delete($id)
    {
    	$origenvoucher = $this->model->find($id);

        $origenvoucher = $this->model->destroy($id);

		return $origenvoucher;
    }

    public function find($id)
    {
        if (null == $origenvoucher = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $origenvoucher;
    }

    public function findOrFail($id)
    {
        if (null == $origenvoucher = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $origenvoucher;
    }
}
