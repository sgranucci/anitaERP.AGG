<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Presupuesto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Presupuesto\Presupuesto_EscenarioRepositoryInterface;
use App\ApiAnita;
use Auth;
use DB;

class PresupuestoRepository implements PresupuestoRepositoryInterface
{
    protected $model;
    private $presupuesto_escenarioRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Presupuesto $presupuesto,
                                Presupuesto_EscenarioRepositoryInterface $presupuesto_escenariorepository)
    {
        $this->model = $presupuesto;
        $this->presupuesto_escenarioRepository = $presupuesto_escenariorepository;
    }

    public function all()
    {
        $hay_presupuesto = $this->model->first();

        if (!$hay_presupuesto)
			Self::sincronizarConAnita();

        return $this->model->with('creousuarios')->with('presupuesto_escenarios')->orderBy('id','desc')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		Self::ultimoCodigoPresupuesto($codigo);
		$data['codigo'] = $codigo;

        try
        {
            DB::beginTransaction();

            $presupuesto = $this->model->create($data);
            
            $nombres = $data['nombres'];
            $tipos = $data['tipos'];
            $codigosEscenarios = [];
            for ($i=0; $i < count($nombres); $i++) {
                if ($nombres[$i] != '') 
                {
                    $codigosEscenarios[$i] = '';
                    Self::ultimoCodigoEscenario($codigosEscenarios[$i]);

                    $this->presupuesto_escenarioRepository->create([
                                                        'presupuesto_id' => $presupuesto->id,
                                                        'nombre' => $nombres[$i],
                                                        'tipo' => $tipos[$i],
                                                        'codigo' => $codigosEscenarios[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }
            $data['codigos'] = $codigosEscenarios;

            // Graba anita
            $anita = Self::guardarAnita($data);

            if (isset($anita['error']))
            {
                if ($anita['error'] == 'Error')
                    throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
            }

            DB::commit();
        
            return redirect('presupuesto/presupuesto')->with('mensaje', 'Presupuesto creado con éxito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }        

        return $presupuesto;
    }

    public function update(array $data, $id)
    {
        try
        {
            DB::beginTransaction();

            $presupuesto = $this->model->findOrFail($id)->update($data);

            $this->presupuesto_escenarioRepository->deletePorPresupuesto($id);

            $nombres = $data['nombres'];
            $tipos = $data['tipos'];
            $codigos = $data['codigos'];
            $creousuario_escenario_ids = $data['creousuario_escenario_ids'];
            for ($i=0; $i < count($nombres); $i++) {
                if ($nombres[$i] != '') 
                {
                    if ($codigos[$i] == '')
                    {
                        Self::ultimoCodigoEscenario($codigos[$i]);
                    
                        $data['codigos'] = $codigos;
                    }

                    $this->presupuesto_escenarioRepository->create([
                                                        'presupuesto_id' => $id,
                                                        'nombre' => $nombres[$i],
                                                        'tipo' => $tipos[$i],
                                                        'codigo' => $codigos[$i],
                                                        'creousuario_id' => $creousuario_escenario_ids[$i]
                                                        ]);
                }
            }

            // Actualiza anita
            $anita = Self::actualizarAnita($data);

            if (isset($anita['error']))
            {
                if ($anita['error'] == 'Error')
                    throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
            }

            DB::commit();
        
            return redirect('presupuesto/presupuesto')->with('mensaje', 'Presupuesto actualizado con éxito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }            

		return $presupuesto;
    }

    public function delete($id)
    {
    	$presupuesto = $this->model->find($id);

        $codigo = $presupuesto->codigo;

        $presupuesto = $this->model->destroy($id);

        // Elimina anita
		self::eliminarAnita($codigo);

		return $presupuesto;
    }

    public function find($id)
    {
        if (null == $presupuesto = $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                        ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                        ->where('codigo',$codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $presupuesto = $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'base_admin',
                        'campos' => 'ipresupuestoid', 
                        'tabla' => 'presupuestos' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita)
		{
        	foreach ($dataAnita as $value) 
            {
               	$this->traerRegistroDeAnita($value->ipresupuestoid);
        	}
		}
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => 'presupuestos',
            'sistema' => 'base_admin',
            'campos' => '
                ipresupuestoid,
				iestadoid,
				cnombre,
				cdescripcion,
				ianio
            ' , 
            'whereArmado' => " WHERE ipresupuestoid = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            switch($data->iestadoid)
            {
            case '1':
                $estado = 'Abierto';
                break;
            default:
                $estado = 'Cerrado';
            }

            $presupuesto = $this->model->create([
                "codigo" => $data->ipresupuestoid,
                "nombre" => $data->cnombre,
                "detalle" => $data->cdescripcion,
                "estado" => $estado,
				"anio" => $data->ianio,
                "creousuario_id" => Auth()->id()
            ]);

            // Trae los escenarios
            $apiAnita = new ApiAnita();
            $data = array( 
                'acc' => 'list', 'tabla' => 'escenariospresup',
                'sistema' => 'base_admin',
                'campos' => '
                    ipresupuestoid,
                    iescenarioid,
                    cdescripcion,
                    itipoescenarioid
                ' , 
                'whereArmado' => " WHERE ipresupuestoid = '".$key."' " 
            );
            $dataAnita = json_decode($apiAnita->apiCall($data));

            if (count($dataAnita) > 0) {
                foreach($dataAnita as $escenario)
                {
                    if ($escenario->itipoescenarioid == 1)
                        $tipo = 'Original';
                    else    
                        $tipo = 'Análisis';

                    $this->presupuesto_escenarioRepository->create([
                        "presupuesto_id" => $presupuesto->id,
                        "codigo" => $escenario->iescenarioid,
                        "nombre" => $escenario->cdescripcion,
                        "tipo" => $tipo,
                        "creousuario_id" => Auth()->id()
                    ]);
                }
            }
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        if ($request['estado'] == 'Abierto')
            $estado = '1';
        else
            $estado = '2';

        $data = array( 'tabla' => 'presupuestos',
						'acc' => 'insert',
                        'sistema' => 'base_admin',
            			'campos' => ' 
                            ipresupuestoid,
                            iestadoid,
                            cnombre,
                            cdescripcion,
                            ianio',
            			'valores' => " 
                                '".$request['codigo']."', 
                                '".$estado."', 
                                '".$request['nombre']."', 
                                '".$request['detalle']."', 
                                '".$request['anio']."' "
        );
        $apiAnita->apiCall($data);

        // Graba escenarios
        $nombres = $request['nombres'];
        $tipos = $request['tipos'];
        $codigos = $request['codigos'];
        for ($i=0; $i < count($nombres); $i++) 
        {
            if ($nombres[$i] != '') 
            {
                if ($tipos[$i] == 'Original')
                    $tipo = 1;
                else
                    $tipo = 2;
                $data = array( 'tabla' => 'escenariospresup',
                                'acc' => 'insert',
                                'sistema' => 'base_admin',
                                'campos' => ' 
                                    ipresupuestoid,
                                    iescenarioid,
                                    cdescripcion,
                                    cversion,
                                    itipoescenarioid',
                                'valores' => " 
                                        '".$request['codigo']."', 
                                        '".$codigos[$i]."', 
                                        '".$nombres[$i]."', 
                                        '".'1.0'."',
                                        '".$tipo."' "
                );
                $apiAnita->apiCall($data);
            }
        }
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();

        if ($request['estado'] == 'Abierto')
            $estado = '1';
        else
            $estado = '2';

		$data = array( 'acc' => 'update', 
						'tabla' => 'presupuestos', 
                        'sistema' => 'base_admin',
						'valores' => " 
                            iestadoid = '".$estado."', 
                            cnombre = '".$request['nombre']."', 
                            cdescripcion = '".$request['detalle']."', 
                            ianio = '".$request['anio']."' ", 
						'whereArmado' => " WHERE ipresupuestoid='".$request['codigo']."' " );
        $apiAnita->apiCall($data);

        // Graba escenarios
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'escenariospresup',
                    'sistema' => 'base_admin',
					'whereArmado' => " WHERE ipresupuestoid='".$request['codigo']."' " );
        $apiAnita->apiCall($data);

        $nombres = $request['nombres'];
        $tipos = $request['tipos'];
        $codigos = $request['codigos'];
        for ($i=0; $i < count($nombres); $i++) 
        {
            if ($nombres[$i] != '') 
            {
                if ($tipos[$i] == 'Original')
                    $tipo = 1;
                else
                    $tipo = 2;
                $data = array( 'tabla' => 'escenariospresup',
                                'acc' => 'insert',
                                'sistema' => 'base_admin',
                                'campos' => ' 
                                    ipresupuestoid,
                                    iescenarioid,
                                    cdescripcion,
                                    cversion,
                                    itipoescenarioid',
                                'valores' => " 
                                        '".$request['codigo']."', 
                                        '".$codigos[$i]."', 
                                        '".$nombres[$i]."', 
                                        '".'1.0'."',
                                        '".$tipo."' "
                );
                $apiAnita->apiCall($data);
            }
        }        
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'presupuestos',
                    'sistema' => 'base_admin',
					'whereArmado' => " WHERE ipresupuestoid='".$id."' " );
        $apiAnita->apiCall($data);

        // Borra escenarios
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'escenariospresup',
                    'sistema' => 'base_admin',
					'whereArmado' => " WHERE ipresupuestoid='".$id."' " );
        $apiAnita->apiCall($data);
	}

   	// Devuelve ultimo codigo de presupuesto + 1 para agregar nuevos en Anita

	private function ultimoCodigoPresupuesto(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
            'tabla' => 'presupuestos', 
            'sistema' => 'base_admin',
            'campos' => " max(ipresupuestoid) as numeropresupuesto "
            );
				
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->numeropresupuesto != '')
		{
			$codigo = filter_var($dataAnita[0]->numeropresupuesto, FILTER_SANITIZE_NUMBER_INT);
			$codigo = $codigo + 1;
		}
		else
			$codigo = "1";
	}

   	// Devuelve ultimo codigo de presupuesto + 1 para agregar nuevos en Anita

	private function ultimoCodigoEscenario(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
            'tabla' => 'escenariospresup', 
            'sistema' => 'base_admin',
            'campos' => " max(iescenarioid) as numeroescenario "
            );
				
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->numeroescenario != '')
		{
			$codigo = filter_var($dataAnita[0]->numeroescenario, FILTER_SANITIZE_NUMBER_INT);
			$codigo = $codigo + 1;
		}
		else
		{
			$codigo = "1";
		}
	}    
}

