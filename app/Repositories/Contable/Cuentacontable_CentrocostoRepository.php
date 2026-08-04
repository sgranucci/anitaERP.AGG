<?php

namespace App\Repositories\Contable;

use App\ApiAnita;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Cuentacontable_Centrocosto;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class Cuentacontable_CentrocostoRepository implements Cuentacontable_CentrocostoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cuentacontable_Centrocosto $cuentacontable_centrocosto)
    {
        $this->model = $cuentacontable_centrocosto;
    }

    public function sincronizarDesdeAnita(): array
    {
        ini_set('max_execution_time', '600');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'sin_cuenta' => 0,
            'sin_centrocosto' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $dataAnita = json_decode($apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ccosvalid',
            'campos' => 'ccosv_empresa,ccosv_cuenta,ccosv_ccosto',
        ]));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió un listado válido de ccosvalid.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);

        $empresaPorCodigo = Empresa::query()->pluck('id', 'codigo');

        $centrocostoPorCodigo = [];
        foreach (Centrocosto::query()->get(['id', 'codigo']) as $cc) {
            $centrocostoPorCodigo[(string) (int) $cc->codigo] = (int) $cc->id;
        }

        $cuentaPorEmpresaCodigo = [];
        foreach (Cuentacontable::query()->get(['id', 'empresa_id', 'codigo']) as $cta) {
            $cuentaPorEmpresaCodigo[(int) $cta->empresa_id.'|'.$cta->codigo] = (int) $cta->id;
        }

        $existentes = [];
        foreach ($this->model->newQuery()->get(['cuentacontable_id', 'centrocosto_id']) as $row) {
            $existentes[(int) $row->cuentacontable_id.'|'.(int) $row->centrocosto_id] = true;
        }

        foreach ($dataAnita as $row) {
            $empresaCodigo = (string) ($row->ccosv_empresa ?? '');
            $cuentaCodigo = (string) ($row->ccosv_cuenta ?? '');
            $ccostoCodigo = (string) (int) ($row->ccosv_ccosto ?? 0);

            $empresaId = $empresaPorCodigo[$empresaCodigo] ?? null;
            if (! $empresaId) {
                $ret['sin_cuenta']++;
                continue;
            }

            $cuentaId = $cuentaPorEmpresaCodigo[(int) $empresaId.'|'.$cuentaCodigo] ?? null;
            if (! $cuentaId) {
                $ret['sin_cuenta']++;
                continue;
            }

            $centrocostoId = $centrocostoPorCodigo[$ccostoCodigo] ?? null;
            if (! $centrocostoId) {
                $ret['sin_centrocosto']++;
                continue;
            }

            $clave = $cuentaId.'|'.$centrocostoId;
            if (isset($existentes[$clave])) {
                $ret['omitidos']++;
                continue;
            }

            try {
                $this->model->create([
                    'cuentacontable_id' => $cuentaId,
                    'centrocosto_id' => $centrocostoId,
                ]);
                $existentes[$clave] = true;
                $ret['importados']++;
            } catch (Throwable $e) {
                $ret['errores'][] = "emp {$empresaCodigo} cta {$cuentaCodigo} cc {$ccostoCodigo}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    public function create(array $data, $id)
    {
		return self::guardarCuentacontable_Centrocosto($data, 'create', $id);
    }
	
    public function createUnRegistro(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarCuentacontable_Centrocosto($data, 'update', $id);
    }

    public function delete($cuentacontable_id, $codigo)
    {
        return $this->model->where('cuentacontable_id', $cuentacontable_id)->delete();
    }

    public function find($id)
    {
        if (null == $cuentacontable_centrocosto = $this->model
											->with('centrocostos')->find($id)) 				
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacontable_centrocosto;
    }

	public function leeCuentacontable_Centrocosto($cuentacontable_id)
	{
		$cuentacontable_centrocosto = $this->model->select('cuentacontable_centrocosto.centrocosto_id as id', 
													'centrocosto.codigo', 
													'centrocosto.nombre as nombre')
												->join('centrocosto', 'centrocosto.id', 'cuentacontable_centrocosto.centrocosto_id')
												->where('cuentacontable_id', $cuentacontable_id)->get();

		return $cuentacontable_centrocosto;
	}
	
    public function findOrFail($id)
    {
        if (null == $cuentacontable_centrocosto = $this->model
										->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarCuentacontable_Centrocosto($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cuentacontable_centrocosto = $this->model->where('cuentacontable_id', $id)->get()->pluck('id')->toArray();
			$q_cuentacontable_centrocosto = count($cuentacontable_centrocosto);
		}

		// Graba exclusiones
		if (isset($data['centrocosto_ids']))
		{
			$centrocosto_ids = $data['centrocosto_ids'];

			if ($funcion == 'update')
			{
				$_id = $cuentacontable_centrocosto;

				// Borra los que sobran
				if ($q_cuentacontable_centrocosto > count($centrocosto_ids))
				{
					for ($d = count($centrocosto_ids); $d < $q_cuentacontable_centrocosto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cuentacontable_centrocosto && $i < count($centrocosto_ids); $i++)
				{
					if ($i < count($centrocosto_ids))
					{
						$cuentacontable_centrocosto = $this->model->findOrFail($_id[$i])->update([
									"cuentacontable_id" => $id,
									"centrocosto_id" => $centrocosto_ids[$i]
									]);
					}
				}
				if ($q_cuentacontable_centrocosto > count($centrocosto_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_centrocosto = $i; $i_centrocosto < count($centrocosto_ids); $i_centrocosto++)
			{
				//* Valida si se cargo un centro de costo
				if ($centrocosto_ids[$i_centrocosto] != '') 
				{
					$cuentacontable_centrocosto = $this->model->create([
									"cuentacontable_id" => $id,
									"centrocosto_id" => $centrocosto_ids[$i_centrocosto]
									]);
				}
			}
		}
		else
		{
			$cuentacontable_centrocosto = $this->model->where('cuentacontable_id', $id)->delete();
		}
	}
}
