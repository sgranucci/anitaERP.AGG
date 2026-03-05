<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo;
use App\Repositories\Caja\RendicionreceptivoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class RendicionreceptivoRepository implements RendicionreceptivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo $rendicionreceptivo)
    {
        $this->model = $rendicionreceptivo;
    }

	public function all()
    {
        return $this->model->with('guias')->with('moviles')->with('cajas')
                        ->with('empresas')->with('usuarios')
						->with('rendicionreceptivo_caja_movimientos')
						->with('rendicionreceptivo_vouchers')
						->with('rendicionreceptivo_formapagos')
                        ->with('rendicionreceptivo_comisiones')
                        ->with('rendicionreceptivo_adelantos')
						->with('caja_movimientos')
						->get();
    }

    public function leeRendicionreceptivo($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $rendicionreceptivo = $this->model->select('rendicionreceptivo.id as id',
                                        'rendicionreceptivo.numerotalonario as numerotalonario',
                                        'rendicionreceptivo.fecha as fecha',
										'rendicionreceptivo.ordenservicio_id as ordenservicio_id',
                                        'guia.nombre as nombreguia',
                                        'movil.nombre as nombremovil')
                                ->join('guia', 'guia.id', '=', 'rendicionreceptivo.guia_id')
								->join('movil', 'movil.id', '=', 'rendicionreceptivo.movil_id')
                                ->where('rendicionreceptivo.numerotalonario', $busqueda)
                                ->orWhere('rendicionreceptivo.fecha', $busqueda)  
                                ->orWhere('guia.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('movil.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('rendicionreceptivo.ordenservicio_id', $busqueda)  
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $rendicionreceptivo = $rendicionreceptivo->paginate(10);
            else
                $rendicionreceptivo = $rendicionreceptivo->get();
        }
        else
            $rendicionreceptivo = $rendicionreceptivo->get();

        return $rendicionreceptivo;
    }

    public function create(array $data)
    {
		$data['numerotalonario'] = self::ultimoNumeroTransaccion($data['empresa_id']);
		$data['usuario_id'] = Auth::user()->id;

		$rendicionreceptivo = $this->model->create($data);

		return $rendicionreceptivo;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		
		$rendicionreceptivo = $this->model->findOrFail($id)->update($data);

		return $rendicionreceptivo;
    }

    public function delete($id)
    {
		$rendicionreceptivo = $this->model->findOrFail($id);

		// Elimina anita
		if ($rendicionreceptivo)
        	$rendicionreceptivo = $this->model->destroy($id);

		return $rendicionreceptivo;
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo = $this->model->with("rendicionreceptivo_caja_movimientos")
											->with("rendicionreceptivo_vouchers")
											->with("rendicionreceptivo_formapagos")
                                            ->with('rendicionreceptivo_comisiones')
                                            ->with('rendicionreceptivo_adelantos')
											->with("caja_movimientos")
											->with("empresas")
											->with("cajas")
											->with("moviles")
											->with("guias")
											->with("usuarios")
											->find($id)) 
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo = $this->model->with("rendicionreceptivo_caja_movimientos")
											->with("rendicionreceptivo_vouchers")
											->with("rendicionreceptivo_formapagos")
                                            ->with('rendicionreceptivo_comisiones')
                                            ->with('rendicionreceptivo_adelantos')
											->with("caja_movimientos")
											->with("empresas")
											->with("cajas")
											->with("moviles")
											->with("guias")
											->with("usuarios")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $rendicionreceptivo;
    }

	private function ultimoNumeroTransaccion($empresa_id) 
	{
		$rendicionreceptivo = $this->model->select('empresa_id', 'numerotalonario')
										->where('empresa_id', $empresa_id)
										->orderBy('numerotalonario', 'desc')->first();
		
		$numerotalonario = 0;
        if ($rendicionreceptivo) 
		{
			$numerotalonario = $rendicionreceptivo->numerotalonario;
			$numerotalonario = $numerotalonario + 1;
		}
		else	
			$numerotalonario = 1;

		return $numerotalonario;
	}
}
