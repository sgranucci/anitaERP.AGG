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

        $codigosLocales = self::query()->pluck('codigo')->map(fn ($c) => (string) $c)->all();

		if ($dataAnita)
		{
        	foreach ($dataAnita as $value) {
                $codigo = (string) $value->{$this->keyFieldAnita};
            	if (! in_array($codigo, $codigosLocales, true)) {
                	$this->traerRegistroDeAnita($codigo);
            	}
        	}
		}
    }

    /**
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public function resincronizarConAnita(): array
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'shared',
            'campos' => $this->camposListadoAnita(),
            'orderBy' => $this->keyFieldAnita,
            'tabla' => $this->table,
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $stats = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        if (! is_array($dataAnita)) {
            return $stats;
        }

        foreach ($dataAnita as $value) {
            $resultado = $this->upsertDesdeFilaAnita($value);
            if ($resultado === 'insertado') {
                $stats['insertados']++;
            } elseif ($resultado === 'actualizado') {
                $stats['actualizados']++;
            } else {
                $stats['omitidos']++;
            }
        }

        return $stats;
    }

    private function camposListadoAnita(): string
    {
        if (config('app.empresa') == 'INTERFORMING' ||
            config('app.empresa') == 'FRASLE') {
            return 'loc_localidad, loc_provincia, loc_desc';
        }

        return 'loc_localidad, loc_provincia, loc_desc, loc_cod_postal, loc_cod_senasa';
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'sistema' => 'shared',
            'campos' => $this->camposListadoAnita(),
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return null;
        }

        return $this->upsertDesdeFilaAnita($dataAnita[0], $key);
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    private function upsertDesdeFilaAnita(object $data, ?string $key = null): ?string
    {
        $key = $key ?? (string) ($data->loc_localidad ?? '');
        if ($key === '') {
            return null;
        }

        $provincia = Provincia::select('id')->where('codigo', $data->loc_provincia)->first();
        $provincia_id = $provincia?->id;

        if (config('app.empresa') == 'INTERFORMING' ||
            config('app.empresa') == 'FRASLE') {
            $payload = [
                'nombre' => $data->loc_desc,
                'codigopostal' => null,
                'codigo' => $data->loc_localidad,
                'provincia_id' => $provincia_id,
                'codigosenasa' => null,
            ];
        } else {
            $payload = [
                'nombre' => $data->loc_desc,
                'codigopostal' => $data->loc_cod_postal,
                'codigo' => $data->loc_localidad,
                'provincia_id' => $provincia_id,
                'codigosenasa' => $data->loc_cod_senasa,
            ];
        }

        $existente = self::query()
            ->where('codigo', (string) $data->loc_localidad)
            ->orWhere('id', $key)
            ->first();

        if ($existente) {
            $existente->update($payload);

            return 'actualizado';
        }

        self::create(array_merge(['id' => $key], $payload));

        return 'insertado';
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
        $apiAnita->apiCallEscritura($data);
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
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->table,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request->codigo."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
