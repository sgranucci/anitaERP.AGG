<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Copiaot extends Model
{
    protected $fillable = ['nombre'];
    protected $keyField = 'copi_codigo';

    public static function traeCopia($tipoEmision)
	{
        $apiAnita = new ApiAnita();

		// Define codigo de copia
		$copia = 11;
		switch($tipoEmision)
		{
		case 'COMPLETA': // codigo_copia 11
			$copia = 11;
			break;
		case 'STOCK':    // codigo_copia 14
			$copia = 14;
			break;
		case 'CAJA':     // codigo_copia 12
			$copia = 12;
			break;
		}

        $data = array( 
            'acc' => 'list', 'tabla' => 'copiaot',
            'campos' => '
                copi_codigo,
				copi_desc,
				copi_imprime_cp1,
				copi_titulo_cp1,
				copi_imprime_cp2,
				copi_titulo_cp2,
				copi_imprime_cp3,
				copi_titulo_cp3,
				copi_imprime_cp4,
				copi_titulo_cp4,
				copi_imprime_cp5,
				copi_titulo_cp5,
				copi_imprime_cp6,
				copi_titulo_cp6,
				copi_imprime_cp7,
				copi_titulo_cp7,
				copi_imprime_cp8,
				copi_titulo_cp8,
				copi_imprime_cp9,
				copi_titulo_cp9,
				copi_imprime_cp10,
				copi_titulo_cp10
            ' , 
            'whereArmado' => " WHERE copi_codigo = '".$copia."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$titulo = [];
        if ($dataAnita) {
			$titulo[] = $dataAnita[0]->copi_titulo_cp1;
			$titulo[] = $dataAnita[0]->copi_titulo_cp2;
			$titulo[] = $dataAnita[0]->copi_titulo_cp3;
			$titulo[] = $dataAnita[0]->copi_titulo_cp4;
			$titulo[] = $dataAnita[0]->copi_titulo_cp5;
			$titulo[] = $dataAnita[0]->copi_titulo_cp6;
			$titulo[] = $dataAnita[0]->copi_titulo_cp7;
			$titulo[] = $dataAnita[0]->copi_titulo_cp8;
			$titulo[] = $dataAnita[0]->copi_titulo_cp9;
			$titulo[] = $dataAnita[0]->copi_titulo_cp10;
        }
		return $titulo;
    }
}
