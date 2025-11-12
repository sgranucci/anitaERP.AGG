<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\Stock\DepmaeTrait;
use App\ApiAnita;

class Depmae extends Model
{
    use DepmaeTrait;

    protected $fillable = ['nombre', 'tipodeposito', 'codigo'];
    protected $table = 'depmae';
    protected $keyField = 'depm_deposito';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'sistmea' => 'ventas', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Depmae::all();
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

	public function traerRegistroDeAnita($key)
	{
        $apiAnita = new ApiAnita();
		if (config('app.empresa') == 'Calzados Ferli' ||
	    	config('app.empresa') == 'EL BIERZO')
        	$data = array( 
            	'acc' => 'list', 'tabla' => $this->table, 
            	'campos' => '
                	depm_deposito,
                	depm_desc,
					depm_maneja_part,
					depm_cta_contable
            	' , 
            	'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        	);
		else
        	$data = array( 
            	'acc' => 'list', 'tabla' => $this->table, 
            	'campos' => '
                	depm_deposito,
                	depm_desc,
					depm_maneja_part,
					depm_tipo_deposito
            	' , 
            	'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        	);

        $dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			if (config('app.empresa') == 'AGG')
            	$tipoDeposito = array_search($data->depm_tipo_deposito, 
                	array_column(Depmae::$enumTipoDeposito, 'valor', 'nombre'));
	   		else 
	    		$tipoDeposito = 'N';

            Depmae::create([
                "nombre" => $data->depm_desc,
                "tipodeposito" => $tipoDeposito,
                "codigo" => $key
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

	if (config('app.empresa') == 'Calzados Ferli' ||
	    config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => 'depmae', 'acc' => 'insert',
                'campos' => ' depm_deposito, depm_desc, depm_maneja_part, depm_cta_contable ',
                'valores' => " '".$id."', '".$request->nombre."', 'S', 0"
            );
        else
        {
            $tipoDeposito = array_search($request->tipodeposito, 
                array_column(Depmae::$enumTipoDeposito, 'nombre', 'valor'));

            $data = array( 'tabla' => 'depmae', 'acc' => 'insert',
                'campos' => ' depm_deposito, depm_desc, depm_maneja_part, depm_tipo_deposito ',
                'valores' => " '".$request->codigo."', '".$request->nombre."', 'S', '".$tipoDeposito."' "
            );
        }
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if (config('app.empresa') == 'Calzados Ferli' ||
	    	config('app.empresa') == 'EL BIERZO')
            $data = array( 'acc' => 'update', 'tabla' => 'depmae', 'valores' => " depm_desc = '".
                        $request->nombre."' ", 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        else
        {
            $tipoDeposito = array_search($request->tipodeposito, 
                array_column(Depmae::$enumTipoDeposito, 'nombre', 'valor'));

            $data = array( 'acc' => 'update', 'tabla' => 'depmae', 'valores' => 
                        " depm_desc = '".$request->nombre."',
                          depm_tipo_deposito = '".$tipoDeposito."' ", 
                        'whereArmado' => " WHERE depm_deposito = '".$request->codigo."' " );            
        }
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'Calzados Ferli' ||
	    	config('app.empresa') == 'EL BIERZO')
            $data = array( 'acc' => 'delete', 'tabla' => 'depmae', 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        else
            $data = array( 'acc' => 'delete', 'tabla' => 'depmae', 'whereArmado' => " WHERE depm_deposito = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
