<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ProvinciaRepository implements ProvinciaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'provincia';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'provi_provincia';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provincia $provincia)
    {
        $this->model = $provincia;
    }

    public function all()
    {
        $hay_provincia = Provincia::first();

        if (!$hay_provincia)
			self::sincronizarConAnita();

        return $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                    ->with('provincia_cuentacontableiibbs')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $provincia = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $data['codigo']);

        return $provincia;
    }

    public function update(array $data, $id)
    {
        $provincia = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $provincia;
    }

    public function delete($id)
    {
    	$provincia = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($provincia->codigo);

        $provincia = $this->model->destroy($id);

		return $provincia;
    }

    public function find($id)
    {
        if (null == $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                        ->with('provincia_cuentacontableiibbs')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia;
    }

    public function findPorId($id)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                            ->with('provincia_cuentacontableiibbs')->where('id', $id)->first();

        return $provincia;
    }

    public function findPorCodigo($codigo)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                    ->with('provincia_cuentacontableiibbs')->where('codigo', $codigo)->first();

        return $provincia;
    }

    public function findPorJurisdiccion($jurisdiccion)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                    ->with('provincia_cuentacontableiibbs')->where('jurisdiccion', $jurisdiccion)->first();

        return $provincia;
    }

    public function findOrFail($id)
    {
        if (null == $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                                ->with('provincia_cuentacontableiibbs')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'shared',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Provincia::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
		if ($dataAnita)
		{
        	foreach ($dataAnita as $value) {
            	if (!in_array($value->{$this->keyFieldAnita}, $datosLocalArray)) {
                	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            	}
        	}
		}
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'EL BIERZO')
            $data = array( 
                'acc' => 'list', 'tabla' => $this->tableAnita, 
                'sistema' => 'shared',
                'campos' => '
                    provi_provincia,
                    provi_desc,
                    provi_abrev,
                    provi_jurisdiccion,
                    provi_cod_externo
                ' , 
                'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
            );
        else
            $data = array( 
                'acc' => 'list', 'tabla' => $this->tableAnita, 
                'sistema' => 'shared',
                'campos' => '
                    provi_provincia,
                    provi_desc,
                    provi_abrev,
                    provi_jurisdiccion,
                    provi_letra
                ' , 
                'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
            );            
        $dataAnita = json_decode($apiAnita->apiCall($data));
        
        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            if (config('app.empresa') == 'EL BIERZO')
                $codigoExterno = $data->provi_cod_externo;
            else
                $codigoExterno = $data->provi_provincia;

            Provincia::create([
                "id" => $data->provi_provincia,
                "nombre" => $data->provi_desc,
                "abreviatura" => $data->provi_abrev,
                "jurisdiccion" => $data->provi_jurisdiccion,
                "codigo" => $data->provi_provincia,
                "pais_id" => 1,
                "codigoexterno" => $codigoExterno
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if (config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion, provi_cod_externo',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
                                        '".$request['jurisdiccion']."',
										'".$request['codigoexterno']."' "
            );
        else
            $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
										'".$request['jurisdiccion']."' "
            );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'EL BIERZO')
		    $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" provi_desc = '".$request['nombre']."',
							provi_abrev = '".$request['abreviatura']."',
                            provi_jurisdiccion = '".$request['jurisdiccion']."',
							provi_cod_externo = '".$request['codigoexterno']."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );
        else
            $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" provi_desc = '".$request['nombre']."',
							provi_abrev = '".$request['abreviatura']."',
							provi_jurisdiccion = '".$request['jurisdiccion']."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );            
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->tableAnita,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );
        $apiAnita->apiCall($data);
	}
}
