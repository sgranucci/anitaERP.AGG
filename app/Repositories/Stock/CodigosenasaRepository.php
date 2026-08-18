<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Codigosenasa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class CodigosenasaRepository implements CodigosenasaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'codsenasa';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'cods_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Codigosenasa $codigosenasa)
    {
        $this->model = $codigosenasa;
    }

    public function all()
    {
        $hay_codigosenasas = Codigosenasa::first();

		if (!$hay_codigosenasas)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $codigosenasa = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);

        return $codigosenasa;
    }

    public function update(array $data, $id)
    {
        $codigosenasa = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $codigosenasa;
    }

    public function delete($id)
    {
    	$codigosenasa = Codigosenasa::find($id);
		//
		// Elimina anita
		self::eliminarAnita($codigosenasa->codigo);

        $codigosenasa = $this->model->destroy($id);

		return $codigosenasa;
    }

    public function find($id)
    {
        if (null == $codigosenasa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $codigosenasa;
    }

    public function findOrFail($id)
    {
        if (null == $codigosenasa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $codigosenasa;
    }

    /** Evita listar Anita más de una vez por request (certificado sanitario). */
    private static bool $sincronizadoEsteRequest = false;

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        if (self::$sincronizadoEsteRequest) {
            return;
        }
        self::$sincronizadoEsteRequest = true;

        $apiAnita = new ApiAnita();
        $dataAnita = json_decode($apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => 'cods_codigo,cods_desc,cods_registro,cods_envase,cods_frio,cods_prefijo',
            'whereArmado' => ' WHERE 1=1 ',
            'orderBy' => 'cods_codigo',
            'limit' => 'FIRST 300',
        ]));
        if (! is_array($dataAnita) || $dataAnita === []) {
            return;
        }

        $locales = Codigosenasa::query()->get()->keyBy(function (Codigosenasa $row) {
            return (string) (int) $row->codigo;
        });

        foreach ($dataAnita as $value) {
            $attrs = $this->atributosDesdeAnita($value);
            if ($attrs === null) {
                continue;
            }
            $clave = (string) (int) $attrs['codigo'];
            $local = $locales->get($clave);
            if (! $local) {
                $this->model->create($attrs);
                continue;
            }
            if ($this->necesitaActualizarDesdeAnita($local, $attrs)) {
                $local->fill([
                    'nombre' => $attrs['nombre'],
                    'registro' => $attrs['registro'],
                    'envasesenasa_id' => $attrs['envasesenasa_id'],
                    'llevafrio' => $attrs['llevafrio'],
                    'prefijo' => $attrs['prefijo'],
                ]);
                $local->save();
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
		// Formato El Bierzo
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'ventas',
            'campos' => '
			cods_codigo,
    		cods_desc,
            cods_registro,
            cods_envase,
            cods_frio,
            cods_prefijo
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (is_array($dataAnita) && count($dataAnita) > 0) {
            $attrs = $this->atributosDesdeAnita($dataAnita[0]);
            if ($attrs !== null) {
                $this->model->create($attrs);
            }
        }
    }

    /**
     * @return array{nombre: string, registro: string, envasesenasa_id: int|null, llevafrio: string, prefijo: string, codigo: string}|null
     */
    private function atributosDesdeAnita(object|array $data): ?array
    {
        $data = (object) $data;
        $codigo = trim((string) ($data->cods_codigo ?? ''));
        $nombre = trim((string) ($data->cods_desc ?? ''));
        $registro = trim((string) ($data->cods_registro ?? ''));
        $prefijo = trim((string) ($data->cods_prefijo ?? ''));
        if ($codigo === '' || ($nombre === '' && $registro === '' && $prefijo === '')) {
            return null;
        }

        $envase = (int) ($data->cods_envase ?? 0);

        return [
            'nombre' => $nombre !== '' ? $nombre : $codigo,
            'registro' => $registro,
            'envasesenasa_id' => $envase > 0 ? $envase : null,
            'llevafrio' => Codigosenasa::codigoFrio((string) ($data->cods_frio ?? 'N')) === 'S'
                ? 'Lleva Frio'
                : 'No Lleva Frio',
            'prefijo' => $prefijo,
            'codigo' => $codigo,
        ];
    }

    /**
     * @param  array{nombre: string, registro: string, envasesenasa_id: int|null, llevafrio: string, prefijo: string, codigo: string}  $attrs
     */
    private function necesitaActualizarDesdeAnita(Codigosenasa $local, array $attrs): bool
    {
        return trim((string) $local->registro) !== $attrs['registro']
            || trim((string) $local->prefijo) !== $attrs['prefijo']
            || Codigosenasa::codigoFrio((string) $local->llevafrio) !== Codigosenasa::codigoFrio($attrs['llevafrio'])
            || (int) ($local->envasesenasa_id ?? 0) !== (int) ($attrs['envasesenasa_id'] ?? 0)
            || trim((string) $local->nombre) !== $attrs['nombre'];
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        if ($request['llevafrio'] == 'Lleva Frio')
            $frio = 'S';
        else
            $frio = 'N';

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				cods_codigo,
    			cods_desc,
                cods_registro,
                cods_envase,
                cods_frio,
                cods_prefijo
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
                '".$request['envasesenasa_id']."',
                '".$frio."',
                '".$request['prefijo']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if ($request['llevafrio'] == 'Lleva Frio')
            $frio = 'S';
        else
            $frio = 'N';

        $data = array( 'acc' => 'update', 
                'sistema' => 'ventas', 
                'tabla' => $this->tableAnita, 
				'valores' => " 
                cods_codigo    = '".$request['codigo']."',
                cods_desc 	   = '".$request['nombre']."',
                cods_registro  = '".$request['registro']."',
                cods_envase    = '".$request['envasesenasa_id']."',
                cods_frio      = '".$frio."',
                cods_prefijo   = '".$request['prefijo']."' "
                ,
				'whereArmado' => " WHERE cods_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE cods_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
