<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Linea;
use App\Models\Ventas\Puntoventa;

class Categoria extends Model
{
    protected $fillable = ['nombre', 'codigo', 'copiaot', 'tipoarticulo_id',
                            'division', 'estado', 'grupocompra', 'linea_id', 'deposito_id', 
                            'puntoventa_id', 'excel'];
    protected $table = 'categoria';
    protected $tableAnita = 'stkagr';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'stka_agrupacion';

    public function tipoarticulo()
    {
        return $this->belongsTo(Tipoarticulo::class, 'tipoarticulo_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyFieldAnita, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Categoria::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyFieldAnita}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();

        switch(config('app.empresa'))
        {
            case 'FRASLE':
                $data = array( 
                    'acc' => 'list', 'tabla' => $this->tableAnita, 
                    'campos' => '
                        stka_agrupacion,
                        stka_desc,
                        stka_division,
                        stka_estado,
                        stka_grupocom,
                        stka_linea,
                        stka_deposito,
                        stka_sucursal,
                        stka_excel
                    ' , 
                    'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
                );
                break;

            default:
                $data = array( 
                    'acc' => 'list', 'tabla' => $this->tableAnita, 
                    'campos' => '
                        stka_agrupacion,
                        stka_desc
                    ' , 
                    'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
                );
                break;
        }
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$codigo = ltrim($data->stka_agrupacion, '0');

            if (config('app.empresa') != 'FRASLE')
                Categoria::create([
                    "nombre" => $data->stka_desc,
                    "codigo" => $codigo,
                    "copiaot" => '',
                    "tipoarticulo_id" => 1
                ]);
            else
            {
                $linea = Linea::select('id')->where('codigo', ltrim($data->stka_linea, '0'))->first();

                $linea_id = null;
                if ($linea)
                    $linea_id = $linea->id;

                $deposito = Depmae::select('id')->where('codigo', $data->stka_deposito)->first();

                $deposito_id = null;
                if ($deposito)
                    $deposito_id = $deposito->id;

                $puntoventa = Puntoventa::select('id')->where('codigo', $data->stka_sucursal)->first();

                $puntoventa_id = null;
                if ($puntoventa)
                    $puntoventa_id = $puntoventa->id;

                Categoria::create([
                    "nombre" => $data->stka_desc,
                    "codigo" => $codigo,
                    "copiaot" => '',
                    "tipoarticulo_id" => 1,
                    "division" => $data->stka_division,
                    "estado" => $data->stka_estado,
                    "grupocompra" => $data->stka_grupocom,
                    "linea_id" => $linea_id,
                    "deposito_id" => $deposito_id,
                    "puntoventa_id" => $puntoventa_id,
                    "excel" => $data->stka_excel
                ]);
            }
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		// Traigo id del tipo de articulo 
		if ($request->tipoarticulo_id == 2)
			$tipoarticulo = 'B';
		else
			$tipoarticulo = 'Z';

		$codigo = str_pad($request->codigo, 4, "0", STR_PAD_LEFT);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' stka_agrupacion, stka_desc',
            'valores' => " '".$codigo."', '".$request->nombre."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		// Traigo id del tipo de articulo 
		if ($request->tipoarticulo_id == 2)
			$tipoarticulo = 'B';
		else
			$tipoarticulo = 'Z';

		$codigo = str_pad($request->codigo, 4, "0", STR_PAD_LEFT);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'update',
					'valores' => " stka_desc = '".  $request->nombre."'", 
					'whereArmado' => " WHERE stka_agrupacion = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita, 
			'whereArmado' => " WHERE stka_id = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
