<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Localidad extends Model
{
    protected $fillable = ['nombre', 'codigopostal', 'codigo', 'provincia_id', 'codigosenasa'];
    protected $table = 'localidad';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'loc_localidad';

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'shared',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Localidad::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

		if ($dataAnita)
		{
        	foreach ($dataAnita as $value) {
            	if (!in_array($value->{$this->keyFieldAnita}, $datosLocalArray)) {
                	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            	}
        	}
		}
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'INTERFORMING' ||
            config('app.empresa') == 'FRASLE')
            $data = array( 
                'acc' => 'list', 'tabla' => $this->table, 
                'sistema' => 'shared',
                'campos' => '
                    loc_localidad,
                    loc_provincia,
                    loc_desc
                ' , 
                'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
            );
        else
            $data = array( 
                'acc' => 'list', 'tabla' => $this->table, 
                'sistema' => 'shared',
                'campos' => '
                    loc_localidad,
                    loc_provincia,
                    loc_desc,
                    loc_cod_postal,
                    loc_cod_senasa
                ' , 
                'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
            );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            // Busca el codigo de provincia
            $provincia = Provincia::select('id')->where('codigo', $data->loc_provincia)->first();

            $provincia_id = null;
            if ($provincia)
                $provincia_id = $provincia->id;
            if (config('app.empresa') == 'INTERFORMING' ||
                config('app.empresa') == 'FRASLE')
                Localidad::create([
                    "id" => $key,
                    "nombre" => $data->loc_desc,
                    "codigopostal" => null, 
                    "codigo" => $data->loc_localidad,
                    "provincia_id" => $provincia_id,
                    "codigosenasa" => null
                ]);
            else
                Localidad::create([
                    "id" => $key,
                    "nombre" => $data->loc_desc,
                    "codigopostal" => $data->loc_cod_postal,
                    "codigo" => $data->loc_localidad,
                    "provincia_id" => $provincia_id,
                    "codigosenasa" => $data->loc_cod_senasa
                ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $provincia = Provincia::select('codigo')->where('id', $request->provincia_id)->first();

        $codigoprovincia = '0';
        if ($provincia)
            $codigoprovincia = $provincia->codigo;

        if (config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => $this->table, 
                        'sistema' => 'shared',
						'acc' => 'insert',
            			'campos' => ' loc_localidad, loc_provincia, loc_desc, loc_cod_postal, loc_cod_senasa',
            			'valores' => " '".$request->codigo."', 
										'".$codigoprovincia."',
										'".$request->nombre."',  
										'".$request->codigopostal."',
                                        '".$request->codigosenasa."' "
            );
        else
            $data = array( 'tabla' => $this->table, 
                        'sistema' => 'shared',
						'acc' => 'insert',
            			'campos' => ' loc_localidad, loc_provincia, loc_desc',
            			'valores' => " '".$request->codigo."', 
										'".$codigoprovincia."',
										'".$request->nombre."' "
            );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $provincia = Provincia::select('codigo')->where('id', $request->provincia_id)->first();

        $codigoprovincia = '0';
        if ($provincia)
            $codigoprovincia = $provincia->codigo;

        if (config('app.empresa') == 'EL BIERZO')
		    $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->table, 
						'valores' => " 
						    loc_provincia = '".$codigoprovincia."',
							loc_desc = '".$request->nombre."',
                			loc_cod_postal =	'".$request->codigopostal."',
                            loc_cod_senasa = '".$request->codigosenasa."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request->codigo."' " );
        else
		    $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->table, 
						'valores' => " 
						    loc_provincia = '".$codigoprovincia."',
							loc_desc = '".$request->nombre."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request->codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->table,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request->codigo."' " );
        $apiAnita->apiCall($data);
	}
}
