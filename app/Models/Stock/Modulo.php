<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Talle;
use App\Models\Ventas\Pedido_Combinacion;

class Modulo extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'modulo';
    protected $keyField = 'modu_modulo';

	public function talles()
	{
    	return $this->belongsToMany(Talle::class)->withPivot(['cantidad']);
	}

	public function lineas()
	{
    	return $this->belongsToMany(Linea::class, 'linea_modulo');
	}

	public function pedido_combinaciones()
    {
        return $this->hasMany(Pedido_combinacion::class, 'id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => $this->keyField, 
						'orderBy' => $this->keyField,
						'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Modulo::all();
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
                modu_modulo,
				modu_desc,
				modu_cant_m16,
				modu_cant_m17,
				modu_cant_m18,
				modu_cant_m19,
				modu_cant_m20,
				modu_cant_m21,
				modu_cant_m22,
				modu_cant_m23,
				modu_cant_m24,
				modu_cant_m25,
				modu_cant_m26,
				modu_cant_m27,
				modu_cant_m28,
				modu_cant_m29,
				modu_cant_m30,
				modu_cant_m31,
				modu_cant_m32,
				modu_cant_m33,
				modu_cant_m34,
				modu_cant_m35,
				modu_cant_m36,
				modu_cant_m37,
				modu_cant_m38,
				modu_cant_m39,
				modu_cant_m40,
				modu_cant_m41,
				modu_cant_m42,
				modu_cant_m43,
				modu_cant_m44,
				modu_cant_m45,
				modu_cant_m46,
				modu_cant_m47
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

            $modulo = Modulo::create([
                "nombre" => $data->modu_desc,
                "codigo" => $data->modu_modulo,
            ]);

			for ($_ii = 16; $_ii <= 47; $_ii++)
			{
				$attr = 'modu_cant_m'.$_ii;

				if ($data->{$attr} > 0)
				{
					// Traigo id del talle 
					$talle = Talle::where('nombre', $_ii)->first();

            		$modulo->talles()->attach($talle->id, ['cantidad' => $data->{$attr}]);
				}
			}
        }
    }

	public function guardarAnita($request, $id, $talles, $cantidades) {
        $apiAnita = new ApiAnita();

		$campos = " modu_modulo, modu_desc ";
		for ($talle = 0; $talle < count($talles); $talle++)
			$campos = $campos . ", modu_cant_m".$talles[$talle];

        $modulo = Modulo::select('codigo','id')->where('id',$id)->first();
		if ($modulo)
			$modulo_id = $modulo->id;
		else
			$modulo_id = $id;
		$valores = " '".$modulo_id."', '".$request->nombre."' ";
		for ($talle = 0; $talle < count($talles); $talle++)
			$valores = $valores . ", '".$cantidades[$talle]."'";

		// Agrega campos vacios
		for ($talle = 16; $talle <= 47; $talle++)
		{
            if (!in_array($talle, $talles)) 
			{
				$campos = $campos . ", modu_cant_m".$talle;
				$valores = $valores . ", '0'";
			}
		}

        $data = array( 'tabla' => $this->table, 'acc' => 'insert',
            'campos' => $campos,
            'valores' => $valores
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $talles, $cantidades) {
        $apiAnita = new ApiAnita();

		$valores = " modu_desc = '".$request->nombre."' ";
		for ($talle = 0; $talle < count($talles); $talle++)
			$valores = $valores . ", modu_cant_m".$talles[$talle]." = '".$cantidades[$talle]."'";

		// Agrega campos vacios
		for ($talle = 16; $talle <= 47; $talle++)
		{
            if (!in_array($talle, $talles)) 
			{
				$valores = $valores . ", modu_cant_m".$talle." = '0'";
			}
		}

        $modulo = Modulo::select('codigo','id')->where('id',$id)->first();
		if ($modulo)
			$modulo_id = $modulo->id;
		else
			$modulo_id = $id;

		$data = array( 'acc' => 'update', 
				'tabla' => $this->table,
				'valores' => $valores,
				'whereArmado' => " WHERE modu_modulo = '".$modulo_id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();

        $modulo = Modulo::select('codigo','id')->where('id',$id)->first();
		if ($modulo)
			$modulo_id = $modulo->id;
		else
			$modulo_id = $id;

        $data = array( 'acc' => 'delete', 
			'tabla' => $this->table,
			'whereArmado' => " WHERE modu_modulo = '".$modulo_id."' " );
        $apiAnita->apiCall($data);
	}
}
