<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Reserva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class ReservaRepository implements ReservaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'reserva';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'rese_reserva';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Reserva $reserva)
    {
        $this->model = $reserva;
    }

    public function all()
    {
        #$hay_reserva = Reserva::first();

        #if (!$hay_reserva)
			#self::sincronizarConAnita();
        
        $fecha = Carbon::now();
        $fecha = $fecha->format('Ymd');

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'receptivo',
            'campos' => '
                rese_reserva as id,
                rese_fecha_arribo as fechaarribo,
                rese_fecha_partida as fechapartida,
                rese_pasajero as nombrepasajero,
                (select resepa_pasajero from resepax where resepa_reserva=rese_reserva
                and resepa_orden=0) as pasajero_id
            ' , 
            'whereArmado' => " WHERE rese_fecha_partida > ".$fecha." and rese_estado != 'A'",
            'orderBy' => "rese_fecha_arribo desc" 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        #return $this->model->orderBy('nombre','ASC')->get();

        return $dataAnita;
    }

    public function leeReserva($consulta)
    {
        $columns = ['rese_reserva', 'rese_fecha_arribo', 'rese_fecha_partida', 'rese_pasajero', 'pasajero_id'];
        $columnsOut = ['id', 'fechaarribo', 'fechapartida', 'nombrepasajero', 'pasajero_id'];

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'receptivo',
            'campos' => '
                rese_reserva as id,
                rese_fecha_arribo as fechaarribo,
                rese_fecha_partida as fechapartida,
                rese_pasajero as nombrepasajero,
                (select resepa_pasajero from resepax where resepa_reserva=rese_reserva
                and resepa_orden=0) as pasajero_id
            ' , 
            'whereArmado' => " WHERE (rese_pasajero like '%".$consulta."%' ".
                                    (is_numeric($consulta) ? " or rese_fecha_arribo = ".$consulta." " : "").
                                    (is_numeric($consulta) ? " or rese_fecha_partida = ".$consulta." " : "").
                                    (is_numeric($consulta) ? " or rese_reserva = ".$consulta.") " : ")").
                                    "and rese_fecha_arribo > 20250100",
            'orderBy' => "rese_fecha_arribo desc" 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($dataAnita) > 0)
		{
			foreach ($dataAnita as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultareserva">Elegir</a></td>';
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

    public function create(array $data)
    {
        $reserva = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $data['codigo']);
    }

    public function update(array $data, $id)
    {
        $reserva = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $reserva;
    }

    public function delete($id)
    {
    	$reserva = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($reserva->codigo);

        $reserva = $this->model->destroy($id);

		return $reserva;
    }

    public function find($id)
    {
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'receptivo',
            'campos' => '
                rese_reserva as reserva_id,
                rese_reserva as id,
                rese_fecha_arribo as fechaarribo,
                rese_fecha_partida as fechapartida,
                rese_cant_pasajero as cantidadpasajero,
                rese_cant_gratis as cantidadgratis,
                rese_pasajero as nombrepasajero,
                (select resepa_pasajero from resepax where resepa_reserva=rese_reserva
                and resepa_orden=0) as pasajero_id,
                0 as cantidadincluido,
                0 as cantidadopcional
            ' , 
            'whereArmado' => " WHERE rese_reserva = $id and rese_estado != 'A'" 
        );
        $reserva = json_decode($apiAnita->apiCall($data));

        #if (null == $reserva = $this->model->find($id)) {
        #    throw new ModelNotFoundException("Registro no encontrado");
        #}

        return $reserva;
    }

    // Busca reserva por id y filtra servicio
    public function leeReservaPorIdServicioTerrestre($reserva_id, $servicioterrestre_id)
    {
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'receptivo',
            'campos' => "
                rese_reserva as reserva_id,
                rese_reserva as id,
                rese_fecha_arribo as fechaarribo,
                rese_fecha_partida as fechapartida,
                rese_cant_pasajero as cantidadpasajero,
                rese_cant_gratis as cantidadgratis,
                rese_pasajero as nombrepasajero,
                (select resepa_pasajero from resepax where resepa_reserva=rese_reserva
                and resepa_orden=0) as pasajero_id,
                (select sum(reses_pax) from reseserv where reses_reserva=rese_reserva
                and reses_tipo_serv='".'T'."' and reses_servicio=$servicioterrestre_id) as cantidadincluido,
                (select sum(reses_pax) from reseserv where reses_reserva=rese_reserva
                and reses_tipo_serv='".'A'."' and reses_servicio=$servicioterrestre_id) as cantidadopcional
            " , 
            'whereArmado' => " WHERE rese_reserva = $reserva_id and rese_estado != 'A'" 
        );
        $reserva = json_decode($apiAnita->apiCall($data));

        #if (null == $reserva = $this->model->find($id)) {
        #    throw new ModelNotFoundException("Registro no encontrado");
        #}

        return $reserva;
    }

    public function findPorId($id)
    {
        $reserva = $this->model->where('id', $id)->first();

        return $reserva;
    }

    public function findPorCodigo($codigo)
    {
        $reserva = $this->model->where('codigo', $codigo)->first();

        return $reserva;
    }

    public function findOrFail($id)
    {
        if (null == $reserva = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $reserva;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'shared',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Reserva::all();
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
            'sistema' => 'shared',
            'campos' => '
                rese_reserva,
				rese_fecha_alta,
				rese_fecha_arribo
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Reserva::create([
                "id" => $key,
                "nombre" => $data->mon_desc,
                "abreviatura" => $data->mon_abreviatura,
                "codigo" => $data->mon_codigo
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' mon_codigo, mon_desc, mon_abreviatura',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
										'".$request['codigo']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" mon_desc = '".$request['nombre']."',
							mon_abreviatura = '".$request['abreviatura']."',
                			mon_codigo =	'".$request['codigo']."'",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->tableAnita,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
