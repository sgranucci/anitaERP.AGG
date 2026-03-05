<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Cliente_CuentacorrienteRepository implements Cliente_CuentacorrienteRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Cuentacorriente $cliente_cuentacorriente)
    {
        $this->model = $cliente_cuentacorriente;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $cliente_cuentacorriente = $this->model->findOrFail($id)->update($data);

		return $cliente_cuentacorriente;
    }

    public function delete($id)
    {
    	$cliente_cuentacorriente = $this->model->destroy($id);

		return $cliente_cuentacorriente;
    }

    public function find($id)
    {
        if (null == $cliente_cuentacorriente = $this->model->with('ventas')->with('monedas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_cuentacorriente = $this->model->with('ventas')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente;
    }

    public function findPorVenta($venta_id)
    {
        return $this->model->where('venta_id', $venta_id)->with('ventas')->with('monedas')->get();
    }

    public function buscaPorVentaCobranza($venta_id, $cobranza_id)
    {
        if (!isset($venta_id))
            return $this->model->where('venta_id', null)
                    ->where('cobranza_id', $cobranza_id)->with('ventas')->with('monedas')->get();
        else
            return $this->model->where('venta_id', $venta_id)
                    ->where('cobranza_id', $cobranza_id)->with('ventas')->with('monedas')->get();
    }

    public function listarCuentaCorriente($busqueda, $cliente_id)
    {
        $cuentacorriente = $this->model->with('ventas')->with("cobranzas")->with('monedas')->with('empresas')->where('cliente_id', $cliente_id)
                                        ->where('cliente_cuentacorriente.deleted_at', null)
                                        ->whereHas('monedas', function ($query) use ($busqueda) {
                                            $query->orwhere('abreviatura', 'like', "%{$busqueda}%");
                                        })
                                        ->whereHas('ventas', function ($query) use ($busqueda) {
                                            $query->orwhere('codigo', 'like', "%{$busqueda}%");
                                        })
                                        ->orwhere('fecha', $busqueda)
                                        ->orwhere('fechavencimiento', $busqueda)
                                        ->orderBy('fecha', 'asc')
                                        ->paginate(10);								

        return $cuentacorriente;
    }

    public function consultarDeuda($cliente_id, $empresa_id, $venta_id = null)
    {
        $cuentacorriente = $this->model->select('venta.id as idventa',
                                                'cliente_cuentacorriente.id as idcuentacorriente',
                                                'cliente_cuentacorriente.fecha as fecha',
                                                'cliente_cuentacorriente.fechavencimiento as fechavencimiento',
                                                'cliente_cuentacorriente.cliente_id as cliente_id',
                                                'cliente_cuentacorriente.total as total',
                                                'cliente_cuentacorriente.moneda_id as moneda_id',
                                                'cliente_cuentacorriente.cotizacion as cotizacion',
                                                'cliente_cuentacorriente.empresa_id as empresa_id',
                                                'cliente_cuentacorriente.cobranza_id as cobranza_id',
                                                'venta.codigo as codigo',
                                                'puntoventa.empresa_id as empresa_id',
                                                'moneda.abreviatura as abreviaturamoneda',
                                                'cliente.nombre as nombrecliente',
                                                'cliente.codigo as codigocliente'
                                                )
                                                ->leftJoin('venta', 'venta.id', 'cliente_cuentacorriente.venta_id')
                                                ->join('puntoventa', 'puntoventa.id', 'venta.puntoventa_id')
                                                ->join('moneda', 'moneda.id', 'cliente_cuentacorriente.moneda_id')
                                                ->join('cliente', 'cliente.id', 'venta.cliente_id')
                                                ->addSelect([
                                                    'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                                                        ->selectRaw('SUM(total)')
                                                        ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                                ])
                                                ->havingRaw('abs(aplicado) < abs(cliente_cuentacorriente.total) or aplicado is null')
                                                ->where('cliente_cuentacorriente.deleted_at', null);

        if (isset($venta_id))
            $cuentacorriente = $cuentacorriente->where('venta.id', $venta_id);
        else
            $cuentacorriente = $cuentacorriente->where('cliente_cuentacorriente.cliente_id', $cliente_id)
                                                ->where('puntoventa.empresa_id', $empresa_id);
        
        $cuentacorriente = $cuentacorriente->orderBy('fecha', 'asc')->get();

        return $cuentacorriente;
    }

    public function consultarAplicacion($cliente_cuentacorriente_id)
    {
        $cuentacorriente = $this->model->select('cliente_cuentacorriente.id as cuentacorriente_id',
                                                'cliente_cuentacorriente_aplicacion.id as id',
                                                'cliente_cuentacorriente_aplicacion.fecha as fechaaplicacion',
                                                'cliente_cuentacorriente_aplicacion.comprobanteaplicado as comprobante',
                                                'cliente_cuentacorriente_aplicacion.total as total',
                                                'cliente_cuentacorriente_aplicacion.moneda_id as moneda_id',
                                                'cliente_cuentacorriente_aplicacion.cotizacion as cotizacion',
                                                'cliente_cuentacorriente_aplicacion.ventaaplicado_id as ventaaplicado_id',
                                                'cliente_cuentacorriente_aplicacion.cobranza_id as cobranza_id',
                                                'cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_aplicado_id as aplicado_id'
                                                )
                                                ->join('cliente_cuentacorriente_aplicacion', 'cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                                ->where('cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_id', $cliente_cuentacorriente_id)
                                                ->orderBy('cliente_cuentacorriente_aplicacion.fecha')
                                                ->get();

        return $cuentacorriente;
    }
}

