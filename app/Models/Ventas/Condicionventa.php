<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\ApiAnita;
use App\Models\Ventas\Condicionventacuota;
use Carbon\Carbon;

class Condicionventa extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'condicionventa';
    protected $tableAnita = ['condmae','condmov'];
    protected $keyField = 'conm_codigo';

	public function condicionventacuotas()
	{
    	return $this->hasMany(Condicionventacuota::class);
	}

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'ventas',
						'campos' => $this->keyField, 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Condicionventa::all();
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

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'ventas',
            'campos' => '
                conm_codigo,
				conm_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

        	$datamov = array( 
            	'acc' => 'list', 
				'sistema' => 'ventas',
				'tabla' => $this->tableAnita[1], 
            	'campos' => '
                	conv_codigo,
					conv_nro_cuota,
					conv_tipo_plazo,
					conv_dia,
					conv_fecha_vto,
					conv_porc_monto,
					conv_porc_interes
            	' , 
            	'whereArmado' => " WHERE conv_codigo = '".$key."' " 
        	);
        	$dataAnitamov = json_decode($apiAnita->apiCall($datamov));

			// Crea registro 
            $condicionventa = Condicionventa::create([
                "id" => $key,
                "nombre" => $data->conm_desc
            ]);

			if ($condicionventa)
			{
				foreach ($dataAnitamov as $cuota)
				{
					$nrocuota = $cuota->conv_nro_cuota + 1;
					$tipoplazo = $colTipoPlazo->where('id', $cuota->conv_tipo_plazo);
        			$condicionventacuota = Condicionventacuota::create([
            											'condicionventa_id' => $condicionventa->id,
            											'cuota' => $nrocuota,
														'tipoplazo' => $tipoplazo[0]['valor'],
														'plazo' => $cuota->conv_dia, 
														'fechavencimiento' => ($cuota->conv_fecha_vto == 0 ? NULL : $cuota->conv_fecha_vto),
														'porcentaje' => ($cuota->conv_porc_monto == 0 ? NULL : $cuota->conv_porc_monto),
														'interes' => ($cuota->conv_porc_interes == 0 ? NULL : $cuota->conv_porc_interes),
														]);
				}
			}
        }
    }

	public function guardarAnita($request, $id, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita[0], 
			'acc' => 'insert',
			'sistema' => 'ventas',
            'campos' => 'conm_codigo, conm_desc',
            'valores' => " 
						'".$id."', 
						'".$request->nombre."' "
        );
        $apiAnita->apiCall($data);

    	for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) 
		{
			$tipoplazo = $colTipoPlazo->where('valor', $tiposplazo[$i_cuota])->first();
			$fecha = 0;
			if ($tipoplazo['valor'] == 'F')
				$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota])->format('Ymd');

        	$data = array( 'tabla' => $this->tableAnita[1], 
				'acc' => 'insert',
				'sistema' => 'ventas',
            	'campos' => 'conv_codigo, conv_nro_cuota, conv_tipo_plazo, conv_dia, conv_fecha_vto, conv_porc_monto, conv_porc_interes',
            	'valores' => " 
						'".$id."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
		}
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {
	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita[0],
				'sistema' => 'ventas',
            	'valores' => " 
							conm_codigo = '".$id."', 
							conm_desc = '".$request->nombre."' ", 
            	'whereArmado' => " WHERE ".$this->keyField." = '".$id."' " 
				);
        $apiAnita->apiCall($data);

		// Elimina los movimientos
        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita[1],
			'sistema' => 'ventas',
            'whereArmado' => " WHERE conv_codigo = '".$id."' " );
        $apiAnita->apiCall($data);

		// Graba los movimientos
    	for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) 
		{
			$tipoplazo = $colTipoPlazo->where('valor', $tiposplazo[$i_cuota])->first();
			$fecha = 0;
			if ($tipoplazo['valor'] == 'F')
				$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota])->format('Ymd');

        	$data = array( 'tabla' => $this->tableAnita[1], 
				'acc' => 'insert',
				'sistema' => 'ventas',
            	'campos' => 'conv_codigo, conv_nro_cuota, conv_tipo_plazo, conv_dia, conv_fecha_vto, conv_porc_monto, conv_porc_interes',
            	'valores' => " 
						'".$id."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
        	$apiAnita->apiCall($data);
		}
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'ventas',
			'tabla' => $this->tableAnita[0],
            'whereArmado' => " WHERE ".$this->keyField." = '".$id."' " );
        $apiAnita->apiCall($data);

        $data = array( 'acc' => 'delete', 
			'sistema' => 'ventas',
			'tabla' => $this->tableAnita[1],
            'whereArmado' => " WHERE conv_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
