<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Nivelsocioeconomico_Uif;
use App\Repositories\Uif\Nivelsocioeconomico_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Nivelsocioeconomico_UifRepository implements Nivelsocioeconomico_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Nivelsocioeconomico_Uif $nivelsocioeconomico_uif)
    {
        $this->model = $nivelsocioeconomico_uif;
    }

    public function all()
    {
        $nivelsocioeconomico_uif = $this->model->get();

        return $nivelsocioeconomico_uif;
    }

    public function create(array $data)
    {
        $nivelsocioeconomico_uif = $this->model->create($data);

        return($nivelsocioeconomico_uif);
    }

    public function update(array $data, $id)
    {
        $nivelsocioeconomico_uif = $this->model->findOrFail($id)->update($data);

		return $nivelsocioeconomico_uif;
    }

    public function delete($id)
    {
    	$nivelsocioeconomico_uif = $this->model->find($id);

        $nivelsocioeconomico_uif = $this->model->destroy($id);

		return $nivelsocioeconomico_uif;
    }

    public function find($id)
    {
        if (null == $nivelsocioeconomico_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $nivelsocioeconomico_uif;
    }

    public function findOrFail($id)
    {
        if (null == $nivelsocioeconomico_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $nivelsocioeconomico_uif;
    }

}
