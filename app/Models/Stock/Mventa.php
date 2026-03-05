<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Mventa extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'mventa';
    protected $tablaAnita = 'marmae';
    protected $keyField = 'marm_marca';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->tablaAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Mventa::all();
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
            'acc' => 'list', 'tabla' => $this->tablaAnita, 
            'campos' => '
                marm_marca,
                marm_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            $codigo = ltrim($data->marm_marca, '0');

            Mventa::create([
                "nombre" => $data->marm_desc,
                "codigo" => $codigo
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'marmae', 'acc' => 'insert',
            'campos' => ' marm_marca, marm_desc ',
            'valores' => " '".str_pad($request->codigo, 8, "0", STR_PAD_LEFT)
                                ."', '".$request->nombre."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $codigo = str_pad($request->codigo, 8, "0", STR_PAD_LEFT);
		$data = array( 'acc' => 'update', 'tabla' => 'marmae', 'valores' => " marm_desc = '".
					$request->nombre."' ", 'whereArmado' => " WHERE marm_marca = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($codigo) {
        $apiAnita = new ApiAnita();

        $codigo = str_pad($request->codigo, 8, "0", STR_PAD_LEFT);

        $data = array( 'acc' => 'delete', 'tabla' => 'marmae', 'whereArmado' => " WHERE marm_marca = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}
}
