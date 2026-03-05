<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Talonariovoucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TalonariovoucherRepository implements TalonariovoucherRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Talonariovoucher $talonariovoucher)
    {
        $this->model = $talonariovoucher;
    }

    public function all()
    {
        return $this->model->with("origenesvoucher")->get();
    }

    public function create(array $data)
    {
        $talonariovoucher = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $talonariovoucher = $this->model->findOrFail($id)->update($data);

		return $talonariovoucher;
    }

    public function delete($id)
    {
    	$talonariovoucher = $this->model->find($id);

        $talonariovoucher = $this->model->destroy($id);

		return $talonariovoucher;
    }

    public function find($id)
    {
        if (null == $talonariovoucher = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $talonariovoucher;
    }

    public function findOrFail($id)
    {
        if (null == $talonariovoucher = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $talonariovoucher;
    }
}
