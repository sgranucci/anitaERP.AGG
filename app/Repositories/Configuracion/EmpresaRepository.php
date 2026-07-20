<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\ApiAnita;
use Auth;

class EmpresaRepository implements EmpresaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'emprmae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'empm_empresa';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Empresa $empresa)
    {
        $this->model = $empresa;
    }

    public function all()
    {
        $hay_empresa = Empresa::first();

        if (!$hay_empresa)
			self::sincronizarConAnita();

        return $this->model->with(['pais:id,nombre', 'provincia:id,nombre', 'localidad:id,nombre'])
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function traeEmpresasAsignadas()
    {
        // Extrae las empresas asignadas
        return collect(Session::get('usuario_empresas'))->pluck('id')->toArray();
    }

    /**
     * Restringe un query a las empresas asignadas al usuario en sesión.
     * Sin empresas asignadas (acceso total): no aplica filtro.
     */
    public function aplicarFiltroEmpresasAsignadas($query, string $column = 'empresa_id', bool $incluirNull = false): void
    {
        $empresasAsignadas = $this->traeEmpresasAsignadas();

        if (count($empresasAsignadas) >= 1) {
            if ($incluirNull) {
                $query->where(function ($q) use ($empresasAsignadas, $column) {
                    $q->whereIn($column, $empresasAsignadas)
                        ->orWhereNull($column);
                });
            } else {
                $query->whereIn($column, $empresasAsignadas);
            }
        }
    }

    public function empresaIdPermitida(int $empresaId): bool
    {
        $asignadas = $this->traeEmpresasAsignadas();

        if (count($asignadas) === 0) {
            return true;
        }

        return in_array($empresaId, $asignadas, true);
    }

    public function allFiltrado()
    {
        // Extrae las empresas asignadas
        $empresas = collect(Session::get('usuario_empresas'))->pluck('id')->toArray();

        if (count($empresas) >= 1) {
            $empresa = $this->model->whereIn('id', $empresas)->orderBy('id', 'ASC')->get();
        } else {
            $empresa = $this->model->orderBy('id', 'ASC')->get();
        }

        return $empresa;
    }

    /**
     * Empresas "activas" en AGG: aparecen en usuario_empresa, filtradas por sesión del operador.
     */
    public function empresasActivasOperativas()
    {
        $idsActivos = DB::table('usuario_empresa')
            ->distinct()
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($idsActivos === []) {
            return collect();
        }

        $query = $this->model->whereIn('id', $idsActivos)->orderBy('id', 'ASC');
        $this->aplicarFiltroEmpresasAsignadas($query, 'id');

        return $query->get();
    }

    public function empresaTieneUsuariosAsignados(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        return DB::table('usuario_empresa')
            ->where('empresa_id', $empresaId)
            ->exists();
    }

    public function create(array $data)
    {
        $empresa = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $empresa = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $empresa;
    }

    public function delete($id)
    {
    	$empresa = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($empresa->codigo);

        $empresa = $this->model->destroy($id);

		return $empresa;
    }

    public function find($id)
    {
        if (null == $empresa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empresa;
    }

    public function findPorId($id)
    {
        $empresa = $this->model->where('id', $id)->first();

        return $empresa;
    }

    public function findPorCodigo($codigo)
    {
        $empresa = $this->model->where('codigo', $codigo)->first();

        return $empresa;
    }

    public function findPorDocumento($nroinscripcion)
    {
        $empresa = $this->model->where('nroinscripcion', $nroinscripcion)->get();

        return $empresa;
    }

    public function findOrFail($id)
    {
        if (null == $empresa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empresa;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'sistema' => 'contab',
                        'campos' => $this->keyFieldAnita, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Empresa::all();
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
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'contab',
            'campos' => '
                empm_empresa,
				empm_nombre,
				empm_direccion,
				empm_localidad,
				empm_provincia,
				empm_cod_postal,
				empm_cuit,
				empm_ult_depura,
				empm_mes_inicio,
				empm_ejer_anio
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Empresa::create([
                "id" => $key,
                "nombre" => $data->empm_nombre,
                "domicilio" => $data->empm_direccion,
                "nroinscripcion" => $data->empm_cuit,
				"codigo" => $data->empm_empresa
            ]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'contab',
            			'campos' => ' 
                                empm_empresa, 
                                empm_nombre, 
                                empm_direccion, 
                                empm_localidad, 
                                empm_provincia, 
                                empm_cod_postal, 
                                empm_cuit, 
                                empm_ult_depura, 
                                empm_mes_inicio, 
                                empm_ejer_anio',
            			'valores' => " 
                                '".$request['codigo']."', 
                                '".$request['nombre']."', 
                                '".$request['domicilio']."', 
                                '".' '."', 
                                '".' '."', 
                                '".'0'."', 
                                '".$request['nroinscripcion']."', 
                                '".'0'."', 
                                ".'0'.", 
                                '".'0'."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
						'tabla' => $this->tableAnita, 
                        'sistema' => 'contab',
						'valores' => " 
                            empm_nombre = '".$request['nombre']."', 
                            empm_direccion = '".$request['domicilio']."', 
                            empm_cuit = '".$request['nroinscripcion']."' ", 
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita,
                    'sistema' => 'contab',
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
