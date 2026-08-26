<?php

return [

    /*
    | Modo de aprobación de transferencias de mercadería:
    | - inmediata: salida + entrada en el mismo paso (sin bandeja pendiente)
    | - tipo_transaccion: usa tipotransaccion_stock.requiere_aprobacion
    | - siempre: toda transferencia exige aprobación del receptor
    */
    'transferencia_modo_aprobacion' => env('STOCK_TRANSFERENCIA_MODO_APROBACION', 'tipo_transaccion'),

    /** Horas de validez de enlaces de aprobación/rechazo por correo. */
    'transferencia_horas_validez_token' => (int) env('STOCK_TRANSFERENCIA_HORAS_TOKEN', 168),

    /*
    | Numerador secuencial de transferencia de mercadería (sistema_numerador).
    | Una sola semilla global: el codigo TR- es unique en toda la tabla.
    | empresa_id solo ubica la fila del numerador (no cambia el correlativo).
    */
    'transferencia_numerador_codigo' => env('STOCK_TRANSFERENCIA_NUMERADOR_CODIGO', 'stock.transferencia'),
    'transferencia_numerador_empresa_id' => (int) env('STOCK_TRANSFERENCIA_NUMERADOR_EMPRESA', 1),

    /*
    | Defaults de la pantalla ágil (Stock → Transferencia) para pickeo nocturno
    | depósito central → gastronomía. El usuario puede cambiarlos; se recuerdan
    | en cache. Códigos de depmae, no IDs (los IDs varían entre instalaciones).
    */
    'transferencia_pickeo' => [
        'tipo_abreviatura' => strtoupper(trim((string) env('STOCK_TRANSFERENCIA_TIPO_ABREVIATURA', 'TRA'))),
        'deposito_salida_codigo' => trim((string) env('STOCK_TRANSFERENCIA_DEPOSITO_SALIDA_CODIGO', '1')),
        'deposito_entrada_codigo' => trim((string) env('STOCK_TRANSFERENCIA_DEPOSITO_ENTRADA_CODIGO', '8')),
    ],

    /*
    | Ventana de edición de movimientos de stock.
    | true  → solo se pueden modificar o eliminar movimientos cuya fecha sea la del día.
    |          Los de fechas anteriores quedan únicamente con la opción Revertir (auditable).
    | false → edición/eliminación sin restricción de fecha (comportamiento histórico).
    | Para AGG queda activo.
    */
    'movimiento_edicion_solo_dia' => filter_var(env('STOCK_MOV_EDICION_SOLO_DIA', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | stkmov Anita legacy desactivado para procesos ERP de stock (mov. manual, transferencias,
    | recuento, préstamos, recepción proveedor COM). Facturación ventas sigue con su bridge.
    */
    /*
    | Sync depmae desde Anita por empresa (depmae:sincronizar-anita).
    | Omite codigos numericos > depmae_anita_codigo_maximo (maquinas/tragamonedas).
    */
    /** Campos stkagr list Anita (override). Vacío = stka_agrupacion. No incluir stka_rubro. */
    'categoria_anita_campos_listado' => env('STOCK_CATEGORIA_ANITA_CAMPOS_LISTADO', ''),

    /** Campos stkagr detalle Anita (override). Vacío = según EMPRESA. */
    'categoria_anita_campos_detalle' => env('STOCK_CATEGORIA_ANITA_CAMPOS_DETALLE', ''),

    /** Campos stkmae detalle Anita (override). Vacío = según config app.empresa. */
    'articulo_anita_campos_detalle' => env('STOCK_ARTICULO_ANITA_CAMPOS_DETALLE', ''),

    /** Artículos por llamada list stkmae en sync masivo (IN stkm_articulo). */
    'articulo_anita_lote_tamano' => (int) env('STOCK_ARTICULO_ANITA_LOTE_TAMANO', 200),

    'depmae_anita_codigo_maximo' => (int) env('STOCK_DEPMAE_ANITA_CODIGO_MAXIMO', 100000),

    /** Campos depmae en Anita (override por instalación). Vacío = según config app.empresa. */
    'depmae_anita_campos_listado' => env('STOCK_DEPMAE_ANITA_CAMPOS_LISTADO', ''),

    /** @var list<int> Orden de sync por empresa_id (ej. AGG: 1,2,3; INTERFORMING: 1). */
    'depmae_anita_empresas_sync' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('STOCK_DEPMAE_ANITA_EMPRESAS_SYNC', '1,2,3'))
    ), fn (int $id) => $id > 0)),

    'anita_stkmov' => [
        'habilitado' => filter_var(env('STOCK_ANITA_STKMOV_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'sistema_ventas' => env('STOCK_ANITA_STKMOV_SISTEMA', 'ventas'),
        // Sucursal virtual ERP: concat 99 + código empresa (1→991, 2→992). No numerador Anita legacy.
        'sucursal_erp' => (int) env('STOCK_ANITA_STKMOV_SUCURSAL_ERP', 99),
        'letra' => env('STOCK_ANITA_STKMOV_LETRA', 'X'),
        /*
        | Bridge Informix por empresa_id ERP para stkmov/stkmae (stock no centralizado).
        | Empresa 1 (Biyemas): bridge global ANITA_IP. Kandiko/Rebisco: hosts propios.
        | Override opcional: STOCK_ANITA_POR_EMPRESA JSON. Si vacío, usa gastronomia.ticket_tarjeta_anita_por_empresa.
        */
        'anita_por_empresa' => (static function (): array {
            $raw = env('STOCK_ANITA_POR_EMPRESA');
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return [];
            }
            $map = [];
            foreach ($decoded as $empresaId => $cfg) {
                if (is_array($cfg) && $cfg !== []) {
                    $map[(int) $empresaId] = $cfg;
                }
            }

            return $map;
        })(),

        'stkv_tipo_por_abreviatura' => [
            // Transferencias y ajustes manuales: solo ERP (sin stkmov Anita).
            'TRA' => null,
            'TAP' => null,
            'TBU' => null,
            'TBAP' => null,
            'FBU' => null,
            'TRCONT' => null,
            'TRS' => null,
            'TRE' => null,
            'AJCON' => null,
            'ENT' => null,
            'SAL' => null,
            'SAS' => null,
            // Recuento de inventario: ajustes solo en ERP (sin stkmov Anita).
            'RCAJP' => null,
            'RCAJN' => null,
            'RCAJR' => null,
            'NPUBJ' => null,
            'NPUAL' => null,
            // Préstamos: solo ERP (sin stkmov Anita).
            'PRSAL' => null,
            'PRING' => null,
            'PRRCH' => null,
            'PRDSL' => null,
            'PRDIN' => null,
        ],
    ],

    /*
    | Antigüedad de la fecha del recuento al cerrar en modo FECHA_RECUENTO.
    | - aviso: muestra alerta en pantalla / confirmación JS
    | - bloqueo: impide cerrar «a fecha del recuento» (sí permite «al saldo actual»)
    | Evita el error de cargar inventario con fecha de período vieja y reaplicar ajustes
    | sobre movimientos posteriores (saldos negativos).
    */
    'recuento_dias_aviso_fecha_antigua' => (int) env('STOCK_RECUENTO_DIAS_AVISO_FECHA_ANTIGUA', 3),
    'recuento_dias_bloqueo_fecha_antigua' => (int) env('STOCK_RECUENTO_DIAS_BLOQUEO_FECHA_ANTIGUA', 15),

    /*
    | Precio unitario de última compra (costo) para recuentos, transferencias y mov. stock.
    | Resolución en ArticuloPrecioUltimaCompraSupport:
    |   1) ERP: última recepción COM confirmada (historia OC o línea recepción)
    |   2) Anita stkmae.stkm_pre_compra3
    |   3) articulo.costo / articulo.ppp
    | TITO (promedio TRCONT): ArticuloPrecioPromedioCompraSupport
    |   1) exactamente 3 recepciones COM ERP → promedio en pesos usando
    |      moneda/cotización de Anita recepmov (recv_cod_mon / recv_cotizacion)
    |   2) Anita stkm_pre_compra1/2/3 → promedio
    */
    'precio_ultima_compra' => [
        'fuente_primaria' => 'erp_com',
        'fuente_secundaria' => 'anita',
    ],

    /*
    | Mail al contabilizar TRCONT con artículos TITO (precio promedio 3 compras).
    | Observer: Transferencia_MercaderiaObserver al asignar asiento_id.
    */
    'aviso_tito_trcont' => [
        'habilitado' => filter_var(env('STOCK_AVISO_TITO_TRCONT_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'destinatarios' => env(
            'STOCK_AVISO_TITO_TRCONT_DESTINATARIOS',
            'sergiogranucci@gmail.com,egalarza@grupoagg.com'
        ),
    ],

    /*
    | Precio sugerido en movimientos de stock manuales (ArticuloPrecioMovimientoStockSupport).
    | Salidas con estas abreviaturas → lista de precios de venta vigente.
    | Resto de tipos → última compra (misma cadena que precio_ultima_compra).
    */
    'precio_movimiento_salida_venta_abreviaturas' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STOCK_PRECIO_MOV_SALIDA_VENTA_ABREVS', 'SAL,SAS'))
    ), static fn (string $v) => $v !== '')),

    /*
    | Sync precios venta Anita stkpre → precio (precio:sincronizar-anita).
    | Filtro stkp_fe_ult_act mínimo Ymd; tras upsert deja solo la fila más vigente por artículo+lista.
    */
    'precio_anita_sync_desde' => env('STOCK_PRECIO_ANITA_SYNC_DESDE', '20250101'),

    /*
    | Pedido DESPACHO (El Bierzo): TM desde el pedido de reposición.
    | Vacío = se resuelve por abreviatura (TRA). El tipo define si pide aprobación.
    */
    'transferencia_despacho_tipotransaccion_stock_id' => env('STOCK_TRANSFERENCIA_DESPACHO_TIPOTRANSACCION_ID'),
    'transferencia_despacho_tipotransaccion_abreviatura' => env('STOCK_TRANSFERENCIA_DESPACHO_TIPOTRANSACCION_ABREVIATURA', 'TRA'),

];
