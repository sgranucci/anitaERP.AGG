<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencionganancia_Escala;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use App\Support\Compras\Retencion\AnitaRetencionEsquemaSupport;

class RetenciongananciaRepository implements RetenciongananciaRepositoryInterface
{
    protected $model, $model_Escala;
    protected $tableAnita = ['retencion','escala'];
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'ret_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencionganancia $retencionganancia, Retencionganancia_Escala $retencionganancia_escala)
    {
        $this->model = $retencionganancia;
        $this->model_Escala = $retencionganancia_escala;
    }

    public function all()
    {
        $retencionesganancia = $this->model->with("retencionganancia_escalas")->get();

		if ($retencionesganancia->isEmpty())
		{
        	self::sincronizarConAnita();

			$retencionesganancia = $this->model->with("retencionganancia_escalas")->get();
		}
		return $retencionesganancia;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $retencionganancia = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $retencionganancia = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $retencionganancia;
    }

    public function delete($id)
    {
    	$retencionganancia = $this->model->find($id);
		$codigo = $retencionganancia->codigo;

        $retencionganancia = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $retencionganancia;
    }

    public function find($id)
    {
        if (null == $retencionganancia = $this->model->with("retencionganancia_escalas")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionganancia;
    }

	public function findPorId($id)
    {
		$retencionganancia = $this->model->where('id', $id)->first();

		return $retencionganancia;
    }

	public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $retencionganancia = $this->model->with("retencionganancia_escalas")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionganancia;
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita)) {
            return;
        }
        $datosLocal = $this->model->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
		
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'compras',
            'campos' => $this->camposCabeceraAnita(),
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return;
        }

            $data = $dataAnita[0];

        	$datamov = array( 
            	'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita[1], 
            	'campos' => '
                	esc_nro_linea,
					esc_desde,
					esc_hasta,
					esc_retencion,
					esc_porc_ret,
					esc_codigo
            	' , 
            	'whereArmado' => " WHERE esc_codigo = '".$key."' " 
        	);
        	$dataAnitamov = json_decode($apiAnita->apiCall($datamov));
            if (! is_array($dataAnitamov)) {
                $dataAnitamov = [];
            }

			// Crea registro 
            $retencionganancia = $this->model->create([
                'id' => $key,
                'nombre' => $data->ret_desc,
				'codigo' => $data->ret_codigo,
				'regimen' => $data->ret_cod_regimen,
				'formacalculo' => $data->ret_toma_acum,
				'porcentajeinscripto' => $data->ret_porc_insc,
				'porcentajenoinscripto' => $data->ret_porc_no_insc,
				'montoexcedente' => $data->ret_excedente,
				'minimoretencion' => $data->ret_minimo_ret,
				'baseimponible' => AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()
                    ? ($data->ret_base ?? 0)
                    : 0,
				'cantidadperiodoacumula' => AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()
                    ? ($data->ret_cant_per ?? 0)
                    : 0,
				'valorunitario' => AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()
                    ? ($data->ret_valor_unit ?? 0)
                    : 0,
            ]);

			if ($retencionganancia)
			{
				for ($i = 0; $i < count($dataAnitamov); $i++)
				{
					if ($i == 0)
						$excedente = 0;
					else
						$excedente = $dataAnitamov[$i-1]->esc_desde;

        			$retencionganancia_escala = $this->model_Escala->create([
            											'retencionganancia_id' => $retencionganancia->id,
            											'desdemonto' => $dataAnitamov[$i]->esc_desde,
														'hastamonto' => $dataAnitamov[$i]->esc_hasta,
														'montoretencion' => $dataAnitamov[$i]->esc_retencion,
														'porcentajeretencion' => $dataAnitamov[$i]->esc_porc_ret,
														'excedente' => $excedente
														]);
				}
			}
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

        $campos = [
            'ret_codigo',
            'ret_desc',
            'ret_porc_insc',
            'ret_porc_no_insc',
            'ret_excedente',
            'ret_cod_regimen',
            'ret_toma_acum',
            'ret_minimo_ret',
        ];
        $valores = [
            "'".$request['codigo']."'",
            "'".$request['nombre']."'",
            "'".$request['porcentajeinscripto']."'",
            "'".$request['porcentajenoinscripto']."'",
            "'".$request['montoexcedente']."'",
            "'".$request['regimen']."'",
            "'".$request['formacalculo']."'",
            "'".$request['minimoretencion']."'",
        ];
        if (AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()) {
            $campos[] = 'ret_base';
            $campos[] = 'ret_cant_per';
            $campos[] = 'ret_valor_unit';
            $valores[] = "'".$request['baseimponible']."'";
            $valores[] = "'".$request['cantidadperiodoacumula']."'";
            $valores[] = "'".$request['valorunitario']."'";
        }

        $data = array( 'tabla' => $this->tableAnita[0], 
			'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => implode(",\n", $campos),
            'valores' => implode(",\n", $valores),
        );
        $apiAnita->apiCallEscritura($data);

		$desdeMontos = $request['desdemontos'];
		$hastaMontos = $request['hastamontos'];
		$montoRetenciones = $request['montoretenciones'];
		$porcentajeRetenciones = $request['porcentajeretenciones'];

    	for ($i_rango=0; $i_rango < count($desdeMontos); $i_rango++) 
		{
			if ($hastaMontos[$i_rango] > 0)
			{
				$apiAnita = new ApiAnita();

				$data = array( 'tabla' => $this->tableAnita[1], 
					'acc' => 'insert',
					'sistema' => 'compras',
					'campos' => '
							esc_nro_linea,
							esc_desde,
							esc_hasta,
							esc_retencion,
							esc_porc_ret,
							esc_codigo
							',
					'valores' => " 
							'".$i_rango."', 
							'".$desdeMontos[$i_rango]."' ,
							'".$hastaMontos[$i_rango]."' ,
							'".$montoRetenciones[$i_rango]."' ,
							'".$porcentajeRetenciones[$i_rango]."' ,
							'".$request['codigo']."' "
					);

				$apiAnita->apiCallEscritura($data);
			}
		}
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

        $valores = "
							ret_codigo = '".$request['codigo']."', 
							ret_desc = '".$request['nombre']."',
							ret_porc_insc = '".$request['porcentajeinscripto']."',
							ret_porc_no_insc = '".$request['porcentajenoinscripto']."',
							ret_excedente = '".$request['montoexcedente']."',
							ret_cod_regimen = '".$request['regimen']."',
							ret_toma_acum = '".$request['formacalculo']."',
							ret_minimo_ret = '".$request['minimoretencion']."'";
        if (AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()) {
            $valores .= ",
							ret_base = '".$request['baseimponible']."',
							ret_cant_per = '".$request['cantidadperiodoacumula']."',
							ret_valor_unit = '".$request['valorunitario']."'";
        }

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita[0],
				'sistema' => 'compras',
            	'valores' => $valores, 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " 
				);
        $apiAnita->apiCallEscritura($data);

		// Elimina los movimientos
        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita[1],
			'sistema' => 'compras',
            'whereArmado' => " WHERE esc_codigo = '".$request['codigo']."' " );
        $apiAnita->apiCallEscritura($data);

		$desdeMontos = $request['desdemontos'];
		$hastaMontos = $request['hastamontos'];
		$montoRetenciones = $request['montoretenciones'];
		$porcentajeRetenciones = $request['porcentajeretenciones'];

		// Graba los movimientos
    	for ($i_rango=0; $i_rango < count($desdeMontos); $i_rango++) 
		{
			if ($hastaMontos[$i_rango] > 0)
			{
				$data = array( 'tabla' => $this->tableAnita[1], 
					'acc' => 'insert',
					'sistema' => 'compras',
					'campos' => '
							esc_nro_linea,
							esc_desde,
							esc_hasta,
							esc_retencion,
							esc_porc_ret,
							esc_codigo
							',
					'valores' => " 
							'".$i_rango."', 
							'".$desdeMontos[$i_rango]."' ,
							'".$hastaMontos[$i_rango]."' ,
							'".$montoRetenciones[$i_rango]."' ,
							'".$porcentajeRetenciones[$i_rango]."' ,
							'".$request['codigo']."' "
					);
				$apiAnita->apiCallEscritura($data);
			}
		}
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita[0],
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita[1],
            'whereArmado' => " WHERE esc_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}    

		// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita[0], 
				'campos' => " max(ret_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (is_array($dataAnita) && count($dataAnita) > 0
            && isset($dataAnita[0]->{$this->keyFieldAnita})
            && $dataAnita[0]->{$this->keyFieldAnita} !== null)
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
		else	
			$codigo = 1;
	}

    /**
     * Campos de cabecera Anita `retencion`.
     * Ferli no tiene ret_base / ret_cant_per / ret_valor_unit.
     */
    private function camposCabeceraAnita(): string
    {
        $campos = [
            'ret_codigo',
            'ret_desc',
            'ret_porc_insc',
            'ret_porc_no_insc',
            'ret_excedente',
            'ret_cod_regimen',
            'ret_toma_acum',
            'ret_minimo_ret',
        ];
        if (AnitaRetencionEsquemaSupport::retencionIncluyeBasePeriodoValor()) {
            $campos[] = 'ret_base';
            $campos[] = 'ret_cant_per';
            $campos[] = 'ret_valor_unit';
        }

        return implode(",\n", $campos);
    }
		
}
