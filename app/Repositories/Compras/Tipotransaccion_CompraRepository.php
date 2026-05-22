<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tipotransaccion_Compra;
use App\Repositories\Compras\Tipotransaccion_Compra_CentrocostoRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Tipotransaccion_CompraRepository implements Tipotransaccion_CompraRepositoryInterface
{
    protected $model;
    private $tipotransaccion_compra_centrocostoRepository;
    private $tipotransaccion_compra_concepto_ivacompraRepository;
	private $concepto_ivacompraRepository;
	private $centrocostoRepository;
    protected $tableAnita = ['t_comp', 'ccostcomp', 'cont_comp'];
    protected $keyField = 'abreviatura';
    protected $keyFieldAnita = 'tcomp_clave';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipotransaccion_Compra $tipotransaccion,
								Concepto_IvacompraRepositoryInterface $concepto_ivacomprarepository,
								CentrocostoRepositoryInterface $centrocostorepository,
                                Tipotransaccion_Compra_CentrocostoRepositoryInterface $tipotransaccion_compra_centrocostorepository,
                                Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface $tipotransaccion_compra_conceptocomprarepository
                                )
    {
        $this->model = $tipotransaccion;
		$this->concepto_ivacompraRepository = $concepto_ivacomprarepository;
		$this->centrocostoRepository = $centrocostorepository;
        $this->tipotransaccion_compra_centrocostoRepository = $tipotransaccion_compra_centrocostorepository;
        $this->tipotransaccion_compra_concepto_ivacompraRepository = $tipotransaccion_compra_conceptocomprarepository;
    }

    public function all($operacion, $estado = null)
    {
		$hay_tipotransaccion_compra = $this->model->first();

		if (!$hay_tipotransaccion_compra)
			self::sincronizarConAnita();

        $tipotransaccion = $this->model;

        if ($operacion && $operacion != '*')
            $tipotransaccion = $tipotransaccion->wherein('operacion', $operacion);

        if ($estado)
            $tipotransaccion = $tipotransaccion->wherein('estado', $estado);
        
        return $tipotransaccion->get();
    }

    public function create(array $data)
    {
        $tipotransaccion = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);

        return($tipotransaccion);
    }

    public function update(array $data, $id)
    {
        $tipotransaccion = $this->model->findOrFail($id)->update($data);

  		// Actualiza anita
		self::actualizarAnita($data, $data['abreviatura']);

		return $tipotransaccion;
    }

    public function delete($id)
    {
    	$tipotransaccion = $this->model->find($id);

    	// Elimina anita
		if ($tipotransaccion)
            self::eliminarAnita($tipotransaccion->abreviatura);        
        
        $tipotransaccion = $this->model->destroy($id);

		return $tipotransaccion;
    }

    public function find($id)
    {
        if (null == $tipotransaccion = $this->model->with('tipotransaccion_compra_centrocostos')
                                        ->with('tipotransaccion_compra_concepto_ivacompras')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion;
    }

    public function findOrFail($id)
    {
        if (null == $tipotransaccion = $this->model->with('tipotransaccion_compra_centrocostos')
                                        ->with('tipotransaccion_compra_concepto_ivacompras')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion;
    }

    public function findPorAbreviatura($abreviatura)
    {
        return $this->model->with('tipotransaccion_compra_centrocostos')
               	->with('tipotransaccion_compra_concepto_ivacompras')->where('abreviatura', $abreviatura)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Tipotransaccion_Compra::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, true);
            }
			else
			{
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, false);
			}
        }
    }

    private function traerRegistroDeAnita($key, $fl_crea_registro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'compras',
            'campos' => '
				tcomp_clave, 
				tcomp_desc, 
				tcomp_oper, 
				tcomp_refer, 
				tcomp_subdiar, 
				tcomp_oper_stk, 
				tcomp_genera_asi, 
				tcomp_concepto, 
				tcomp_tipo_comp, 
				tcomp_tipo_oper, 
				tcomp_toma_ret, 
				tcomp_estado 
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (isset($dataAnita)) {
            $data = $dataAnita[0];

			if ($data->tcomp_toma_ret == 'T' || $data->tcomp_toma_ret == 'I' || $data->tcomp_toma_ret == 'C')
				$retieneIva = 'S';
			else	
				$retieneIva = 'N';
			
			if ($data->tcomp_toma_ret == 'T' || $data->tcomp_toma_ret == 'G' || $data->tcomp_toma_ret == 'C')
			{
				$retieneGanancia = 'S';
				$retieneIIBB = 'S';
			}
			else
			{
				$retieneGanancia = 'N';
				$retieneIIBB = 'S';
			}

			if ($data->tcomp_tipo_oper == 'M')
				$operacion = 'L';
			else	
				$operacion = 'I';

			$arr_campos = [
				"nombre" => $data->tcomp_desc,
				'operacion' => $operacion, 
				'abreviatura' => $data->tcomp_clave, 
				'codigoafip' => $data->tcomp_tipo_comp, 
				'signo' => $data->tcomp_oper,
				'subdiario' => ($data->tcomp_subdiar == 'N' ? 'N' : 'C'), 
				'asientocontable' => $data->tcomp_genera_asi, 
				'retieneiva' => $retieneIva, 
				'retieneganancia' => $retieneGanancia, 
				'retieneIIBB' => $retieneIIBB, 
				'estado' => $data->tcomp_estado
            	];
	
			if ($fl_crea_registro)
            	$tipotransaccion = $this->model->create($arr_campos);
			else
            	$tipotransaccion = $this->model->findOrFail($data->tcomp_clave)->update($arr_campos);

			// Graba tabla de centros de costo del tipo de transaccion
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'campos' => '
					ccostc_ccosto,
					ccostc_tipo
				',
				'whereArmado' => " WHERE ccostc_tipo = '".$key."' " 
			);
			$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach ($dataAnita as $centrocosto)
			{
				// Busca centro de costo
				$centrocosto = $this->centrocostoRepository->findPorCodigo($centrocosto->ccostc_ccosto);
				if ($centrocosto)
					$centrocosto_id = $centrocosto->id;
				else
					$centrocosto_id = null;
				
				$arr_tipotransaccion_compra_centrocosto = [
					"tipotransaccion_compra_id" => $tipotransaccion->id,
					"centrocosto_id" => $centrocosto_id
				];
				if ($fl_crea_registro)
					$this->tipotransaccion_compra_centrocostoRepository
						->createUnRegistro($arr_tipotransaccion_compra_centrocosto);
			}

			// Graba tabla de conceptos de compras del tipo de transaccion
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'compras',
				'campos' => '
					contc_tipo,
					contc_concepto
				',
				'whereArmado' => " WHERE contc_tipo = '".$key."' " 
			);
			$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach ($dataAnita as $concepto)
			{
				// Busca Concepto de compras
				$concepto_ivacompra = $this->concepto_ivacompraRepository->findPorCodigo($concepto->contc_concepto);
				if ($concepto_ivacompra)
					$concepto_ivacompra_id = $concepto_ivacompra->id;
				else
					$concepto_ivacompra_id = null;
				
				$arr_tipotransaccion_compra_concepto_ivacompra = [
					"tipotransaccion_compra_id" => $tipotransaccion->id,
					"concepto_ivacompra_id" => $concepto_ivacompra_id
				];
				if ($fl_crea_registro)
					$this->tipotransaccion_compra_concepto_ivacompraRepository
						->createUnRegistro($arr_tipotransaccion_compra_concepto_ivacompra);
			}
        }
    }

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$subdiario = ($request['subdiario'] == 'N' ? 'N' : 'C');
		$tipoOperacion = ($request['operacion'] == 'L' ? 'M' : 'I');
		$tomaRetencion = 'T';
		if ($request['retieneiva'] == 'S' && $request['retieneganancia'] == 'S')
			$tomaRetencion = 'T';
		if ($request['retieneiva'] == 'S' && $request['retieneganancia'] == 'N')
			$tomaRetencion = 'I';
		if ($request['retieneiva'] == 'N' && $request['retieneganancia'] == 'S')
			$tomaRetencion = 'G';

        $data = array( 'tabla' => $this->tableAnita[0], 'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => ' 
				tcomp_clave, 
				tcomp_desc, 
				tcomp_oper, 
				tcomp_refer, 
				tcomp_subdiar, 
				tcomp_oper_stk, 
				tcomp_genera_asi, 
				tcomp_concepto, 
				tcomp_tipo_comp, 
				tcomp_tipo_oper, 
				tcomp_toma_ret, 
				tcomp_estado 
				',
            'valores' => " 
				'".$request['abreviatura']."', 
				'".$request['nombre']."',
				'".$request['signo']."',
				'".'000'."',
				'".$subdiario."',
				'".' '."',
				'".$request['asientocontable']."',
				'".'0'."',
				'".$request['codigoafip']."', 
				'".$tipoOperacion."',
				'".$tomaRetencion."',
				'".$request['estado']."' "
        );
        $apiAnita->apiCallEscritura($data);

		// Graba centros de costo
		Self::grabaCentrocosto($request);

		// Graba conceptos de iva
		Self::grabaConcepto_Ivacompra($request);
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$subdiario = ($request['subdiario'] == 'N' ? 'N' : 'C');
		$tipoOperacion = ($request['operacion'] == 'L' ? 'M' : 'I');
		$tomaRetencion = 'T';
		if ($request['retieneiva'] == 'S' && $request['retieneganancia'] == 'S')
			$tomaRetencion = 'T';
		if ($request['retieneiva'] == 'S' && $request['retieneganancia'] == 'N')
			$tomaRetencion = 'I';
		if ($request['retieneiva'] == 'N' && $request['retieneganancia'] == 'S')
			$tomaRetencion = 'G';

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'compras',
				'valores' => " 
					tcomp_clave 	  = '".$request['abreviatura']."',
					tcomp_desc        = '".$request['nombre']."',
					tcomp_oper        = '".$request['signo']."', 
					tcomp_subdiar     = '".$subdiario."', 
					tcomp_genera_asi  = '".$request['asientocontable']."',
					tcomp_tipo_comp   = '".$request['codigoafip']."',
					tcomp_tipo_oper   = '".$tipoOperacion."', 
					tcomp_toma_ret    = '".$tomaRetencion."',
					tcomp_estado      = '".$request['estado']."' "
					,
				'whereArmado' => " WHERE tcomp_clave = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

		// Borra centros de costo
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE ccostc_tipo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

		// Graba centros de costo
		Self::grabaCentrocosto($request);

		// Borra formas de pago
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE contc_tipo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

		// Graba formas de pago
		Self::grabaConcepto_Ivacompra($request);
	}

	private function grabaCentrocosto($request)
	{
		// Graba exclusiones
		if (isset($request['centrocosto_ids']))
		{
			$apiAnita = new ApiAnita();

			$centrocosto_ids = $request['centrocosto_ids'];

			if ($centrocosto_ids[0] != null)
				$qCentrocosto = count($centrocosto_ids);
			else
				$qCentrocosto = 0;

			for ($i_ccosto=0; $i_ccosto < $qCentrocosto; $i_ccosto++) 
			{
				$centrocosto = $this->centrocostoRepository->find($centrocosto_ids[$i_ccosto]);

				$codigoCentroCosto = 0;
				if ($centrocosto)
					$codigoCentroCosto = $centrocosto->codigo;

				$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
						'sistema' => 'compras',
							'campos' => '
							ccostc_ccosto,
							ccostc_tipo
							',
						'valores' => " 
								'".$codigoCentroCosto."', 
								'".$request['abreviatura']."' "
						);
				$anita = $apiAnita->apiCallEscritura($data);
			}
		}
	}

	private function grabaConcepto_Ivacompra($request)
	{
		if (isset($request['concepto_ivacompra_ids']))
		{
			$apiAnita = new ApiAnita();

			// Graba Conceptos
			$concepto_ivacompra_ids = $request['concepto_ivacompra_ids'];

			if ($concepto_ivacompra_ids[0] != null)
				$qConcepto_Ivacompra = count($concepto_ivacompra_ids);
			else
				$qConcepto_Ivacompra = 0;
			for ($i_concepto=0; $i_concepto < $qConcepto_Ivacompra; $i_concepto++) 
			{
				$concepto_ivacompra = $this->concepto_ivacompraRepository->find($concepto_ivacompra_ids[$i_concepto]);

				$codigoConceptoIvaCompra = 0;
				if ($concepto_ivacompra)
					$codigoConceptoIvaCompra = $concepto_ivacompra->codigo;

				$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
				'sistema' => 'compras',
				'campos' => '
						contc_tipo,
						contc_orden,
						contc_concepto
					',
				'valores' => " 
						'".$request['abreviatura']."', 
						'".$i_concepto."',
						'".$codigoConceptoIvaCompra."' "
				);
				$apiAnita->apiCallEscritura($data);
			}
		}
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE tcomp_clave = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

		// Borra centros de costo
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE ccostc_tipo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

		// Borra conceptos de iva compra
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
			'sistema' => 'compras',
			'whereArmado' => " WHERE contc_tipo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
