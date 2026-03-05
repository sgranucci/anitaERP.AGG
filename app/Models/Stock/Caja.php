<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Articulo;

class Caja extends Model
{
    protected $fillable = ['nombre','desdenro','hastanro','articulo_id'];
    protected $table = 'cajaproducto';
    protected $tableAnita = 'numcaja';
    protected $keyFieldAnita = 'numca_codigo';
    protected $keyField = 'id';

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function articulos_caja()
    {
        return $this->hasMany(Articulo_Caja::class);
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => $this->keyFieldAnita, 
						'tabla' => $this->tableAnita, 
						'orderBy' => $this->keyFieldAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Caja::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyFieldAnita}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                numca_codigo,
				numca_desde_nro,
				numca_hasta_nro,
				numca_articulo
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			// Lee el articulo para sacar el id
			$sku = ltrim($data->numca_articulo, '0');
			$id = Articulo::select('id')->where('sku', '=', $sku)->first();

			if (!$id)
				$articulo_id = 0;
			else 
				$articulo_id = $id->id;

            Caja::create([
                "id" => $key,
				"nombre" => $data->numca_desde_nro." / ".$data->numca_hasta_nro. " - ".$data->numca_codigo,
				"desdenro" => $data->numca_desde_nro,
				"hastanro" => $data->numca_hasta_nro,
				"articulo_id" => $articulo_id
            ]);
        }
    }

	public function guardarAnita($request, $id, $sku) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' numca_codigo, numca_desde_nro, numca_hasta_nro, numca_articulo ',
            'valores' => " '".$id."', '".$request->desdenro."', '".$request->hastanro."', '".str_pad($sku, 13, "0", STR_PAD_LEFT)."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $sku) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
					'valores' => " numca_desde_nro = '".$request->desdenro."', numca_hasta_nro = '".$request->hastanro."', numca_articulo = '".str_pad($sku, 13, "0", STR_PAD_LEFT)."' ", 'whereArmado' => " WHERE numca_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 'whereArmado' => " WHERE numca_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
