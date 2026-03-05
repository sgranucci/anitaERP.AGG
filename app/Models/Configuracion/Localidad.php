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
            Localidad::create([
                "id" => $key,
                "nombre" => $data->loc_desc,
                "codigopostal" => $data->loc_cod_postal,
                "codigo" => $data->loc_localidad,
                "provincia_id" => ($data->loc_provincia > 0 ? $data->loc_provincia : NULL),
                "codigosenasa" => $data->loc_cod_senasa
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->table, 
                        'sistema' => 'shared',
						'acc' => 'insert',
            			'campos' => ' loc_localidad, loc_provincia, loc_desc, loc_cod_postal, loc_cod_senasa',
            			'valores' => " '".$request->codigo."', 
										'".($request->provincia_id == NULL ? 0 : $request->provincia_id)."',
										'".$request->nombre."',  
										'".$request->codigopostal."',
                                        '".$request->codigosenasa."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->table, 
						'valores' => " 
						    loc_provincia = '".($request->provincia_id == NULL ? 0 : $request->provincia_id)."',
							loc_desc = '".$request->nombre."',
                			loc_cod_postal =	'".$request->codigopostal."',
                            loc_cod_senasa = '".$request->codigosenasa."' ",
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
