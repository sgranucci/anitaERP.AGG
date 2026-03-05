<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor_Concepto;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Precarga_Comprobante_Proveedor_ConceptoRepository implements Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'precargaconc';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'precc_id';
    protected $concepto_ivacompraRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Precarga_Comprobante_Proveedor_Concepto $precarga_comprobante_proveedor_concepto,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository)
    {
        $this->model = $precarga_comprobante_proveedor_concepto;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $precarga_comprobante_proveedor_concepto = $this->model->create($data);
		//
		// Graba anita
		Self::guardarAnita($data, $data['precarga_comprobante_proveedor_id']);
    }

    public function update(array $data, $id)
    {
        $precarga_comprobante_proveedor_concepto = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		Self::actualizarAnita($data, $id);

		return $precarga_comprobante_proveedor_concepto;
    }

    public function delete($id)
    {
    	$precarga_comprobante_proveedor_concepto = Precarga_Comprobante_Proveedor_Concepto::find($id);
		//
		// Elimina anita
		Self::eliminarAnita($precarga_comprobante_proveedor_concepto->id);

        $precarga_comprobante_proveedor_concepto = $this->model->destroy($id);

		return $precarga_comprobante_proveedor_concepto;
    }

    public function deletePorPrecargaComprobanteProveedor($id)
    {
        $precarga_comprobante_proveedor_concepto = Precarga_Comprobante_Proveedor_Concepto::where('precarga_comprobante_proveedor_id', $id)->delete();

        Self::eliminarAnitaPorPrecargaComprobanteProveedor($id);
    }

    public function find($id)
    {
        if (null == $precarga_comprobante_proveedor_concepto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $precarga_comprobante_proveedor_concepto;
    }

    public function findOrFail($id)
    {
        if (null == $precarga_comprobante_proveedor_concepto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $precarga_comprobante_proveedor_concepto;
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        // Numera con ultimo id
        $idConcepto = Self::traeUltimoIdConcepto();

        // Busca el codigo de anita del concepto
        $concepto_ivacompra = $this->concepto_ivacompraRepository->find($request['concepto_ivacompra_id']);

        $codigoConcepto = 0;
        if ($concepto_ivacompra)
            $codigoConcepto = $concepto_ivacompra->codigo;

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
				precc_id,
				precc_precarga_id,
                precc_concepto,
                precc_monto
				',
            'valores' => " 
				'".$idConcepto."', 
                '".$id."', 
                '".$codigoConcepto."', 
                '".$request['monto']."' "
        );
        $apiAnita->apiCall($data);
	}

    public function traeUltimoIdConcepto()
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $grabaAnita = array( 
            'acc' => 'list', 
			'tabla' => $this->tableAnita, 
            'campos' => '
                max(precc_id) as id
			' 
        );
        $dataAnita = json_decode($apiAnita->apiCall($grabaAnita));
        
        if ($dataAnita)
        {
            if (count($dataAnita) > 0)
                $nro = $dataAnita[0]->id + 1;

            if (!isset($nro))
                return 'error';
        }
        else
            $nro = 1;
        
        return $nro;
    }

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                        precc_precarga_id 	            = '".$id."',
                        precc_concepto 	               	= '".$request['concepto']."',
                        precc_monto 	               	= '".$request['montoconcepto']."' "
					,
				'whereArmado' => " WHERE precc_id = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE precc_id = '".$id."' " );
        $apiAnita->apiCall($data);
	}

    public function eliminarAnitaPorPrecargaComprobanteProveedor($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE precc_precarga_id = '".$id."' " );
        $apiAnita->apiCall($data);
    }
}
