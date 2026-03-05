<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Articulo;

class Serigrafia extends Model
{
    protected $fillable = ['nombre','articulo_id'];
    protected $table = 'serigrafia';
    protected $keyField = 'seri_serigrafia';

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Serigrafia::all();
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
                seri_serigrafia,
				seri_desc,
				seri_articulo
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			// Lee el articulo para sacar el id
			$sku = ltrim($data->seri_articulo, '0');
			$id = Articulo::select('id')->where('sku', '=', $sku)->first();

			if (!$id)
				$articulo_id = 0;
			else 
				$articulo_id = $id->id;

            Serigrafia::create([
                "id" => $key,
				"nombre" => $data->seri_desc,
				"articulo_id" => $articulo_id
            ]);
        }
    }

	public function guardarAnita($request, $id, $sku) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'serigrafia', 'acc' => 'insert',
            'campos' => ' seri_serigrafia, seri_desc, seri_articulo ',
            'valores' => " '".$id."', '".$request->nombre."', '".str_pad($sku, 13, "0", STR_PAD_LEFT)."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $sku) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'serigrafia', 'valores' => " seri_desc = '".
					$request->nombre."', seri_articulo = '".str_pad($sku, 13, "0", STR_PAD_LEFT)."' ", 'whereArmado' => " WHERE seri_serigrafia = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'serigrafia', 'whereArmado' => " WHERE seri_serigrafia = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
