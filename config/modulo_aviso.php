<?php

return [

    /*
    | Handler por clave modulo.codigo → clase que implementa ModuloAvisoHandlerInterface
    | o ModuloAvisoDespachoHandlerInterface.
    */
    'handlers' => [
        'sala.requisicion_sala_creacion' => App\Services\Configuracion\Handlers\SalaRequisicionSalaCreacionAvisoHandler::class,
        'stock.prestamo_solicitud' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_recordatorio' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_aprobado_solicitante' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.prestamo_rechazado_solicitante' => App\Services\Configuracion\Handlers\StockPrestamoAvisoDespachoHandler::class,
        'stock.recepcion_proveedor_precio_diferencia' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_laboratorio' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_cantidad_diferencia' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_articulo_extra' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
        'stock.recepcion_proveedor_faltante_oc' => App\Services\Configuracion\Handlers\StockRecepcionProveedorAvisoHandler::class,
    ],

];
