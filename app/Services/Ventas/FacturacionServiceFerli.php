<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Impuesto;
use App\Queries\Ventas\PedidoQueryFerli;
use App\Services\Stock\PrecioServiceFerli;
use Auth;
use Cache;
use Carbon\Carbon;

/**
 * Facturación Ferli por OT / pedido_combinacion / talle.
 * Reutiliza emisión ARCA del FacturacionService; no toca kilos, CAEA salto ni reparto Bierzo.
 */
class FacturacionServiceFerli extends FacturacionService
{
    protected function asignaPrecioLineaItemOt($articulo, $combinacion_id, $talle, $fechaFactura)
    {
        return app(PrecioServiceFerli::class)->asignaPrecio(
            $articulo->id,
            $combinacion_id,
            $talle->id,
            $fechaFactura
        );
    }

    protected function aplicarVencimientosFacturaOt(array $cuentacorriente, $fechaFactura, $puntoventa): array
    {
        return $cuentacorriente;
    }

    protected function prepararSesionFacturaOt(array $data): void
    {
        Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
        Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);
        Cache::forever(generaKey('puntoventaremito'), $data['puntoventaremito_id']);
    }

    protected function leePedidoFacturaOt($id)
    {
        return app(PedidoQueryFerli::class)->leePedidoporId($id);
    }

    protected function aplicarLugarEntregaFacturaOt($cliente, $pedido)
    {
        if ($cliente->id != $pedido->cliente_id) {
            $cliente_entrega = $this->cliente_entregaRepository->leeClienteEntrega($cliente->id);

            if ($cliente_entrega) {
                $pedido->lugarentrega = $cliente_entrega[0]->nombre;
            }

            $this->descuentoPie = $cliente->descuento;
        }

        return null;
    }

    protected function sincronizarLugarEntregaFacturaOt($pedido): void
    {
    }

    protected function transporteIdFacturaOt(array $data, $pedido)
    {
        $transporteId = (int) ($data['transporte_id'] ?? 0);

        return $transporteId > 0 ? $transporteId : $pedido->transporte_id;
    }

    public function grabaStockLocal($puntoventa, $letra, $venta, $datatalle,
        $codigoCliente = '', $vendedor = 1, $zonavta_id = 0, $provincia_id = 902,
        $subzonavta_id = 0, $servidor = 'LOCAL_IP', $ifx_server = 'IFX_SERVER_LOCAL')
    {
        $dataItem = $this->agrupaItemsPorMedidaFerli($datatalle, $ifx_server);
        $usuario = Auth::check() ? Auth::user()->nombre : 'ERP';
        $orden = 0;

        foreach ($dataItem as $medida) {
            $orden++;
            $impuesto = Impuesto::findOrFail($medida['impuesto_id']);
            $tasa = $impuesto ? $impuesto->valor : 1;

            if ($medida['incluyeimpuesto'] == '1') {
                $precio = $medida['precio'] / (1 + ($tasa / 100));
            } else {
                $precio = $medida['precio'];
            }

            $deposito = isset($medida['deposito']) ? $medida['deposito'] : 1;
            if ($ifx_server == 'IFX_SERVER_LOCAL') {
                $deposito = ($puntoventa == 27) ? 27 : 10;
            }

            $apiAnita = new ApiAnita();
            $data = [
                'tabla' => 'stkmov',
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
					stkv_cod_umd, stkv_unidad_xenv, stkv_cod_umd_alter, stkv_cant_unidad,
					stkv_color
				',
                'valores' => "
					'".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."',
					'".str_pad($medida['categoria'], 4, "0", STR_PAD_LEFT)."',
					'".date('Ymd', strtotime($venta['fecha']))."',
					'".substr($venta['codigo'], 0, 3)."',
					'".$letra."',
					'".$puntoventa."',
					'".$venta['numerocomprobante']."',
					' ',
					'0',
					'0',
					'".$deposito."',
					'".$medida['cantidad']."',
					'".$precio."',
					'".$venta['moneda_id']."',
					'".$medida['impuesto_id']."',
					'".($this->descuentoLinea == null || $letra == 'E' ? 0 : $this->descuentoLinea)."',
					'".($this->descuentoPie == null ? 0 : $this->descuentoPie)."',
					'0',
					'".$orden."',
					'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
					'".$vendedor."',
					'".($zonavta_id == null ? '0' : $zonavta_id)."',
					'".($provincia_id == null ? '0' : $provincia_id)."',
					'".($subzonavta_id == null ? '0' : $subzonavta_id)."',
					'0',
					'".($ifx_server == 'IFX_SERVER_LOCAL' ? $medida['medida'] : $medida['partida'])."',
					'".substr($medida['pedido'], -8)."',
					'".$usuario."',
					'ERP',
					'".date_format(Carbon::now(), 'Ymd')."',
					'0',
					'0',
					'0',
					'0',
					'0',
					'".$medida['codigocombinacion']."'
				",
                'servidor' => $servidor,
                'ifx_server' => $ifx_server,
            ];
            $stkmov = $apiAnita->apiCallEscritura($data);
            if ($this->respuestaAnitaFalloFerli($stkmov)) {
                return 'Error stkmov: '.$stkmov;
            }

            $apiAnita = new ApiAnita();
            $data = [
                'tabla' => 'stkvmed',
                'acc' => 'insert',
                'campos' => '
					stkvm_articulo, stkvm_agrupacion, stkvm_fecha,
					stkvm_tipo, stkvm_letra, stkvm_sucursal, stkvm_nro,
					stkvm_nro_orden, stkvm_deposito, stkvm_cli_pro, stkvm_vendedor,
					stkvm_zona_vta, stkvm_zona_mult, stkvm_subzona_vta, stkvm_comprador,
					stkvm_partida, stkvm_medida, stkvm_marca, stkvm_linea, stkvm_cantidad,
					stkvm_color
				',
                'valores' => "
					'".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."',
					'".str_pad($medida['categoria'], 4, "0", STR_PAD_LEFT)."',
					'".date('Ymd', strtotime($venta['fecha']))."',
					'".substr($venta['codigo'], 0, 3)."',
					'".$letra."',
					'".$puntoventa."',
					'".$venta['numerocomprobante']."',
					'".$orden."',
					'".$deposito."',
					'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
					'".$vendedor."',
					'".($zonavta_id == null ? '0' : $zonavta_id)."',
					'".($provincia_id == null ? '0' : $provincia_id)."',
					'".($subzonavta_id == null ? '0' : $subzonavta_id)."',
					'0',
					'".($ifx_server == 'IFX_SERVER_LOCAL' ? $medida['medida'] : $medida['partida'])."',
					'".$medida['medida']."',
					'0',
					'0',
					'".$medida['cantidad']."',
					'".$medida['codigocombinacion']."'
				",
                'servidor' => $servidor,
                'ifx_server' => $ifx_server,
            ];
            $stkvmed = $apiAnita->apiCallEscritura($data);
            if ($this->respuestaAnitaFalloFerli($stkvmed)) {
                return 'Error stkvmed';
            }
        }

        return 'Success';
    }

    private function agrupaItemsPorMedidaFerli($datatalle, $ifx_server)
    {
        $dataItem = [];
        foreach ($datatalle as $item) {
            foreach ($item['medidas'] as $medida) {
                $partida = 1;
                if ($medida['medida'] >= config('consprod.DESDE_INTERVALO1') &&
                    $medida['medida'] <= config('consprod.HASTA_INTERVALO1')) {
                    $partida = 1;
                }
                if ($medida['medida'] >= config('consprod.DESDE_INTERVALO2') &&
                    $medida['medida'] <= config('consprod.HASTA_INTERVALO2')) {
                    $partida = 2;
                }
                if ($medida['medida'] >= config('consprod.DESDE_INTERVALO3') &&
                    $medida['medida'] <= config('consprod.HASTA_INTERVALO3')) {
                    $partida = 3;
                }
                if ($medida['medida'] >= config('consprod.DESDE_INTERVALO4') &&
                    $medida['medida'] <= config('consprod.HASTA_INTERVALO4')) {
                    $partida = 4;
                }

                $flEncontro = false;
                $ii = 0;
                for ($ii = 0; $ii < count($dataItem); $ii++) {
                    if (($ifx_server == 'IFX_SERVER_LOCAL' ?
                        $dataItem[$ii]['medida'] == $medida['medida'] : $dataItem[$ii]['partida'] == $partida) &&
                        $dataItem[$ii]['sku'] == $item['sku'] &&
                        $dataItem[$ii]['codigocombinacion'] == $item['codigocombinacion']) {
                        $flEncontro = true;
                        break;
                    }
                }

                if ($flEncontro) {
                    $dataItem[$ii]['cantidad'] += $medida['cantidad'];
                } else {
                    $dataItem[] = [
                        'partida' => $partida,
                        'cantidad' => $medida['cantidad'],
                        'precio' => $medida['precio'],
                        'impuesto_id' => $item['impuesto_id'],
                        'incluyeimpuesto' => $item['incluyeimpuesto'],
                        'pedido' => $medida['pedido'],
                        'sku' => $item['sku'],
                        'descripcion' => $item['descripcion'],
                        'categoria' => $item['categoria'],
                        'codigocombinacion' => $item['codigocombinacion'],
                        'despacho' => $item['despacho'],
                        'medida' => $medida['medida'],
                    ];
                }
            }
        }

        return $dataItem;
    }

    private function respuestaAnitaFalloFerli($respuesta)
    {
        if ($respuesta === false || $respuesta === null || $respuesta === '') {
            return true;
        }

        return strpos((string) $respuesta, 'Error') !== false;
    }
}
