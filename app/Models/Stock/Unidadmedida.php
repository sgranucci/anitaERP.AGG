<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Unidadmedida extends Model
{
    protected $fillable = ['id', 'nombre', 'abreviatura', 'codigo'];
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

        $datosLocalArray = Unidadmedida::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($dataAnita as $value) {
            $idAnita = (int) ($value->{$this->keyField} ?? 0);
            if ($idAnita <= 0 || in_array($idAnita, $datosLocalArray, true)) {
                continue;
            }
            $this->traerRegistroDeAnita($value->{$this->keyField});
            $datosLocalArray[] = $idAnita;
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
                'id' => (int) $key,
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
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita,
				'valores' => " stkum_desc = '".$request->nombre.
					"' , stkum_abreviatura = '".$request->abreviatura."'", 
				'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
