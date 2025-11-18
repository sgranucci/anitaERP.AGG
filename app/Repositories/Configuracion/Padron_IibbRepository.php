<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Iibb;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class Padron_IibbRepository implements Padron_IibbRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Iibb $padron_iibb)
    {
        $this->model = $padron_iibb;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)
            ->update($data);

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $padron_iibb = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb;
    }

    public function findOrFail($id)
    {
        if (null == $padron_iibb = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb;
    }

    public function findPorCuit($cuit)
    {
        return $this->model->select('id', 'cuit')->with('padron_iibb_tasas')->where('cuit', $cuit)->first();
    }

    // Busca tasas ya cargadas por jurisdiccion
    public function leePadronIibb($cuit, $tipo, $jurisdiccion)
	{
		// Elimino los posibles guiones
		$cuitFinal = str_replace("-", "", $cuit);

        $padron_iibb = $this->model->select('id', 'cuit')->where('cuit', $cuitFinal)->first();

        $tasa = $tasaDiferencial = $coeficiente = null;
        $riesgofiscal = $excluido = '';
        if ($padron_iibb)
        {
            foreach ($padron_iibb->padron_iibb_tasas as $tasas)
            {
                if ($tasas->provincias->jurisdiccion == $jurisdiccion)
                {
                    $tasa = ($tipo == "percepcion" ? $tasas->tasapercepcion : $tasas->tasaretencion);
                    $tasaDiferencial = ($tipo == "percepcion" ? $tasas->tasapercepciondiferencial : $tasas->tasapercepciondiferencial);
                    $coeficiente = $tasas->coeficiente;
                    $riesgoFiscal = $tasas->riesgofiscal; // Salta
                    $excluido = $tasas->excluido;         // Tucuman
                }
            }
        }

        return ['tasa' => $tasa, 'tasadiferencial' => $tasaDiferencial, 'coeficiente' => $coeficiente,
                'riesgofiscal' => $riesgofiscal, 'excluido' => $excluido];
	}

    // Lee padron para index

    function leePadron_Iibb($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $padron_iibbs = $this->model->select('padron_iibb.id as id',
                                        'padron_iibb.nombre as nombre',
										'padron_iibb.cuit as cuit')
                                ->where('padron_iibb.id', $busqueda)
                                ->orWhere('padron_iibb.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('padron_iibb.cuit', 'like', '%'.$busqueda.'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $padron_iibbs = $padron_iibbs->paginate(10);
            else
                $padron_iibbs = $padron_iibbs->get();
        }
        else
            $padron_iibbs = $padron_iibbs->get();

        return $padron_iibbs;
    }
}
