<?php

return [

    // Validez por defecto de tokens visualizar en mails (horas). Módulos pueden sobreescribir.
    'publico_horas_validez_token' => (int) env('MODULO_AVISO_PUBLICO_HORAS_TOKEN', 168),

    // Cron: restaura reemplazos de destinatario con vence_el vencido (día siguiente al último día inclusive).
    'reemplazo_destinatario_vencidos' => [
        'hora' => env('MODULO_AVISO_REEMPLAZO_VENCIDOS_HORA', '00:05'),
    ],

    /**
     * Avisos cuya frecuencia se edita en Configuración → Avisos por módulo.
     * El cron avisos:despachar-programados ejecuta el comando cuando corresponde.
     *
     * @var array<string, string> modulo.codigo => comando artisan
     */
    'programados' => [
        'sueldos.libreta_sanitaria_pendiente' => 'sueldos:avisar-libreta-sanitaria',
        'sueldos.empleado_baja_provisoria' => 'sueldos:avisar-baja-provisoria',
        'caja.cheques_aging' => 'caja:avisar-cheques-aging',
        'compras.ordencompra_alertas_abiertas' => 'compras:alertas-ordencompra-abiertas',
        'compras.entrega_dia_sin_recepcion' => 'compras:avisar-entrega-dia-sin-recepcion',
        'compras.ordencompra_contrato_vencimiento' => 'compras:alertas-contratos-vencimiento',
        'compras.suscripcion_comprobante_faltante' => 'compras:alertas-suscripcion-comprobantes',
        'compras.comprobante_proveedor_borrador_pendiente' => 'compras:avisar-comprobantes-borrador',
        'arca.certificado_vencimiento' => 'arca:avisar-vencimiento-certificados',
        'seguridad.ingreso_proveedor_recordatorio' => 'seguridad:recordatorio-tickets-ingreso',
        'stock.prestamo_recordatorio' => 'prestamo:recordatorios',
        'listado.envios_workbench' => 'compras:enviar-listados-programados',
        'consulta_inteligente.envios_programados' => 'direccion:enviar-consultas-inteligentes',
        'contable.reportes_definibles' => 'contable:distribuir-reportes-definibles',
        'sueldos.reportes_definibles' => 'sueldos:distribuir-reportes-definibles',
        'flash.distribuir_reportes' => 'flash:distribuir-reportes',
        'flash_crown.distribuir_reportes' => 'flash-crown:distribuir-reportes',
        'arbolaprobacion.digest_pendientes' => 'arbolaprobacion:digest-pendientes',
        'arbolaprobacion.recordatorios' => 'arbolaprobacion:recordatorios',
        'tesoreria_crown.avisos' => 'tesoreria-crown:avisos',
        'ai.digest_brief' => 'ai:digest-brief',
        'ai.alerta_umbral' => 'ai:alerta-umbral',
        'ventas.kilos_pedidos_manana_1' => 'ventas:distribuir-kilos-pedidos-mail',
        'ventas.kilos_pedidos_manana_2' => 'ventas:distribuir-kilos-pedidos-mail',
        'ventas.kilos_pedidos_tarde_parcial' => 'ventas:distribuir-kilos-pedidos-mail',
        'ventas.kilos_pedidos_tarde_total' => 'ventas:distribuir-kilos-pedidos-mail',
        'ventas.control_afip_pedidos' => 'ventas:distribuir-control-afip-pedidos-mail',
        'ventas.control_nc_descuento' => 'ventas:distribuir-control-nc-descuento-mail',
        'stock.stkmov_int_diario' => 'stock:distribuir-stkmov-int-mail',
    ],

    /*
    | diaria = una vez por día (ultima_en). cada_pasada = cada poll 15′ (suscripción decide).
    */
    'programados_frecuencia' => [
        'contable.reportes_definibles' => 'cada_pasada',
        'sueldos.reportes_definibles' => 'cada_pasada',
        'flash.distribuir_reportes' => 'cada_pasada',
        'flash_crown.distribuir_reportes' => 'cada_pasada',
    ],

    /*
    | Gate por instalación (EMPRESA). Sin entrada = todos los entornos.
    */
    'programados_entorno' => [
        'flash.distribuir_reportes' => 'agg',
        'flash_crown.distribuir_reportes' => 'crown',
        'tesoreria_crown.avisos' => 'crown',
        'ventas.kilos_pedidos_manana_1' => 'elbierzo',
        'ventas.kilos_pedidos_manana_2' => 'elbierzo',
        'ventas.kilos_pedidos_tarde_parcial' => 'elbierzo',
        'ventas.kilos_pedidos_tarde_total' => 'elbierzo',
        'ventas.control_afip_pedidos' => 'elbierzo',
        'ventas.control_nc_descuento' => 'elbierzo',
        'stock.stkmov_int_diario' => 'elbierzo',
    ],

    /*
    | Flags Artisan por clave.
    */
    'programados_opciones' => [
        'sueldos.reportes_definibles' => ['--ejecutar' => true],
        'tesoreria_crown.avisos' => ['--ejecutar' => true, '--anita' => true, '--mail' => true],
        'ai.digest_brief' => ['--ejecutar' => true],
        'ai.alerta_umbral' => ['--ejecutar' => true],
        'ventas.kilos_pedidos_manana_1' => ['--perfil' => 'manana_1'],
        'ventas.kilos_pedidos_manana_2' => ['--perfil' => 'manana_2'],
        'ventas.kilos_pedidos_tarde_parcial' => ['--perfil' => 'tarde_parcial'],
        'ventas.kilos_pedidos_tarde_total' => ['--perfil' => 'tarde_total'],
        'ventas.control_afip_pedidos' => [],
        'ventas.control_nc_descuento' => [],
        'stock.stkmov_int_diario' => [],
    ],

    'programados_habilitado' => env('MODULO_AVISO_PROGRAMADOS', true),

    // Campanita + mail al fallar una corrida (máx. 1 alerta por clave y día).
    'alerta_fallo_habilitada' => env('MODULO_AVISO_ALERTA_FALLO', true),

    /*
    | Acción tipada por clave (catálogo, sin script libre).
    | Valores: comando_artisan | listado_excel | consulta_inteligente | webhook_allowlist
    */
    'acciones_tipadas' => [
        'sueldos.libreta_sanitaria_pendiente' => 'comando_artisan',
        'sueldos.empleado_baja_provisoria' => 'comando_artisan',
        'caja.cheques_aging' => 'comando_artisan',
        'compras.ordencompra_alertas_abiertas' => 'comando_artisan',
        'compras.entrega_dia_sin_recepcion' => 'comando_artisan',
        'compras.ordencompra_contrato_vencimiento' => 'comando_artisan',
        'compras.suscripcion_comprobante_faltante' => 'comando_artisan',
        'compras.comprobante_proveedor_borrador_pendiente' => 'comando_artisan',
        'arca.certificado_vencimiento' => 'comando_artisan',
        'seguridad.ingreso_proveedor_recordatorio' => 'comando_artisan',
        'stock.prestamo_recordatorio' => 'comando_artisan',
        'listado.envios_workbench' => 'listado_excel',
        'consulta_inteligente.envios_programados' => 'consulta_inteligente',
        'contable.reportes_definibles' => 'comando_artisan',
        'sueldos.reportes_definibles' => 'comando_artisan',
        'flash.distribuir_reportes' => 'comando_artisan',
        'flash_crown.distribuir_reportes' => 'comando_artisan',
        'arbolaprobacion.digest_pendientes' => 'comando_artisan',
        'arbolaprobacion.recordatorios' => 'comando_artisan',
        'tesoreria_crown.avisos' => 'comando_artisan',
        'ai.digest_brief' => 'comando_artisan',
        'ai.alerta_umbral' => 'comando_artisan',
        'ventas.kilos_pedidos_manana_1' => 'comando_artisan',
        'ventas.kilos_pedidos_manana_2' => 'comando_artisan',
        'ventas.kilos_pedidos_tarde_parcial' => 'comando_artisan',
        'ventas.kilos_pedidos_tarde_total' => 'comando_artisan',
        'ventas.control_afip_pedidos' => 'comando_artisan',
        'ventas.control_nc_descuento' => 'comando_artisan',
        'stock.stkmov_int_diario' => 'comando_artisan',
    ],

    /*
    | Webhooks allowlist. Solo estas claves se pueden disparar desde reglas.
    | URL vacía = entrada ignorada. No hay URL libre desde la UI.
    */
    'webhooks_allowlist' => array_filter([
        'ops_aprobaciones' => array_filter([
            'url' => env('MODULO_AVISO_WEBHOOK_OPS_APROBACIONES_URL'),
            'metodo' => env('MODULO_AVISO_WEBHOOK_OPS_APROBACIONES_METODO', 'POST'),
            'timeout' => (int) env('MODULO_AVISO_WEBHOOK_OPS_APROBACIONES_TIMEOUT', 10),
        ]),
        'ops_stock' => array_filter([
            'url' => env('MODULO_AVISO_WEBHOOK_OPS_STOCK_URL'),
            'metodo' => env('MODULO_AVISO_WEBHOOK_OPS_STOCK_METODO', 'POST'),
            'timeout' => (int) env('MODULO_AVISO_WEBHOOK_OPS_STOCK_TIMEOUT', 10),
        ]),
        'ops_general' => array_filter([
            'url' => env('MODULO_AVISO_WEBHOOK_OPS_GENERAL_URL'),
            'metodo' => env('MODULO_AVISO_WEBHOOK_OPS_GENERAL_METODO', 'POST'),
            'timeout' => (int) env('MODULO_AVISO_WEBHOOK_OPS_GENERAL_TIMEOUT', 10),
        ]),
    ], static fn ($cfg) => is_array($cfg) && ! empty($cfg['url'])),

    /*
    | Handler por clave modulo.codigo → clase que implementa ModuloAvisoHandlerInterface
    | o ModuloAvisoDespachoHandlerInterface.
    */
    'handlers' => [
        'sala.requisicion_sala_creacion' => App\Services\Configuracion\Handlers\SalaRequisicionSalaCreacionAvisoHandler::class,
        'compras.requisicion_compra_cumplida' => App\Services\Configuracion\Handlers\ComprasRequisicionCumplidaAvisoDespachoHandler::class,
        'compras.ordencompra_alertas_abiertas' => App\Services\Configuracion\Handlers\ComprasOrdencompraAlertasAbiertasAvisoHandler::class,
        'compras.entrega_dia_sin_recepcion' => App\Services\Configuracion\Handlers\ComprasEntregaDiaSinRecepcionAvisoHandler::class,
        'compras.ordencompra_contrato_sin_com' => App\Services\Configuracion\Handlers\ComprasOrdencompraContratoSinComAvisoHandler::class,
        'compras.ordencompra_contrato_vencimiento' => App\Services\Configuracion\Handlers\ComprasOrdencompraContratoVencimientoAvisoHandler::class,
        'compras.ordencompra_contrato_vencido' => App\Services\Configuracion\Handlers\ComprasOrdencompraContratoVencimientoAvisoHandler::class,
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
        'compras.contrato_validacion_abono_pendiente' => App\Services\Configuracion\Handlers\ComprasContratoValidacionAbonoPendienteAvisoHandler::class,
        'compras.suscripcion_comprobante_faltante' => App\Services\Configuracion\Handlers\ComprasSuscripcionComprobanteFaltanteAvisoHandler::class,
        'compras.suscripcion_comprobante_escalamiento' => App\Services\Configuracion\Handlers\ComprasSuscripcionComprobanteFaltanteAvisoHandler::class,
        'compras.aplicacion_cc_reclasificacion' => App\Services\Configuracion\Handlers\ComprasAplicacionCcReclasificacionAvisoHandler::class,
        'compras.precarga_cotizacion_invalida' => App\Services\Configuracion\Handlers\ComprasPrecargaCotizacionInvalidaAvisoHandler::class,
        'compras.precarga_prorrateo_multi_cc' => App\Services\Configuracion\Handlers\ComprasPrecargaProrrateoMultiCcAvisoHandler::class,
        'compras.comprobante_proveedor_borrador_pendiente' => App\Services\Configuracion\Handlers\ComprasComprobanteProveedorBorradorPendienteAvisoHandler::class,
        'compras.proveedor_padron_arca_suspension' => App\Services\Configuracion\Handlers\ComprasProveedorPadronArcaSuspensionAvisoHandler::class,
        'stock.transferencia_pendiente_aprobacion' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'stock.transferencia_confirmada' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'stock.transferencia_rechazada' => App\Services\Configuracion\Handlers\StockTransferenciaMercaderiaAvisoDespachoHandler::class,
        'ventas.pedido_produccion_alarma' => App\Services\Configuracion\Handlers\VentasPedidoProduccionAvisoHandler::class,
        'ventas.fce_emision' => App\Services\Configuracion\Handlers\VentasFceEmisionAvisoHandler::class,
        'ventas.ncl_descuento_cobranza' => App\Services\Configuracion\Handlers\VentasFceEmisionAvisoHandler::class,
        'ventas.cliente_suspension' => App\Services\Configuracion\Handlers\VentasClienteSuspensionAvisoDespachoHandler::class,
        'ventas.cliente_retenido_afip' => App\Services\Configuracion\Handlers\VentasClienteRetenidoAfipAvisoDespachoHandler::class,
        'arca.certificado_vencimiento' => App\Services\Configuracion\Handlers\ArcaCertificadoVencimientoAvisoHandler::class,
        'contable.apertura_periodo_habilitada' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_solicitud_pendiente' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_recordatorio' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.apertura_periodo_cerrada' => App\Services\Configuracion\Handlers\ContableAperturaPeriodoAvisoHandler::class,
        'contable.bien_uso_provisorio' => App\Services\Configuracion\Handlers\ContableBienUsoProvisorioAvisoHandler::class,
        'uif.cliente_alta' => App\Services\Configuracion\Handlers\UifClienteAltaAvisoHandler::class,
        'sueldos.empleado_alta_provisoria' => App\Services\Configuracion\Handlers\SueldosEmpleadoAltaProvisoriaAvisoHandler::class,
        'sueldos.empleado_baja_provisoria' => App\Services\Configuracion\Handlers\SueldosEmpleadoBajaProvisoriaAvisoHandler::class,
        'sueldos.libreta_sanitaria_pendiente' => App\Services\Configuracion\Handlers\SueldosLibretaSanitariaAvisoHandler::class,
        'caja.cheques_aging' => App\Services\Configuracion\Handlers\CajaChequesAgingAvisoHandler::class,
        'ticket.alta_tecnologia' => App\Services\Configuracion\Handlers\TicketAltaTecnologiaAvisoHandler::class,
        'ticket.asignacion_tecnico' => App\Services\Configuracion\Handlers\TicketAsignacionTecnicoAvisoHandler::class,
        'seguridad.ingreso_proveedor_creado' => App\Services\Configuracion\Handlers\SeguridadIngresoProveedorAvisoHandler::class,
        'seguridad.ingreso_proveedor_rechazado' => App\Services\Configuracion\Handlers\SeguridadIngresoProveedorAvisoHandler::class,
        'seguridad.ingreso_proveedor_recordatorio' => App\Services\Configuracion\Handlers\SeguridadIngresoProveedorAvisoHandler::class,
        'seguridad.ingreso_proveedor_abono_sin_cierre' => App\Services\Configuracion\Handlers\SeguridadIngresoProveedorContratoAvisoHandler::class,
        'listado.envios_workbench' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'consulta_inteligente.envios_programados' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'contable.reportes_definibles' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'sueldos.reportes_definibles' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'flash.distribuir_reportes' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'flash_crown.distribuir_reportes' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'arbolaprobacion.digest_pendientes' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'arbolaprobacion.recordatorios' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'tesoreria_crown.avisos' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ai.digest_brief' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ai.alerta_umbral' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.kilos_pedidos_manana_1' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.kilos_pedidos_manana_2' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.kilos_pedidos_tarde_parcial' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.kilos_pedidos_tarde_total' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.control_afip_pedidos' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'ventas.control_nc_descuento' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
        'stock.stkmov_int_diario' => App\Services\Configuracion\Handlers\ListadoEnviosProgramadosAvisoHandler::class,
    ],

];
