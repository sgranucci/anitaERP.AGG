<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Mipyme;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Padron_MipymeRepository implements Padron_MipymeRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Mipyme $padron_mipyme)
    {
        $this->model = $padron_mipyme;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $padron_mipyme = $this->model->create($data);

        return($padron_mipyme);
    }

    public function update(array $data, $id)
    {
        $padron_mipyme = $this->model->findOrFail($id)->update($data);

		return $padron_mipyme;
    }

    public function delete($id)
    {
    	$padron_mipyme = $this->model->find($id);

        $padron_mipyme = $this->model->destroy($id);

		return $padron_mipyme;
    }

    public function find($id)
    {
        if (null == $padron_mipyme = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_mipyme;
    }

    public function findOrFail($id)
    {
        if (null == $padron_mipyme = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_mipyme;
    }

    public function buscaPadron_Mipyme($cuit, $fechainicio)
    {
        $padron_mipyme = $this->model->where('cuit', $cuit)
                                    ->where('fechainicio', $fechainicio)->get();
    }

	public function leePadron_Mipyme($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $padron_mipyme = Padron_Mipyme::select('padron_mipyme.id as id',
                                        'padron_mipyme.nombre as nombre',
										'padron_mipyme.cuit as cuit',
										'padron_mipyme.actividad as actividad',
                                        'padron_mipyme.fechainicio as fechainicio')
                                ->where('padron_mipyme.id', $busqueda)
                                ->orWhere('padron_mipyme.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('padron_mipyme.cuit', 'like', '%'.$busqueda.'%')
								->orWhere('padron_mipyme.actividad', 'like', '%'.$busqueda,'%')
                                ->orWhere('padron_mipyme.fechainicio', 'like', '%'.$busqueda,'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $padron_mipyme = $padron_mipyme->paginate(10);
            else
                $padron_mipyme = $padron_mipyme->get();
        }
        else
            $padron_mipyme = $padron_mipyme->get();

        return $padron_mipyme;
    }

}
