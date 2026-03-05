<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Tipocorte extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'tipocorte';
    protected $keyField = 'tipoc_codigo';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Tipocorte::all();
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
                tipoc_codigo,
				tipoc_desc,
				tipoc_abrev ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Tipocorte::create([
				"nombre" => $data->tipoc_desc,
				"abreviatura" => $data->tipoc_abrev
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'tipocorte', 'acc' => 'insert',
            'campos' => ' tipoc_codigo, tipoc_desc, tipoc_abrev ',
            'valores' => " '".$request->codigo."', '".$request->nombre."' , '".$request->abreviatura."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'tipocorte', 
				'valores' => " tipoc_desc = '".$request->nombre.
					"' , tipoc_abrev = '".$request->abreviatura."'", 
				'whereArmado' => " WHERE tipoc_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'tipocorte', 'whereArmado' => " WHERE tipoc_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
