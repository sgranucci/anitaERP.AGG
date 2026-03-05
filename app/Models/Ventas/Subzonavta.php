<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Subzonavta extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'subzonavta';
    protected $tableAnita = 'subzona';
    protected $keyField = 'subz_codigo';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 
                        'sistema' => 'ventas',
						'tabla' => $this->tableAnita,
						'orderBy' => 'subz_codigo' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Subzonavta::all();
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
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'ventas',
            'campos' => '
                subz_codigo,
				subz_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Subzonavta::create([
                "id" => $key,
                "nombre" => $data->subz_desc,
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => ' subz_codigo, subz_desc ',
            'valores' => " '".$id."', 
						   '".$request->nombre."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
					'tabla' => $this->tableAnita, 
                    'sistema' => 'ventas',
					'valores' => " 
						subz_desc = '".  $request->nombre."' ",
					'whereArmado' => " WHERE subz_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'subzonavta', 
						'whereArmado' => " WHERE subz_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}

