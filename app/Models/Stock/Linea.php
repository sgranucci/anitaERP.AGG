<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Modulo;
use App\Models\Stock\Tiponumeracion;
use App\Models\Stock\Numeracion;
use App\Models\Stock\Listaprecio;

class Linea extends Model
{
    protected $fillable = ['nombre', 'codigo', 'tiponumeracion_id', 'maxhorma', 'numeracion_id', 'listaprecio_id'];
    protected $table = 'linea';
    protected $tableAnita = 'linmae';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'linm_linea';

	public function modulos()
	{
    	return $this->belongsToMany(Modulo::class, 'linea_modulo');
	}

    public function tiponumeraciones()
    {
        return $this->belongsTo(Tiponumeracion::class, 'tiponumeracion_id');
    }

    public function numeraciones()
    {
        return $this->belongsTo(Numeracion::class, 'numeracion_id');
    }

    public function listaprecios()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => $this->keyFieldAnita, 
						'orderBy' => $this->keyFieldAnita,
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Linea::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyFieldAnita}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();

		if (config('app.empresa') == 'Calzados Ferli')
		{
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita, 
				'campos' => '
					linm_linea,
					linm_desc,
					linm_numeracion,
					linm_max_hormas,
					linm_cod_numer,
					linm_lista_precio
				' , 
				'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
			);
		}
		else
		{
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita, 
				'campos' => '
					linm_linea,
					linm_desc
				' , 
				'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
			);
		}

        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];
			$codigo = ltrim($data->linm_linea, '0');

			if (config('app.empresa') == 'Calzados Ferli')
			{
				$tiponumeracion = Tiponumeracion::where('codigo', $data->linm_numeracion)->first();

				$linea = Linea::create([
					"id" => $key,
					"nombre" => $data->linm_desc,
					"codigo" => $codigo,
					"tiponumeracion_id" => $tiponumeracion->id,
					"maxhorma" => $data->linm_max_hormas,
					"numeracion_id" => $data->linm_cod_numer,
					"listaprecio_id" => $data->linm_lista_precio
				]);
				
				$apiAnita = new ApiAnita();
				$data = array( 
					'acc' => 'list', 'tabla' => 'linmod', 
					'campos' => '
						linmo_linea,
						linmo_modulo
					' , 
					'whereArmado' => " WHERE linmo_linea = '".$key."' " 
				);
				$dataAnita = json_decode($apiAnita->apiCall($data));

				if (count($dataAnita) > 0) 
				{
					foreach ($dataAnita as $data)
					{
						// Traigo id del modulo 
						$modulo = Modulo::where('id', $data->linmo_modulo)->first();

						if (!empty($modulo))
							$linea->modulos()->attach($modulo->id);
					}
				}
			}
			else
			{
				$linea = Linea::create([
					"id" => $key,
					"nombre" => $data->linm_desc,
					"codigo" => $codigo,
					"tiponumeracion_id" => 1,
					"maxhorma" => 0
				]);
			}
		}
    }

	public function guardarAnita($request, $id, $modulos) 
	{
        $apiAnita = new ApiAnita();

		$codigo = str_pad($request->codigo, 6, "0", STR_PAD_LEFT);

		if (config('app.empresa') == 'Calzados Ferli')
		{
			$tiponumeracion = Tiponumeracion::where('id', $request->tiponumeracion_id)->first();

			$data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
				'campos' => ' linm_linea, linm_desc, linm_numeracion, linm_max_hormas, linm_cod_numer, linm_lista_precio',
				'valores' => " '".$codigo."', '".$request->nombre."', '".$tiponumeracion->codigo."', '".$request->maxhorma."', '".$request->numeracion_id."', '".$request->listaprecio_id."' "
			);
			$apiAnita->apiCall($data);

			for ($modulo = 0; $modulo < count($modulos); $modulo++)
			{
				$data = array( 'tabla' => 'linmod', 'acc' => 'insert',
					'campos' => 'linmo_linea, linmo_modulo',
					'valores' => " '".$codigo."', '".$modulos[$modulo]."' "
				);
				$apiAnita->apiCall($data);
			}
		}
		else
		{
			$data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
				'campos' => ' linm_linea, linm_desc',
				'valores' => " '".$codigo."', '".$request->nombre."' "
			);
			$apiAnita->apiCall($data);
		}
	}

	public function actualizarAnita($request, $id, $modulos) {
        $apiAnita = new ApiAnita();

		$codigo = str_pad($request->codigo, 6, "0", STR_PAD_LEFT);

		if (config('app.empresa') == 'Calzados Ferli')
		{	
			$tiponumeracion = Tiponumeracion::where('id', $request->tiponumeracion_id)->first();

			$data = array( 'acc' => 'update', 'tabla' => 'linmae', 
						'valores' => " linm_desc = '".$request->nombre."' , linm_numeracion = '".$tiponumeracion->codigo."' , linm_max_hormas = '".$request->maxhorma."' , linm_cod_numer = '".$request->numeracion_id."' , linm_lista_precio = '".$request->listaprecio_id."' ", 
						'whereArmado' => " WHERE linm_linea = '".$codigo."' " );
			$apiAnita->apiCall($data);

			for ($modulo = 0; $modulo < count($modulos); $modulo++)
			{
				// Borra los modulos de las lineas
				$data = array( 'acc' => 'delete', 
					'tabla' => 'linmod',
					'whereArmado' => " WHERE linmo_linea = '".$codigo."' " );
				$apiAnita->apiCall($data);

				// Graba los modulos de las lineas
				$data = array( 'tabla' => 'linmod', 'acc' => 'insert',
					'campos' => 'linmo_linea, linmo_modulo',
					'valores' => " '".$codigo."', '".$modulos[$modulo]."' ");
				$apiAnita->apiCall($data);
			}
		}
		else
		{
			$data = array( 'acc' => 'update', 'tabla' => 'linmae', 
						'valores' => " linm_desc = '".$request->nombre."' ",
						'whereArmado' => " WHERE linm_linea = '".$codigo."' " );
			$apiAnita->apiCall($data);
		}
	}

	public function eliminarAnita($codigo) {
		$codigo = str_pad($codigo, 6, "0", STR_PAD_LEFT);

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita,
			'whereArmado' => " WHERE linm_linea = '".$codigo."' " );
        $apiAnita->apiCall($data);

		if (config('app.empresa') == 'Calzados Ferli')
		{
			$data = array( 'acc' => 'delete', 
				'tabla' => 'linmod', 
				'whereArmado' => " WHERE linmo_linea = '".$codigo."' " );
			$apiAnita->apiCall($data);
		}
	}
}
