<?php

return [

    // Validez por defecto de tokens visualizar en mails (horas). Módulos pueden sobreescribir.
    'publico_horas_validez_token' => (int) env('MODULO_AVISO_PUBLICO_HORAS_TOKEN', 168),

    /*
    | Handler por clave modulo.codigo → clase que implementa ModuloAvisoHandlerInterface
    | o ModuloAvisoDespachoHandlerInterface.
    */
    'handlers' => [
        'sala.requisicion_sala_creacion' => App\Services\Configuracion\Handlers\SalaRequisicionSalaCreacionAvisoHandler::class,
        'compras.requisicion_compra_cumplida' => App\Services\Configuracion\Handlers\ComprasRequisicionCumplidaAvisoDespachoHandler::class,
        'compras.ordencompra_alertas_abiertas' => App\Services\Configuracion\Handlers\ComprasOrdencompraAlertasAbiertasAvisoHandler::class,
        'stock.prestamo_solicitud' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_recordatorio' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_aprobado_solicitante' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_rechazado_solicitante' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.recepcion_proveedor_precio_diferencia' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_precio_pendiente_compras' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_precio_liberado' => App\Services\Configuracion\Handlers\StockRecepcionProveedorPrecioLiberadoAvisoHandler::class,
        'stock.recepcion_proveedor_laboratorio' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_cantidad_diferencia' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_articulo_extra' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_faltante_oc' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_ingresada' => App\Services\Configuracion\Handlers\StockRecepcionProveedorIngresadaAvisoHandler::class,
        'stock.recepcion_proveedor_linea_rechazada' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_parte_unica' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_encuesta' => App\Services\Configuracion\Handlers\StockRecepcionProveedorEncuestaAvisoHandler::class,
        'stock.transferencia_pendiente_aprobacion' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'stock.transferencia_confirmada' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'stock.transferencia_rechazada' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'ventas.pedido_produccion_alarma' => App\Services\Configuracion\Handlers\VentasPedidoProduccionAvisoHandler::class,
        'contable.apertura_periodo_habilitada' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_solicitud_pendiente' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_recordatorio' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_cerrada' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'uif.cliente_alta' => App\Services\Configuracion\Handlers\UifClienteAltaAvisoHandler::class,
        'sueldos.empleado_alta_provisoria' => App\Services\Configuracion\Handlers\SueldosEmpleadoAltaProvisoriaAvisoHandler::class,
    ],

];
