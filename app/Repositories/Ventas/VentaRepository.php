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
                                ->with('ordenventas')
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
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
                                ->with('ordenventas')                                    
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
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
			'tabla' => 'pendmae', 
            'sistema' => 'ventas',
            'campos' => '
                max(penm_nro) as ultnro
			' , 
            'whereArmado' => " WHERE penm_tipo='".$tipo."' and penm_letra='".$letra."' 
                                    and penm_sucursal='".$sucursal."' " 
        );
        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

        if ($fila !== null && isset($fila->ultnro)) {
            return (int) $fila->ultnro + 1;
        }

        return 'error';
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
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            return 'Error al leer compemis: '.$errCompe;
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            return 0;
        }

        $claveNumero = $filaCompe->compe_numero;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => '
                num_ult_numero
            ',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        $errNumerador = ApiAnita::extraerMensajeError($rawNumerador);
        if ($errNumerador !== null) {
            return 'Error al leer numerador: '.$errNumerador;
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            return 'Error al actualizar numerador';
        }

        $numero = (int) $filaNumerador->num_ult_numero + 1;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'update',
            'tabla' => 'numerador',
            'valores' => "num_ult_numero = '".$numero."' ",
            'whereArmado' => " WHERE num_clave = '".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        $numerador = $apiAnita->apiCallEscritura($data);

        if (ApiAnita::extraerMensajeError($numerador) !== null) {
            return 'Error al actualizar numerador';
        }

        return $numero;
    }

    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id, ?int $empresa_id = null)
    {
        $query = $this->model->select('venta.numerocomprobante')
            ->where('venta.tipotransaccion_id', $tipotransaccion_id)
            ->where('venta.puntoventa_id', $puntoventa_id)
            ->whereNull('venta.deleted_at');

        if ($empresa_id !== null && $empresa_id > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresa_id): void {
                $q->where('empresa_id', $empresa_id);
            });
        }

        return $query->orderBy('venta.numerocomprobante', 'desc')->first();
    }

    /**
     * Último número reservado en compemis/numerador Anita (sin incrementar).
     */
    public function leerUltimoNumeradorCompemis(string $tipo, string $letra, string $sucursal, $path_sistema = null): int
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'compemis',
            'campos' => 'compe_numero',
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."'
                                    and compe_sucursal='".$sucursal."' ",
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            return 0;
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            return 0;
        }

        $claveNumero = $filaCompe->compe_numero;

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        if (ApiAnita::extraerMensajeError($rawNumerador) !== null) {
            return 0;
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            return 0;
        }

        return max(0, (int) $filaNumerador->num_ult_numero);
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
