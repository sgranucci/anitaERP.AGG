<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class Articulo_MovimientoRepository implements Articulo_MovimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Movimiento $articulo_movimiento)
    {
        $this->model = $articulo_movimiento;
    }

    public function all()
    {
        $ret = $this->model->get();

        return $ret;
    }

    public function find($id)
    {
        if (null == $articulo_movimiento = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_movimiento;
    }

    public function findPorArticuloCombinacion($articulo_id, $combinacion_id)
    {
        return $this->model->where('articulo_id', $articulo_id)->where('combinacion_id', $combinacion_id)->get();
    }

    public function findPorPedidoCombinacionId($pedido_combinacion_id)
    {
        return $this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->first();
    }

    public function findPorPedidoArticuloId($pedido_articulo_id)
    {
        return $this->model->where('pedido_articulo_id', $pedido_articulo_id)->with("ventas")->first();
    }
    
    public function updatePorPedidoCombinacionId($pedido_combinacion_id, $data)
    {
        $actualizados = 0;
        foreach ($this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->get() as $movimiento) {
            $movimiento->update($data);
            $actualizados++;
        }

        return $actualizados;
    }

    public function create(array $data)
    {
        $articulo_movimiento = $this->model->create($data);

        if ($articulo_movimiento)
        {
            if (isset($medida['codigo']) && substr($medida['codigo'], 0, 3) == 'REM')
                $anita = Self::grabaAnita($data);
        }

        return $articulo_movimiento;
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	$articulo_movimiento = $this->model->destroy($id);

		return $articulo_movimiento;
    }

    public function deletePorMovimientoStockId($movimientostock_id)
    {
        $eliminados = 0;
        foreach ($this->model->where('movimientostock_id', $movimientostock_id)->get() as $movimiento) {
            $movimiento->delete();
            $eliminados++;
        }

        return $eliminados;
    }

    public function deletePorOrdentrabajoId($ordentrabajo_id)
    {
        $eliminados = 0;
        foreach ($this->model->where('ordentrabajo_id', $ordentrabajo_id)->get() as $movimiento) {
            $movimiento->delete();
            $eliminados++;
        }

        return $eliminados;
    }
    
    public function deletePorPedido_combinacionId($pedido_combinacion_id)
    {
        $eliminados = 0;
        foreach ($this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->get() as $movimiento) {
            $movimiento->delete();
            $eliminados++;
        }

        return $eliminados;
    }

    private function grabaAnita($medida)
    {
        if (isset($medida['deposito']))
            $deposito = $medida['deposito'];
        else	
            $deposito = 1;
dd($medida);
        $data = array( 	'tabla' => 'stkmov', 
                    'acc' => 'insert',
                    'campos' => ' 
                        stkv_articulo, stkv_agrupacion, stkv_fecha, 
                        stkv_tipo, stkv_letra, stkv_sucursal, stkv_nro, 
                        stkv_ref_tipo, stkv_ref_sucursal, stkv_ref_nro,
                        stkv_deposito, stkv_cantidad, stkv_precio, stkv_cod_mon,
                        stkv_cod_impuesto, stkv_descuento, stkv_dto_gral, stkv_comision,
                        stkv_nro_orden, stkv_cli_pro, stkv_vendedor, stkv_zona_vta,
                        stkv_zona_mult, stkv_subzona, stkv_comprador, stkv_partida, stkv_pedido,
                        stkv_usuario, stkv_terminal, stkv_fe_ult_act, stkv_cod_entrega,
                        stkv_cod_umd, stkv_unidad_xenv, stkv_cod_umd_alter'.
                        (config('app.empresa') == 'Calzados Ferli' ? ', stkv_cant_unidad, stkv_color' : '').
                        (config('app.empresa') == 'EL BIERZO' ? ', stkv_expreso, stkv_cant_unidad' : '').
                        (config('app.empresa') == 'AGG' ? ', stkv_cant_unidad, stkv_empresa' : ''),
                    'valores' => "
                        '".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."',
                        '".str_pad($medida['categoria'], 4, "0", STR_PAD_LEFT)."',
                        '".date('Ymd', strtotime($venta['fecha']))."',
                        '".substr($medida['codigo'], 0, 3)."',
                        '".$medida['letra']."',
                        '".$medida['puntoventa']."',
                        '".$medida['numerocomprobante']."',
                        '".' '."',
                        '".'0'."',
                        '".'0'."',
                        '".$deposito."',
                        '".$medida['cantidad']."',
                        '".$medida['precio']."',
                        '".$medida['moneda_id']."',
                        '".$medida['impuesto_id']."', 
                        '".$medida['descuento']."',
                        '".$medida['descuentopie']."',
                        '".'0'."',
                        '".$medida['item']."',
                        '".str_pad($medida['codigocliente'], 6, "0", STR_PAD_LEFT)."', 
                        '".$medida['codigovendedor']."',
                        '".($medida['codigozonavta'] == null ? '0' : $medida['codigozonavta'])."',
                        '".($medida['codigoprovincia'] == null ? '0' : $medida['codigoprovincia'])."',
                        '".($medida['codigosubzona'] == null ? '0' : $medida['codigosubzona'])."',
                        '".'0'."',
                        '".($ifx_server == 'IFX_SERVER_LOCAL' ? $medida['medida'] : $medida['partida'])."',
                        '".substr($medida['pedido'],-8)."',
                        '".Auth::user()->nombre."',
                        '".'ERP'."',
                        '".date_format(Carbon::now(), 'Ymd')."',
                        '".'0'."',
                        '".'0'."',
                        '".(config('app.empresa') == 'EL BIERZO' ? $medida['caja'] : '0')."',
                        '".'0'."'".
                        (config('app.empresa') == 'Calzados Ferli' ? 
                        ",'".'0'."',
                        '".$medida['codigocombinacion']."'" :
                        ""
                        ).
                        (config('app.empresa') == 'EL BIERZO' ? 
                        ",'".$medida['codigotransporte']."',
                        '".$medida['pieza']."'" :
                        ""
                        ).
                        (config('app.empresa') == 'AGG' ? 
                        ",'".$medida['cantidad']."',
                        '".$medida['empresa']."'" :
                        ""
                        )
            );

            $stkmov = $apiAnita->apiCallEscritura($data);

        return ['mensaje' => 'Success'];
    }
}
