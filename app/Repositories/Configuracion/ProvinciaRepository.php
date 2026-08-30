<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Provincia;
use App\Support\Configuracion\ProvinciaListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ProvinciaRepository implements ProvinciaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'provincia';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'provi_provincia';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provincia $provincia)
    {
        $this->model = $provincia;
    }

    public function all()
    {
        $hay_provincia = Provincia::first();

        if (!$hay_provincia)
			self::sincronizarConAnita();

        return $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                    ->with('provincia_cuentacontableiibbs')->orderBy('nombre','ASC')->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Provincia>
     */
    public function leeProvincia($filtros, bool $paginar = false)
    {
        $hay_provincia = Provincia::first();
        if (! $hay_provincia) {
            self::sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ProvinciaListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ProvinciaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('provincia.*')
            ->leftJoin('pais', 'pais.id', '=', 'provincia.pais_id')
            ->with([
                'paises:id,nombre',
                'provincia_tasaiibbs',
                'provincia_cuentacontableiibbs.empresas:id,nombre',
                'provincia_cuentacontableiibbs.cuentacontables:id,codigo,nombre',
            ]);

        if (ProvinciaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ProvinciaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('provincia.nombre', 'ASC');

        return $paginar
            ? $query->paginate(10)->appends(ProvinciaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        $provincia = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $data['codigo']);

        return $provincia;
    }

    public function update(array $data, $id)
    {
        $provincia = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $provincia;
    }

    public function delete($id)
    {
    	$provincia = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($provincia->codigo);

        $provincia = $this->model->destroy($id);

		return $provincia;
    }

    public function find($id)
    {
        if (null == $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                        ->with('provincia_cuentacontableiibbs')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia;
    }

    public function findPorId($id)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                            ->with('provincia_cuentacontableiibbs')->where('id', $id)->first();

        return $provincia;
    }

    public function findPorCodigo($codigo)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                    ->with('provincia_cuentacontableiibbs')->where('codigo', $codigo)->first();

        return $provincia;
    }

    public function findPorJurisdiccion($jurisdiccion)
    {
        $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                    ->with('provincia_cuentacontableiibbs')->where('jurisdiccion', $jurisdiccion)->first();

        return $provincia;
    }

    public function findOrFail($id)
    {
        if (null == $provincia = $this->model->with('paises:id,nombre')->with('provincia_tasaiibbs')
                                                ->with('provincia_cuentacontableiibbs')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'shared',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $codigosLocales = Provincia::query()->pluck('codigo')->map(fn ($c) => (string) $c)->all();

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
            'tabla' => $this->tableAnita,
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
        return match (config('app.empresa')) {
            'EL BIERZO' => 'provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion, provi_cod_externo',
            'AGG', 'FRASLE' => 'provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion, provi_letra',
            default => 'provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion',
        };
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'sistema' => 'shared',
            'campos' => $this->camposListadoAnita(),
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        
        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return null;
        }

        return $this->upsertDesdeFilaAnita($dataAnita[0]);
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    private function upsertDesdeFilaAnita(object $data): ?string
    {
        $codigo = (string) ($data->provi_provincia ?? '');
        if ($codigo === '') {
            return null;
        }

        if (config('app.empresa') == 'EL BIERZO') {
            $codigoExterno = $data->provi_cod_externo;
        } else {
            $codigoExterno = $data->provi_provincia;
        }

        $payload = [
            'nombre' => $data->provi_desc,
            'abreviatura' => $data->provi_abrev,
            'jurisdiccion' => $data->provi_jurisdiccion,
            'codigo' => $data->provi_provincia,
            'pais_id' => 1,
            'codigoexterno' => $codigoExterno,
        ];

        $existente = Provincia::query()
            ->where('codigo', $codigo)
            ->orWhere('id', $data->provi_provincia)
            ->first();

        if ($existente) {
            $existente->update($payload);

            return 'actualizado';
        }

        Provincia::create(array_merge(['id' => $data->provi_provincia], $payload));

        return 'insertado';
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if (config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion, provi_cod_externo',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
                                        '".$request['jurisdiccion']."',
										'".$request['codigoexterno']."' "
            );
        else
            $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' provi_provincia, provi_desc, provi_abrev, provi_jurisdiccion',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
										'".$request['jurisdiccion']."' "
            );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        if (config('app.empresa') == 'EL BIERZO')
		    $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" provi_desc = '".$request['nombre']."',
							provi_abrev = '".$request['abreviatura']."',
                            provi_jurisdiccion = '".$request['jurisdiccion']."',
							provi_cod_externo = '".$request['codigoexterno']."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );
        else
            $data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" provi_desc = '".$request['nombre']."',
							provi_abrev = '".$request['abreviatura']."',
							provi_jurisdiccion = '".$request['jurisdiccion']."' ",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );            
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->tableAnita,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " );
        $apiAnita->apiCallEscritura($data);
	}

    public function consultaProvincia($consulta)
    {
        ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $columns = ['provincia.id', 'provincia.nombre', 'provincia.codigo', 'provincia.jurisdiccion'];
        $columnsOut = ['id', 'nombre', 'codigo', 'jurisdiccion'];   

		$consulta = strtoupper($consulta);

		$count = count($columns);
        $data = $this->model->select('provincia.id as id',
                                'provincia.nombre as nombre',
                                'provincia.codigo as codigo',
                                'provincia.jurisdiccion as jurisdiccion')
                        ->where(function ($query) use ($count, $consulta, $columns) {
                                for ($i = 0; $i < $count; $i++)
                                    $query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
            })	
            ->get();	
    
        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaprovincia">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }

}
