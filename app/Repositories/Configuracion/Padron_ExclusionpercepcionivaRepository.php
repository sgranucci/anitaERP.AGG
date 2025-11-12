<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Exclusionpercepcioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Padron_ExclusionpercepcionivaRepository implements Padron_ExclusionpercepcionivaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Exclusionpercepcioniva $padron_exclusionpercepcioniva)
    {
        $this->model = $padron_exclusionpercepcioniva;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $padron_exclusionpercepcioniva = $this->model->create($data);

        return($padron_exclusionpercepcioniva);
    }

    public function update(array $data, $id)
    {
        $padron_exclusionpercepcioniva = $this->model->findOrFail($id)->update($data);

		return $padron_exclusionpercepcioniva;
    }

    public function delete($id)
    {
    	$padron_exclusionpercepcioniva = $this->model->find($id);

        $padron_exclusionpercepcioniva = $this->model->destroy($id);

		return $padron_exclusionpercepcioniva;
    }

    public function find($id)
    {
        if (null == $padron_exclusionpercepcioniva = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_exclusionpercepcioniva;
    }

    public function findOrFail($id)
    {
        if (null == $padron_exclusionpercepcioniva = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_exclusionpercepcioniva;
    }

    public function buscaPadron_Exclusionpercepcioniva($cuit, $fechainicio)
    {
        $padron_exclusionpercepcioniva = $this->model->where('cuit', $cuit)
                                    ->where('fechainicio', $fechainicio)->get();
    }

	public function leePadron_Exclusionpercepcioniva($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $padron_exclusionpercepcioniva = Padron_Exclusionpercepcioniva::select('padron_exclusionpercepcioniva.id as id',
                                        'padron_exclusionpercepcioniva.nombre as nombre',
										'padron_exclusionpercepcioniva.cuit as cuit',
										'padron_exclusionpercepcioniva.desdefecha as desdefecha',
                                        'padron_exclusionpercepcioniva.hastafecha as hastafecha')
                                ->where('padron_exclusionpercepcioniva.id', $busqueda)
                                ->orWhere('padron_exclusionpercepcioniva.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('padron_exclusionpercepcioniva.cuit', 'like', '%'.$busqueda.'%')
								->orWhere('padron_exclusionpercepcioniva.desdefecha', 'like', '%'.$busqueda,'%')
                                ->orWhere('padron_exclusionpercepcioniva.hastafecha', 'like', '%'.$busqueda,'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $padron_exclusionpercepcioniva = $padron_exclusionpercepcioniva->paginate(10);
            else
                $padron_exclusionpercepcioniva = $padron_exclusionpercepcioniva->get();
        }
        else
            $padron_exclusionpercepcioniva = $padron_exclusionpercepcioniva->get();

        return $padron_exclusionpercepcioniva;
    }

}
