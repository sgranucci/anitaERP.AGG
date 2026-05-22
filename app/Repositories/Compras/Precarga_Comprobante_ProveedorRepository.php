<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Precarga_Comprobante_ProveedorRepository implements Precarga_Comprobante_ProveedorRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'precarga';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'prec_id';
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Precarga_Comprobante_Proveedor $precarga_comprobante_proveedor,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $precarga_comprobante_proveedor;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        return $this->model->orderBy('id','desc')->get();
    }

    public function create(array $data)
    {
        if ($data['pararevisar'] == 'PARA REVISAR')
            $data['pararevisar'] = 1;
        else
            $data['pararevisar'] = 0;
        
        $precarga_comprobante_proveedor = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $precarga_comprobante_proveedor->id);

        return $precarga_comprobante_proveedor;
    }

    public function update(array $data, $id)
    {
        if ($data['pararevisar'] == 'PARA REVISAR')
            $data['pararevisar'] = 1;
        else
            $data['pararevisar'] = 0;

        $precarga_comprobante_proveedor = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $precarga_comprobante_proveedor;
    }

    public function delete($id)
    {
    	$precarga_comprobante_proveedor = Precarga_Comprobante_Proveedor::find($id);
		//
		// Elimina anita
		self::eliminarAnita($precarga_comprobante_proveedor->id);

        $precarga_comprobante_proveedor = $this->model->destroy($id);

		return $precarga_comprobante_proveedor;
    }

    public function find($id)
    {
        if (null == $precarga_comprobante_proveedor = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $precarga_comprobante_proveedor;
    }

    public function findOrFail($id)
    {
        if (null == $precarga_comprobante_proveedor = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $precarga_comprobante_proveedor;
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
				prec_id,
				prec_proveedor,
                prec_empresa,
                prec_tipo,
                prec_letra,
                prec_sucursal,
                prec_numero,
                prec_ordencompra,
                prec_subtotal,
                prec_total
				',
            'valores' => " 
				'".$id."', 
                '".str_pad($request['codigoproveedor'], 6, "0", STR_PAD_LEFT)."', 
                '".$request['codigoempresa']."', 
                '".$request['tipo']."', 
                '".$request['letra']."', 
                '".$request['sucursal']."', 
                '".$request['numerocomprobante']."', 
                '".$request['numeroordencompra']."', 
                '".$request['subtotal']."',
                '".$request['total']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                        prec_proveedor 	                = '".str_pad($request['codigoproveedor'], 6, "0", STR_PAD_LEFT)."',
                        prec_empresa 	               	= '".$request['codigoempresa']."',
                        prec_tipo 	               	= '".$request['tipo']."',
                        prec_letra 	               	= '".$request['letra']."', 
                        prec_sucursal 	               	= '".$request['sucursal']."',
                        prec_numero 	               	= '".$request['numerocomprobante']."',
                        prec_ordencompra 	               	= '".$request['numeroordencompra']."',
                        prec_subtotal 	               	= '".$request['subtotal']."',
                        prec_total 	               	= '".$request['total']."' "
					,
				'whereArmado' => " WHERE prec_id = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE prec_id = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

    public function leePrecargaComprobanteProveedor($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // lee usuario para setear filtros
        $usuario_id = Auth::user()->id;
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = [ 'precarga_comprobante_proveedor.id as id',
                    'precarga_comprobante_proveedor.empresa_id as empresa_id',
                    'precarga_comprobante_proveedor.proveedor_id as proveedor_id',
                    'precarga_comprobante_proveedor.tipotransaccion_compra_id as tipotransaccion_compra_id',
                    'empresa.nombre as nombreempresa',
                    'proveedor.nombre as nombreproveedor',
                    'tipotransaccion_compra.nombre as nombretipotransaccion_compra',
                    'precarga_comprobante_proveedor.letra as letra',
                    'precarga_comprobante_proveedor.sucursal as sucursal',
                    'precarga_comprobante_proveedor.numerocomprobante as numerocomprobante',
                    'precarga_comprobante_proveedor.fechafactura as fechafactura',
                    'precarga_comprobante_proveedor.fecharecepcionemail as fecharecepcionemail',
                    'precarga_comprobante_proveedor.numeroordencompra as numeroordencompra',
                    'precarga_comprobante_proveedor.total as total',
                    'precarga_comprobante_proveedor.estado as estado'
                    ];

        $precarga_comprobante_proveedors = $this->model->select($select)
                                ->join('empresa', 'empresa.id', '=', 'precarga_comprobante_proveedor.empresa_id')
                                ->leftjoin('proveedor', 'proveedor.id', '=', 'precarga_comprobante_proveedor.proveedor_id')
                                ->join('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'precarga_comprobante_proveedor.tipotransaccion_compra_id');

        $columns[] = ['columna' => 'precarga_comprobante_proveedor.id', 
                    'clausula' => 'LIKE'];                                
        $columns[] = ['columna' => 'empresa.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'proveedor.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'tipotransaccion_compra.nombre',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.letra',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.sucursal',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.numerocomprobante',
                    'clausula' => 'LIKE'];            
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.numeroordencompra',
                    'clausula' => 'LIKE'];                   
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.fechafactura',
                    'clausula' => 'LIKE'];           
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.fecharecepcionemail',
                    'clausula' => 'LIKE'];                                                                                              
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.estado',
                    'clausula' => 'LIKE'];                                                            

        $count = count($columns);

        $precarga_comprobante_proveedors->whereIn('precarga_comprobante_proveedor.empresa_id', $empresas);

        $precarga_comprobante_proveedors->where(function ($query) use ($count, $busqueda, $columns, $usuario_id) {

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $precarga_comprobante_proveedors->orderBy('id', 'desc');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->paginate(10);
            else
                $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->get();
        }
        else
            $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->get();

        return $precarga_comprobante_proveedors;
    }

}
