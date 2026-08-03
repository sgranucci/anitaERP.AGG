<?php

return [

    /**
     * Réplica rendición de máquinas en Informix (rendmaquina + rendvalor + rendmapgasto).
     * false = paralelo ERP sin grabar Anita; nro_oper serie local desde 1.
     * true  = sincroniza Anita; nro_oper = max(Anita unificado, ERP) + 1.
     */
    'sincronizar' => filter_var(
        env('RENDICION_MAQUINA_SINCRONIZAR_ANITA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sistema' => env('RENDICION_MAQUINA_ANITA_SISTEMA', 'caja'),

    'tabla_cabecera' => env('RENDICION_MAQUINA_ANITA_TABLA', 'rendmaquina'),

    'tabla_valor' => env('RENDICION_MAQUINA_ANITA_TABLA_VALOR', 'rendvalor'),

    'tabla_gasto' => env('RENDICION_MAQUINA_ANITA_TABLA_GASTO', 'rendmapgasto'),

    /** Remesas (REMEM_lee_remesa_interna → vale_rep_fondo en turno mañana). */
    'tabla_remesa' => env('RENDICION_MAQUINA_ANITA_TABLA_REMESA', 'rememae'),

    /**
     * Producción Anita usa 'F' (igual gastronomía/bingo/vending).
     * El C histórico mapeaba EFE_FINAL→'1'; no se observa en datos actuales.
     */
    'tipo_oper' => env('RENDICION_MAQUINA_ANITA_TIPO_OPER', 'F'),

    /** Estado pendiente de cierre contable (espacio en blanco). */
    'estado_pendiente' => env('RENDICION_MAQUINA_ANITA_ESTADO_PENDIENTE', ' '),

    /**
     * Columnas Informix rendmapgasto (prefijo histórico renmap_).
     * Ajustables si el bridge reporta columnas distintas.
     */
    'gasto_col_nro_oper' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_NRO', 'renmap_nro_oper'),
    'gasto_col_orden' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_ORDEN', 'renmap_orden'),
    'gasto_col_codigo' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_CODIGO', 'renmap_codigo'),
    'gasto_col_importe' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_IMPORTE', 'renmap_importe'),

    /** Caja Informix por defecto cuando no hay otra fuente. */
    'caja_id_default_por_empresa' => [
        1 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_1', 12),
        2 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_2', 12),
        3 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_3', 12),
        4 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_4', 12),
    ],

    /**
     * Cierre contable diario (módulo Contabilidad): empresa + fecha jornada turno C.
     * Punto de venta (sucursal) solo para FSL exenta del día.
     * Fuente: p-vtamaquina.c
     */
    'cierre_rendicion_contable' => [
        'tipo_comprobante' => env('RENDICION_MAQUINA_CIERRE_TIPO_COMPROBANTE', 'FSL'),
        'letra_comprobante' => env('RENDICION_MAQUINA_CIERRE_LETRA_COMPROBANTE', 'B'),
        'cliente_codigo' => env('RENDICION_MAQUINA_CIERRE_CLIENTE_CODIGO', '000000'),
        'cliente_nombre' => env('RENDICION_MAQUINA_CIERRE_CLIENTE_NOMBRE', 'Sala de máquinas'),
        'tipoasiento_abreviatura' => env('RENDICION_MAQUINA_CIERRE_TIPOASIENTO_ABREVIATURA', 'MAQ'),
        /**
         * Punto de venta / sucursal FSL por empresa.
         *
         * @var array<int, int>
         */
        'puntoventa_por_empresa' => (static function (): array {
            $raw = env('RENDICION_MAQUINA_CIERRE_PUNTOVENTA_POR_EMPRESA');
            if ($raw !== null && $raw !== '') {
                $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
                if (is_array($decoded)) {
                    $map = [];
                    foreach ($decoded as $empresaId => $pv) {
                        $map[(int) $empresaId] = (int) $pv;
                    }

                    return $map;
                }
            }

            return [
                1 => 39, // Biyemas
                2 => 26, // Kandiko
                3 => 14, // Rebisco
            ];
        })(),
        'estado_facturado_anita' => env('RENDICION_MAQUINA_CIERRE_ESTADO_FACTURADO_ANITA', 'F'),
        'conciliacion_flash_tolerancia' => (float) env('RENDICION_MAQUINA_CIERRE_FLASH_TOLERANCIA', 0.02),
        'canon_loteria_porcentaje' => (float) env('RENDICION_MAQUINA_CIERRE_CANON_LOTERIA_PCT', 34),
        'canon_hospital_porcentaje' => (float) env('RENDICION_MAQUINA_CIERRE_CANON_HOSPITAL_PCT', 1),
    ],

];
