<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Subcategoria extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'subcategoria';
    protected $keyField = 'subc_id';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Subcategoria::all();
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
                subc_id,
                subc_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
			$subcategoria = new Subcategoria();

            Subcategoria::create([
				"id" => $key,
				"nombre" => $data->subc_desc,
				"codigo" => $data->subc_id
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'subcategoria', 'acc' => 'insert',
            'campos' => ' subc_id, subc_desc ',
            'valores' => " '".$request->codigo."', '".$request->nombre."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'subcategoria', 'valores' => " subc_desc = '".
					$request->nombre."' ", 'whereArmado' => " WHERE subc_id = '".$request->codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'subcategoria', 'whereArmado' => " WHERE subc_id = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}
}
