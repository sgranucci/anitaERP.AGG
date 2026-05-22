<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\ApiAnita;
use App\Models\Ventas\Condicionventacuota;
use Carbon\Carbon;

class Condicionventa extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'condicionventa';
    protected $tableAnita = ['condmae','condmov'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'conm_codigo';

	public function condicionventacuotas()
	{
    	return $this->hasMany(Condicionventacuota::class);
	}

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'ventas',
						'campos' => $this->keyFieldAnita, 
						'orderBy' => $this->keyFieldAnita,
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Condicionventa::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $codigo = trim((string) ($value->{$this->keyField} ?? ''));
            if ($codigo !== '') {
                $datosLocalArray[] = $codigo;
            }
        }

        foreach ($dataAnita as $value) {
            $codigoAnita = trim((string) ($value->{$this->keyFieldAnita} ?? ''));
            if ($codigoAnita !== '' && !in_array($codigoAnita, $datosLocalArray, true)) {
                $this->traerRegistroDeAnita($codigoAnita);
            }
        }

        $this->actualizarCodigosLocalesDesdeAnita();
    }

    /**
     * Asigna conm_codigo a registros locales existentes (por codigo, id legacy o nombre).
     */
    public function actualizarCodigosLocalesDesdeAnita(): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita[0],
            'campos' => 'conm_codigo, conm_desc',
            'orderBy' => 'conm_codigo',
        ];
        $filas = json_decode($apiAnita->apiCall($data));
        if (! is_array($filas)) {
            return;
        }

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->conm_codigo ?? ''));
            if ($codigo === '') {
                continue;
            }

            $nombre = trim((string) ($fila->conm_desc ?? ''));

            $condicion = Condicionventa::where('codigo', $codigo)->first();
            if (! $condicion && ctype_digit($codigo)) {
                $condicion = Condicionventa::find((int) $codigo);
            }
            if (! $condicion && $nombre !== '') {
                $condicion = Condicionventa::where('nombre', $nombre)->first();
            }

            if ($condicion && trim((string) $condicion->codigo) !== $codigo) {
                $condicion->update(['codigo' => $codigo]);
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

        $key = trim((string) $key);
        if ($key === '') {
            return;
        }

        if (Condicionventa::where('codigo', $key)->exists()) {
            return;
        }

        $legacy = Condicionventa::find((int) $key);
        if ($legacy) {
            $legacy->update(['codigo' => $key]);
            return;
        }

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'ventas',
            'campos' => '
                conm_codigo,
				conm_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
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

			$condicionventa = Condicionventa::create([
                "nombre" => $data->conm_desc,
                "codigo" => $key,
            ]);

			if ($condicionventa)
			{
				foreach ($dataAnitamov as $cuota)
				{
					$nrocuota = $cuota->conv_nro_cuota + 1;
					$tipoplazo = $colTipoPlazo->where('id', $cuota->conv_tipo_plazo);
        			Condicionventacuota::create([
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

	public function guardarAnita($request, $codigo, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();
        $codigo = trim((string) $codigo);

        $data = array( 'tabla' => $this->tableAnita[0], 
			'acc' => 'insert',
			'sistema' => 'ventas',
            'campos' => 'conm_codigo, conm_desc',
            'valores' => " 
						'".$codigo."', 
						'".$request->nombre."' "
        );
        $apiAnita->apiCallEscritura($data);

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
						'".$codigo."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
        	$apiAnita->apiCallEscritura($data);
		}
	}

	public function actualizarAnita($request, $codigo, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {
	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();
        $codigo = trim((string) $codigo);

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita[0],
				'sistema' => 'ventas',
            	'valores' => " 
							conm_codigo = '".$codigo."', 
							conm_desc = '".$request->nombre."' ", 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$codigo."' " 
				);
        $apiAnita->apiCallEscritura($data);

        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita[1],
			'sistema' => 'ventas',
            'whereArmado' => " WHERE conv_codigo = '".$codigo."' " );
        $apiAnita->apiCallEscritura($data);

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
						'".$codigo."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
        	$apiAnita->apiCallEscritura($data);
		}
	}

	public function eliminarAnita($codigo) {
        $apiAnita = new ApiAnita();
        $codigo = trim((string) $codigo);

        $data = array( 'acc' => 'delete', 
			'sistema' => 'ventas',
			'tabla' => $this->tableAnita[0],
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$codigo."' " );
        $apiAnita->apiCallEscritura($data);

        $data = array( 'acc' => 'delete', 
			'sistema' => 'ventas',
			'tabla' => $this->tableAnita[1],
            'whereArmado' => " WHERE conv_codigo = '".$codigo."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
