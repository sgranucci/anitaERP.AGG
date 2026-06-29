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
    | stkmov Anita para movimientos originados en anitaERP (transferencias, recuento, préstamo, etc.).
    | Clave aislada: stkv_sucursal fijo + stkv_nro = movimientostock.id (no numerador Informix).
    | Recepción proveedor (COM) y facturación ventas siguen con su bridge propio.
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

    'depmae_anita_codigo_maximo' => (int) env('STOCK_DEPMAE_ANITA_CODIGO_MAXIMO', 100000),

    /** Campos depmae en Anita (override por instalación). Vacío = según config app.empresa. */
    'depmae_anita_campos_listado' => env('STOCK_DEPMAE_ANITA_CAMPOS_LISTADO', ''),

    /** @var list<int> Orden de sync por empresa_id (ej. AGG: 1,2,3; INTERFORMING: 1). */
    'depmae_anita_empresas_sync' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('STOCK_DEPMAE_ANITA_EMPRESAS_SYNC', '1,2,3'))
    ), fn (int $id) => $id > 0)),

    'anita_stkmov' => [
        'habilitado' => filter_var(env('STOCK_ANITA_STKMOV_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
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
            // Transferencias: el servicio pasa TRS/TRE explícito (salida/entrada).
            'TRA' => null,
            'TAP' => null,
            'TBU' => null,
            'TBAP' => null,
            'FBU' => null,
            'TRCONT' => null,
            'RCAJP' => 'RCP',
            'RCAJN' => 'RCN',
            'RCAJR' => 'RCR',
            'PRSAL' => 'PRS',
            'PRING' => 'PRI',
            'PRRCH' => 'PRR',
            'PRDSL' => 'PRD',
            'PRDIN' => 'PRV',
            'AJCON' => 'AJC',
        ],
    ],

    /*
    | Precio unitario de última compra (costo) para recuentos, transferencias y mov. stock.
    | Resolución en ArticuloPrecioUltimaCompraSupport:
    |   1) Anita stkmae.stkm_pre_compra3
    |   2) ERP: última recepción COM confirmada (historia OC o línea recepción)
    |   3) articulo.costo / articulo.ppp
    */
    'precio_ultima_compra' => [
        'fuente_primaria' => 'anita',
        'fuente_secundaria' => 'erp_com',
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

];
