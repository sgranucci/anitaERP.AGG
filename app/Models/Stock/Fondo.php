<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Articulo;

class Fondo extends Model
{
    protected $fillable = ['nombre','articulo_id', 'codigo'];
    protected $table = 'fondo';
    protected $keyField = 'fon_fondo';

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Fondo::all();
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
                fon_fondo,
				fon_desc,
				fon_articulo
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
			$sku = ltrim($data->fon_articulo, '0');
			// Lee el articulo para sacar el id
			$id = Articulo::select('id')->where('sku', '=', $sku)->first();

			if (!$id)
				$articulo_id = 0;
			else 
				$articulo_id = $id->id;

            Fondo::create([
                "id" => $key,
				"nombre" => $data->fon_desc,
				"articulo_id" => $articulo_id,
				"codigo" => $data->{$this->keyField}
            ]);
        }
    }

	public function guardarAnita($request, $id, $sku, $codigo) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'fondo', 'acc' => 'insert',
            'campos' => ' fon_fondo, fon_desc, fon_articulo ',
            'valores' => " '".$codigo."', '".$request->nombre."', '".str_pad($sku, 13, "0", STR_PAD_LEFT)."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $sku, $codigo) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'fondo', 'valores' => " fon_desc = '".
					$request->nombre."', fon_articulo = '".str_pad($sku, 13, "0", STR_PAD_LEFT)."' ", 'whereArmado' => " WHERE fon_fondo = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'fondo', 'whereArmado' => " WHERE fon_fondo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
