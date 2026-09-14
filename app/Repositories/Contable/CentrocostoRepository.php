<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use App\Support\Configuracion\EntornoEmpresaSupport;

class CentrocostoRepository implements CentrocostoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'ccosto';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'ccos_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Centrocosto $centrocosto)
    {
        $this->model = $centrocosto;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $centrocosto = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $centrocosto = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $centrocosto;
    }

    public function delete($id)
    {
    	$centrocosto = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($centrocosto->codigo);

        $centrocosto = $this->model->destroy($id);

		return $centrocosto;
    }

    public function find($id)
    {
        if (null == $centrocosto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $centrocosto;
    }

    public function findPorId($id)
    {
        $centrocosto = $this->model->where('id', $id)->first();

        return $centrocosto;
    }

    public function findPorCodigo($codigo)
    {
        $centrocosto = $this->model->where('codigo', $codigo)->first();

        return $centrocosto;
    }

    public function findPorNombre($nombre)
    {
        $centrocosto = $this->model->where('nombre', 'LIKE', '%'.$nombre.'%')->pluck('id')->toArray();

        return $centrocosto;
    }

    public function findOrFail($id)
    {
        if (null == $centrocosto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $centrocosto;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $sinAbreviatura = $this->esquemaCcostoSinAbreviatura();
        $campos = $sinAbreviatura
            ? 'ccos_codigo, ccos_desc, ccos_grupo'
            : 'ccos_codigo, ccos_desc, ccos_grupo, ccos_abreviatura';
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => $this->tableAnita,
            'campos' => $campos,
        ]));
        if ($parsed['error_lectura'] !== null) {
            // Ferli y otros Informix sin ccos_abreviatura: reintentar sin ese campo.
            if (! $sinAbreviatura) {
                $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
                    'acc' => 'list',
                    'sistema' => 'contab',
                    'tabla' => $this->tableAnita,
                    'campos' => 'ccos_codigo, ccos_desc, ccos_grupo',
                ]));
                $sinAbreviatura = true;
            }
        }
        if ($parsed['error_lectura'] !== null) {
            return;
        }

        $datosLocalArray = Centrocosto::query()->pluck($this->keyField)->map(
            fn ($c) => ltrim((string) $c, '0')
        )->all();

        foreach ($parsed['filas'] as $value) {
            $codigo = ltrim((string) ($value->ccos_codigo ?? ''), '0');
            if ($codigo === '' || in_array($codigo, $datosLocalArray, true)) {
                continue;
            }
            $desc = (string) ($value->ccos_desc ?? '');
            $abreviatura = $sinAbreviatura
                ? substr($desc, 0, 5)
                : (string) ($value->ccos_abreviatura ?? substr($desc, 0, 5));

            $this->model->create([
                'nombre' => $desc,
                'codigo' => (string) ($value->ccos_codigo ?? $codigo),
                'abreviatura' => $abreviatura,
            ]);
            $datosLocalArray[] = $codigo;
        }
    }

    public function traerRegistroDeAnita($key){
        if ($key === null || $key === '') {
            return;
        }

        $apiAnita = new ApiAnita();
        $sinAbreviatura = $this->esquemaCcostoSinAbreviatura();
        $campos = $sinAbreviatura
            ? 'ccos_codigo, ccos_desc, ccos_grupo'
            : 'ccos_codigo, ccos_desc, ccos_grupo, ccos_abreviatura';

        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'sistema' => 'contab',
            'campos' => $campos,
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
        ]));
        if ($parsed['error_lectura'] !== null && ! $sinAbreviatura) {
            $sinAbreviatura = true;
            $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
                'acc' => 'list',
                'tabla' => $this->tableAnita,
                'sistema' => 'contab',
                'campos' => 'ccos_codigo, ccos_desc, ccos_grupo',
                'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
            ]));
        }
        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            return;
        }

        $data = $parsed['filas'][0];
        $desc = (string) ($data->ccos_desc ?? '');
        $abreviatura = $sinAbreviatura
            ? substr($desc, 0, 5)
            : (string) ($data->ccos_abreviatura ?? substr($desc, 0, 5));

        $this->model->create([
            'nombre' => $desc,
            'codigo' => $data->ccos_codigo,
            'abreviatura' => $abreviatura,
        ]);
    }

    private function esquemaCcostoSinAbreviatura(): bool
    {
        return EntornoEmpresaSupport::esElBierzo()
            || EntornoEmpresaSupport::esInterforming()
            || EntornoEmpresaSupport::esFerli();
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        if ($this->esquemaCcostoSinAbreviatura())
            $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
                'sistema' => 'contab',
                'campos' => ' 
                    ccos_codigo,
                    ccos_desc,
                    ccos_grupo
                    ',
                'valores' => " 
                    '".$request['codigo']."', 
                    '".$request['nombre']."',
                    '0' "
            );
        else
            $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
                'sistema' => 'contab',
                'campos' => ' 
                    ccos_codigo,
                    ccos_desc,
                    ccos_grupo,
                    ccos_abreviatura
                    ',
                'valores' => " 
                    '".$request['codigo']."', 
                    '".$request['nombre']."',
                    '0',
                    '".$request['abreviatura']."' "
            );            
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if ($this->esquemaCcostoSinAbreviatura())
            $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                    'sistema' => 'contab',
                    'valores' => " 
                    ccos_codigo 	                = '".$request['codigo']."',
                    ccos_desc 	                    = '".$request['nombre']."',
                    ccos_grupo 	                    = '0'"
                        ,
                    'whereArmado' => " WHERE ccos_codigo = '".$id."' " );
        else
            $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                    'sistema' => 'contab',
                    'valores' => " 
                    ccos_codigo 	                = '".$request['codigo']."',
                    ccos_desc 	                    = '".$request['nombre']."',
                    ccos_grupo 	                    = '0',
                    ccos_abreviatura 	            = '".$request['abreviatura']."' "
                        ,
                    'whereArmado' => " WHERE ccos_codigo = '".$id."' " );            
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'contab',
				'whereArmado' => " WHERE ccos_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
