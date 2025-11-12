<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Zonavta extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'zonavta';
    protected $keyField = 'zonv_codigo';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 
                        'sistema' => 'ventas',
						'tabla' => $this->table, 
						'orderBy' => 'zonv_codigo' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Zonavta::all();
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
            'sistema' => 'ventas',
            'campos' => '
                zonv_codigo,
				zonv_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Zonavta::create([
                "id" => $key,
                "nombre" => $data->zonv_desc,
                "codigo" => $data->zonv_codigo
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'zonavta', 'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => ' zonv_codigo, zonv_desc ',
            'valores' => " '".$id."', 
						   '".$request->nombre."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'zonavta', 
                    'sistema' => 'ventas',
					'valores' => " 
								zonv_desc = '".  $request->nombre."' ",
					'whereArmado' => " WHERE zonv_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'zonavta', 
						'whereArmado' => " WHERE zonv_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
    
}
