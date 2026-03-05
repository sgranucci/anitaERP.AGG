<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Unidadmedida extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'codigo'];
    protected $table = 'unidadmedida';
    protected $tableAnita = 'stkumd';
    protected $keyField = 'stkum_umd';

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Unidadmedida::all();
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
            'campos' => '
                stkum_umd,
				stkum_desc,
				stkum_abreviatura ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Unidadmedida::create([
				"nombre" => $data->stkum_desc,
				"abreviatura" => $data->stkum_abreviatura
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'campos' => ' stkum_umd, stkum_desc, stkum_abreviatura ',
            'valores' => " '".$id."', '".$request->nombre."' , '".$request->abreviatura."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita,
				'valores' => " stkum_desc = '".$request->nombre.
					"' , stkum_abreviatura = '".$request->abreviatura."'", 
				'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
