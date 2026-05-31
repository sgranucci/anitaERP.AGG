<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Localidad_Uif;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use App\ApiAnita;

class Localidad_UifRepository implements Localidad_UifRepositoryInterface
{
    protected $model;
    protected $table = 'localidad';
    protected $keyFieldAnita = 'loc_localidad';
    protected $provincia_uifRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Localidad_Uif $localidad_uif,
                                Provincia_UifRepositoryInterface $provincia_uifrepository)
    {
        $this->model = $localidad_uif;
        $this->provincia_uifRepository = $provincia_uifrepository;
    }

    public function all()
    {
        $localidad_uif = $this->model->with('provincias')->get();

        return $localidad_uif;
    }

    public function create(array $data)
    {
        $localidad_uif = $this->model->create($data);

        return($localidad_uif);
    }

    public function update(array $data, $id)
    {
        $localidad_uif = $this->model->findOrFail($id)->update($data);

		return $localidad_uif;
    }

    public function delete($id)
    {
    	$localidad_uif = $this->model->find($id);

        $localidad_uif = $this->model->destroy($id);

		return $localidad_uif;
    }

    public function find($id)
    {
        if (null == $localidad_uif = $this->model->with('provincias')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad_uif;
    }

    public function findOrFail($id)
    {
        if (null == $localidad_uif = $this->model->with('provincias')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad_uif;
    }

    public function findPorCodigo($codigo)
    {
        if (null == $localidad_uif = $this->model->where('codigo', $codigo)->with('provincias')->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad_uif;
    }

	public function leerLocalidades($id)
    {
        return $this->model->select('id','nombre')->where('provincia_uif_id',$id)->orderBy('nombre','asc')->get()->toArray();
    }

    public function leerCodigoPostal($id)
    {
        $cp = $this->model->select('codigopostal')->where('id',$id)->get();
        return $cp[0]->codigopostal;
    }    

    public function sincronizarConAnita()
    {
        $apiAnita = new ApiAnita();
        $data = ['acc' => 'list',
            'sistema' => 'base_admin',
            'campos' => $this->keyFieldAnita,
            'orderBy' => $this->keyFieldAnita,
            'tabla' => $this->table];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            return;
        }

        $codigosLocales = $this->model->newQuery()->pluck('codigo')->map(fn ($c) => (string) $c)->all();

        foreach ($dataAnita as $value) {
            $codigo = (string) $value->{$this->keyFieldAnita};
            if (! in_array($codigo, $codigosLocales, true)) {
                $this->traerRegistroDeAnita($codigo);
            }
        }
    }

    /**
     * @return array{insertados: int, actualizados: int, eliminados: int, omitidos_con_clientes: int}
     */
    public function resincronizarConAnita(): array
    {
        $apiAnita = new ApiAnita();
        $data = ['acc' => 'list',
            'sistema' => 'base_admin',
            'campos' => $this->keyFieldAnita,
            'orderBy' => $this->keyFieldAnita,
            'tabla' => $this->table];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $stats = [
            'insertados' => 0,
            'actualizados' => 0,
            'eliminados' => 0,
            'omitidos_con_clientes' => 0,
        ];

        if (! is_array($dataAnita)) {
            return $stats;
        }

        $codigosAnita = [];
        foreach ($dataAnita as $value) {
            $codigo = (string) $value->{$this->keyFieldAnita};
            $codigosAnita[] = $codigo;
            $resultado = $this->traerRegistroDeAnita($codigo);
            if ($resultado === 'insertado') {
                $stats['insertados']++;
            } elseif ($resultado === 'actualizado') {
                $stats['actualizados']++;
            }
        }

        $codigosAnita = array_flip($codigosAnita);
        $obsoletas = $this->model->newQuery()->get(['id', 'codigo']);

        foreach ($obsoletas as $localidad) {
            $codigo = (string) $localidad->codigo;
            if (isset($codigosAnita[$codigo])) {
                continue;
            }

            $tieneClientes = Cliente_Uif::query()
                ->where('localidad_uif_id', $localidad->id)
                ->orWhere('localidadnacimiento_id', $localidad->id)
                ->exists();

            if ($tieneClientes) {
                $stats['omitidos_con_clientes']++;

                continue;
            }

            $this->model->newQuery()->whereKey($localidad->id)->delete();
            $stats['eliminados']++;
        }

        return $stats;
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public function traerRegistroDeAnita($key)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list', 'tabla' => $this->table,
            'sistema' => 'base_admin',
            'campos' => '
                loc_localidad,
                loc_desc,
				loc_cod_postal,
				loc_partido,
				loc_provincia,
				loc_cod_part
            ',
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            if ($data->loc_provincia == 2) {
                $provincia_uif_id = 2;
            } elseif ($data->loc_provincia == 1) {
                $provincia_uif_id = 1;
            } else {
                $provincia_uif_id = null;
            }

            $payload = [
                'nombre' => $data->loc_desc,
                'codigopostal' => $data->loc_cod_postal,
                'codigo' => (string) $data->loc_localidad,
                'provincia_uif_id' => $provincia_uif_id,
            ];

            $existente = $this->model->newQuery()->where('codigo', $payload['codigo'])->first();
            if ($existente !== null) {
                $existente->update($payload);

                return 'actualizado';
            }

            $this->model->create($payload);

            return 'insertado';
        }

        return null;
    }

    public function leeLocalidad_Uif($consulta, $localidad_uif_id = null)
    {
		$columns = ['localidad_uif.id', 'localidad_uif.nombre', 'provincia_uif.nombre', 'localidad_uif.codigopostal', 
                    'localidad_uif.codigo'];
        $columnsOut = ['id', 'nombre', 'nombreprovincia', 'codigopostal', 'codigoanita'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('localidad_uif.id as id',
									'localidad_uif.nombre as nombre',
                                    'provincia_uif.nombre as nombreprovincia',
									'localidad_uif.codigopostal as codigopostal',
                                    'localidad_uif.codigo as codigoanita')
                            ->leftjoin('provincia_uif', 'provincia_uif.id', '=', 'localidad_uif.provincia_id');

        if (isset($localidad_uif_id))
            $data = $data->where('localidad_uif.id', $localidad_uif_id);

		$data = $data->Where(function ($query) use ($count, $consulta, $columns) {
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultalocalidad_uif">Elegir</a></td>';
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
