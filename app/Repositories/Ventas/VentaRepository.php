<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use Auth;
use App\ApiAnita;

class VentaRepository implements VentaRepositoryInterface
{
    protected $model;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Venta $venta,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $venta;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function leeSinPaginar($busqueda)
    {
        $data = $this->model->whereHas('clientes', function ($query) use ($busqueda) {
                                $query->where('nombre', 'like', '%'.$busqueda.'%');
                            })
                            ->orWhereHas('tipotransacciones', function ($query) use ($busqueda) {
                                $query->where('nombre', 'like', '%'.$busqueda.'%');
                            })
                            ->orWhereHas('puntoventas', function ($query) use ($busqueda) {
                                $query->where('codigo', 'like', '%'.$busqueda.'%');
                            })
                            ->orWhere('numerocomprobante', $busqueda)
                            ->orderBy('id','desc')->get();
        return $data;
    }

    public function leePaginando($busqueda)
    {
        // Trae empresas para filtrar
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $data = $this->model->whereHas('puntoventas', function ($query) use ($busqueda, $empresas) {
                                    $query->whereIn('empresa_id', $empresas);
                                    //$query->orwhereHas('empresas', function ($query) use ($busqueda) {
                                    //    $query->where('nombre', 'like', '%'.$busqueda.'%');
                                    //})->with('empresas');
                            })->with('puntoventas')
                            ->whereHas('clientes', function ($query) use ($busqueda) {
                                $query->orwhere('nombre', 'like', '%'.$busqueda.'%');
                            })
                            ->WhereHas('tipotransacciones', function ($query) use ($busqueda) {
                                $query->orwhere('nombre', 'like', '%'.$busqueda.'%');
                            })
                            ->WhereHas('puntoventas', function ($query) use ($busqueda) {
                                    $query->whereHas('empresas', function ($query) use ($busqueda) {
                                        $query->where('nombre', 'like', '%'.$busqueda.'%');
                                    })->with('empresas');
                            })
                            ->orWhere('numerocomprobante', $busqueda)
                            ->orderBy('id','desc')->paginate(12);
        return $data;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')        
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('clientes')
                                ->with('ordenventas')
                                ->with('asientos')
                                ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta;
    }

    public function findOrFail($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')    
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('clientes')
                                ->with('ordenventas')                                    
                                ->with('asientos')
                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta;
    }

    public function traeUltimoNumeroRemito($tipo, $letra, $sucursal)
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'compemis', 
            'campos' => '
                compe_numero
			' , 
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."' 
                                    and compe_sucursal='".$sucursal."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        
        if (count($dataAnita) > 0)
        {
            $claveNumero = $dataAnita[0]->compe_numero;

            $apiAnita = new ApiAnita();
            $data = array( 
                'acc' => 'list', 
                'tabla' => 'numerador', 
                'campos' => '
                    num_ult_numero
                ' , 
                'whereArmado' => " WHERE num_clave='".$claveNumero."' " 
            );
            $dataAnita = json_decode($apiAnita->apiCall($data));

            $nro = $dataAnita[0]->num_ult_numero + 1;
        }
        
        //$venta = $this->model->where('puntoventaremito_id', $puntoventaremito_id)->max('numeroremito');
		//$nro = 0;
		//if ($venta)
		//	$nro = $venta;
		//$nro = $nro + 1;
        if (!isset($nro))
            return 'error';
        
        return $nro;
    }

    public function numeraAnita($tipo, $letra, $sucursal, $path_sistema = null)
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'compemis', 
            'campos' => '
                compe_numero
			' , 
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."' 
                                    and compe_sucursal='".$sucursal."' " 
        );
        if (isset($path_sistema))
            $data['path_sistema'] = $path_sistema;
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0)
        {
            $claveNumero = $dataAnita[0]->compe_numero;

            $apiAnita = new ApiAnita();
            $data = array( 
                'acc' => 'list', 
                'tabla' => 'numerador', 
                'campos' => '
                    num_ult_numero
                ' , 
                'whereArmado' => " WHERE num_clave='".$claveNumero."' " 
            );
            if (isset($path_sistema))
                $data['path_sistema'] = $path_sistema;
            $dataAnita = json_decode($apiAnita->apiCall($data));

            $numero = $dataAnita[0]->num_ult_numero + 1;

            $apiAnita = new ApiAnita();
            $data = array( 'acc' => 'update', 
                    'tabla' => 'numerador',
                    'valores' => "num_ult_numero = '".$numero."' ",
                    'whereArmado' => " WHERE num_clave = '".$claveNumero."' " 
                    );
            if (isset($path_sistema))
                $data['path_sistema'] = $path_sistema;                    
            $numerador = $apiAnita->apiCall($data);

            if (strpos($numerador, 'Error') !== false)
                return 'Error al actualizar numerador';
        }
        else
          //  return 'Error no tiene numerador';
            $numero = 0;

        return $numero;
    }

    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id)
    {
        $venta = $this->model->select('numerocomprobante')
                                ->where('tipotransaccion_id', $tipotransaccion_id)
                                ->where('puntoventa_id', $puntoventa_id)
                                ->where('deleted_at', null)
                                ->orderBy('numerocomprobante','desc')->first();

        return $venta;
    }

    public function leeComprobantePorOrdenVenta($ordenventa_id)
    {
        return $this->model->select('venta.id as id', 
                                    'venta.codigo as codigo', 
                                    'venta.fecha as fecha', 
                                    'cliente_cuentacorriente.fechavencimiento as fechavencimiento',
                                    'moneda.abreviatura as moneda', 
                                    'venta.total as total')
                                ->leftjoin('cliente_cuentacorriente', 'cliente_cuentacorriente.venta_id', '=', 'venta.id')
                                ->addSelect([
                                    'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                                        ->selectRaw('SUM(total)')
                                        ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                ])                                    
                                ->join('moneda', 'moneda.id', 'venta.moneda_id')
                                ->with('cliente_cuentacorrientes')
                                ->where('ordenventa_id', $ordenventa_id)
                                ->where('venta.deleted_at', null)
                                ->where('cliente_cuentacorriente.cobranza_id', null)
                                ->orderBy('venta.fecha')->get();
    }
}
