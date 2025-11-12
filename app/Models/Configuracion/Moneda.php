<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Moneda extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'codigo'];
    protected $table = 'moneda';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'mon_codigo';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'sistema' => 'shared', 'campos' => $this->keyFieldAnita, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Moneda::all();
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
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'sistema' => 'shared',
            'campos' => '
                mon_codigo,
				mon_desc,
				mon_abreviatura
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Moneda::create([
                "id" => $key,
                "nombre" => $data->mon_desc,
                "abreviatura" => $data->mon_abreviatura
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->table, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' mon_codigo, mon_desc, mon_abreviatura',
            			'valores' => " '".$id."', '".$request->nombre."', '".$request->abreviatura."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->table, 
						'valores' => " mon_desc = '".$request->nombre."', mon_abreviatura = '".$request->abreviatura."' ", 
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'shared', 'tabla' => 'moneda', 
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
