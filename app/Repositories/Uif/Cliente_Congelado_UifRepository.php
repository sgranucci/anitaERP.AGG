<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Congelado_Uif;
use App\Repositories\Uif\Cliente_Congelado_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Cliente_Congelado_UifRepository implements Cliente_Congelado_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Congelado_Uif $cliente_congelado_uif)
    {
        $this->model = $cliente_congelado_uif;
    }

    public function all()
    {
        $cliente_congelado_uif = $this->model->get();

        return $cliente_congelado_uif;
    }

    public function create(array $data)
    {
        $data['usuario_id'] = Auth::user()->id;

        $cliente_congelado_uif = $this->model->create($data);

        return($cliente_congelado_uif);
    }

    public function update(array $data, $id)
    {
        $data['usuario_id'] = Auth::user()->id;

        $cliente_congelado_uif = $this->model->findOrFail($id)->update($data);

		return $cliente_congelado_uif;
    }

    public function delete($id)
    {
    	$cliente_congelado_uif = $this->model->find($id);

        $cliente_congelado_uif = $this->model->destroy($id);

		return $cliente_congelado_uif;
    }

    public function find($id)
    {
        if (null == $cliente_congelado_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_congelado_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_congelado_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_congelado_uif;
    }

    public function leeCliente_Congelado_Uif($consulta)
    {
		$columns = ['cliente_congelado_uif.id', 'cliente_congelado_uif.nombre', 'cliente_congelado_uif.numerodocumento', 'cliente_congelado_uif.resolucion', 'cliente_congelado_uif.fechacaducidad'];
        $columnsOut = ['id', 'nombre', 'documento', 'resolucion', 'fechacaducidad'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('cliente_congelado_uif.id as id',
									'cliente_congelado_uif.nombre as nombre',
                                    'cliente_congelado_uif.numerodocumento as documento',
									'cliente_congelado_uif.resolucion as resolucion',
                                    'cliente_congelado_uif.fechacaducidad as fechacaducidad');

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacliente_congelado_uif">Elegir</a></td>';
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

    // Busca cliente congelado por nombre + numerodocumento + resolucion + fechacaducidad

    public function buscaCliente_Congelado_Uif($nombre, $numerodocumento, $resolucion, $fechacaducidad)
    {
        return $this->model->select('id')->where('nombre', $nombre)->where('numerodocumento', $numerodocumento)
                        ->where('resolucion', $resolucion)->where('fechacaducidad', $fechacaducidad)->get();
    }
}
