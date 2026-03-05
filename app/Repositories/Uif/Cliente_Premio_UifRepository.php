<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Premio_Uif;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_Premio_UifRepository implements Cliente_Premio_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Premio_Uif $cliente_premio_uif)
    {
        $this->model = $cliente_premio_uif;
    }

	public function leeCliente_Premio_Uif($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $cliente_premio_uifs = $this->model->select('cliente_premio_uif.id as id',
                                        'cliente_uif.nombre as nombrecliente',
										'sala.nombre as nombresala',
										'juego_uif.nombre as nombrejuego',
										'cliente_premio_uif.fechaentrega as fechaentrega',
										'cliente_premio_uif.monto as monto',
                                        'cliente_premio_uif.posicion as posicion',
										'cliente_premio_uif.numerotito as numerotito',
										'formapago.nombre as nombreformapago')
                                ->join('cliente_uif', 'cliente_uif.id', '=', 'cliente_premio_uif.cliente_uif_id')
                                ->leftjoin('sala', 'sala.id', '=', 'cliente_premio_uif.sala_id')
                                ->leftjoin('juego_uif', 'juego_uif.id', '=', 'cliente_premio_uif.juego_uif_id')
								->leftjoin('formapago', 'formapago.id', '=', 'cliente_premio_uif.formapago_id')
								->where('cliente_uif.deleted_at', null)
                                ->where('cliente_uif.id', $busqueda)
                                ->orWhere('cliente_uif.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('cliente_premio_uif.fechaentrega', '=', $busqueda)  
								->orWhere('cliente_premio_uif.monto', 'like', '%'.$busqueda.'%')
								->orWhere('cliente_premio_uif.posicion', 'like', '%'.$busqueda.'%')
								->orWhere('cliente_premio_uif.numerotito', 'like', '%'.$busqueda.'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cliente_premio_uifs = $cliente_premio_uifs->paginate(10);
            else
                $cliente_premio_uifs = $cliente_premio_uifs->get();
        }
        else
            $cliente_premio_uifs = $cliente_premio_uifs->get();

		return $cliente_premio_uifs;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Premio_Uif($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_premio_uif = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Premio_Uif($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_premio_uif = $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_premio_uif = $this->model->with('clientes_uif')->with('cliente_premio_archivos_uif')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_premio_uif = $this->model->with('clientes_uif')->with('cliente_premio_archivos_uif')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_uif;
    }

	private function guardarCliente_Premio_Uif($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_premio_uif = $this->model->where('cliente_uif_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_premio_uif = count($cliente_premio_uif);
		}

		// Graba premios
		if (isset($data))
		{
			$premio_ids = $data['idpremios'];
			$sala_ids = $data['sala_ids'];
			$juego_uif_ids = $data['juego_uif_ids'];
			$fechaEntregas = $data['fechaentregas'];
			$detalles = $data['detalles'];
			$montos = $data['montos'];
			$moneda_ids = $data['moneda_ids'];
			$posiciones = $data['posiciones'];
			$numeroTitos = $data['numerotitos'];
			$fechaTitos = $data['fechatitos'];
			$formapago_ids = $data['formapago_ids'];
			$piderecibopagos = $data['piderecibopagos'];
			$creousuario_ids = $data['creousuario_premio_ids'];

			if ($funcion == 'update')
			{
				$_id = $cliente_premio_uif;

				// Borra los que sobran
				if ($q_cliente_premio_uif > count($premio_ids))
				{
					for ($d = count($premio_ids); $d < $q_cliente_premio_uif; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cliente_premio_uif && $i < count($premio_ids); $i++)
				{
					if ($i < count($premio_ids))
					{
						$cliente_premio_uif = $this->model->findOrFail($_id[$i])->update([
									"cliente_uif_id" => $id,
									"premio_id" => $premio_ids[$i],
									"sala_id" => $sala_ids[$i],
									"juego_uif_id" => $juego_uif_ids[$i],
									"fechaentrega" => $fechaEntregas[$i],
									"detalle" => $detalles[$i],
									"monto" => $montos[$i],
									"moneda_id" => $moneda_ids[$i],
									"posicion" => $posiciones[$i],
									"numerotito" => $numeroTitos[$i],
									"fechatito" => $fechaTitos[$i],
									"formapago_id" => $formapago_ids[$i],
									"piderecibopago" => $piderecibopagos[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_cliente_premio_uif > count($premio_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($premio_ids); $i_movimiento++)
			{
				if ($premio_ids[$i_movimiento] != '') 
				{
					$cliente_premio_uif = $this->model->create([
						"cliente_uif_id" => $id,
						"premio_id" => $premio_ids[$i_movimiento],
						"sala_id" => $sala_ids[$i_movimiento],
						"juego_uif_id" => $juego_uif_ids[$i_movimiento],
						"fechaentrega" => $fechaEntregas[$i_movimiento],
						"detalle" => $detalles[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"posicion" => $posiciones[$i_movimiento],
						"numerotito" => $numeroTitos[$i_movimiento],
						"fechatito" => $fechaTitos[$i_movimiento],
						"formapago_id" => $formapago_ids[$i_movimiento],
						"piderecibopago" => $piderecibopagos[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]						
						]);
				}
			}
		}
		else
		{
			$cliente_premio_uif = $this->model->where('cliente_uif_id', $id)->delete();
		}

		return $cliente_premio_uif;
	}

	public function listaPremioParaExportar($periodo, $limiteinformeuif)
	{
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		$fecha = conviertePeriodoEnRangoFecha($periodo, true);
		$desdeFecha = $fecha['desdefecha'];
		$hastaFecha = $fecha['hastafecha'];

        $cliente_premio_uifs = $this->model->select('cliente_premio_uif.id as premioid',
										'cliente_premio_uif.monto as monto',
										'cliente_premio_uif.fechaentrega as fechaentrega',
										'cliente_uif.id as clienteid',
                                        'cliente_uif.nombre as nombrecliente',
                                        'tipodocumento.abreviatura as abreviaturatipodocumento',
                                        'cliente_uif.numerodocumento as numerodocumento',
                                        'cliente_uif.domicilio as domicilio',
										'cliente_uif.piso as piso',
										'cliente_uif.departamento as departamento',
                                        'localidad_uif.nombre as nombrelocalidad',
                                        'provincia_uif.nombre as nombreprovincia',
										'pais_uif.nombre as nombrepais',
										'cliente_uif.telefono as telefono',
                                        'cliente_uif.email as email',
										'sala.nombre as nombresala')
								->join('cliente_uif', 'cliente_uif.id', '=', 'cliente_premio_uif.cliente_uif_id')
								->join('tipodocumento', 'tipodocumento.id', '=', 'cliente_uif.tipodocumento_id')
                                ->leftjoin('localidad_uif', 'localidad_uif.id', '=', 'cliente_uif.localidad_uif_id')
                                ->leftjoin('provincia_uif', 'provincia_uif.id', '=', 'cliente_uif.provincia_uif_id')
								->leftjoin('pais_uif', 'pais_uif.id', '=', 'cliente_uif.pais_uif_id')
                                ->leftjoin('sala', 'sala.id', '=', 'cliente_premio_uif.sala_id')
                                ->leftjoin('juego_uif', 'juego_uif.id', '=', 'cliente_premio_uif.juego_uif_id')
								->where('cliente_premio_uif.deleted_at', null)
								->whereBetween('cliente_premio_uif.fechaentrega', [$desdeFecha, $hastaFecha])
                                ->orderby('cliente_premio_uif.fechaentrega', 'ASC')->get();
                                
		return $cliente_premio_uifs;
	}
}
