<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Numeracion extends Model
{
    protected $fillable = ['nombre', 'desde_nro', 'hasta_nro'];
    protected $table = 'numeracion';
    protected $keyField = 'numer_numeracion';

	public function lineas()
    {
        return $this->hasMany(Linea::class);
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 'campos' => $this->keyField, 'orderBy' => ' numer_numeracion ASC ',
				'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Numeracion::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyField});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'campos' => '
                numer_numeracion,
				numer_desc,
				numer_desde_nro,
				numer_hasta_nro
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Numeracion::create([
                "id" => $key,
                "nombre" => $data->numer_desc,
                "desde_nro" => $data->numer_desde_nro,
                "hasta_nro" => $data->numer_hasta_nro
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'numeracion', 'acc' => 'insert',
            'campos' => ' numer_numeracion, numer_desc, numer_desde_nro, numer_hasta_nro ',
            'valores' => " '".$id."', '".$request->nombre."', '".$request->desde_nro."', '".$request->hasta_nro."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
				'tabla' => 'numeracion', 
				'valores' => " numer_desc = '".  $request->nombre."' ", 
							 " numer_desde_nro = '".  $request->desde_nro."' ", 
							 " numer_hasta_nro = '".  $request->hasta_nro."' ", 
				'whereArmado' => " WHERE numer_numeracion = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'numeracion', 'whereArmado' => " WHERE numer_numeracion = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
