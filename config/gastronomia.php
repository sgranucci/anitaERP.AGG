<?php

/**
 * Gastronomía — proceso de facturación en salón.
 *
 * Nota sobre contabilidad al facturar: {@see \App\Services\Ventas\FacturacionService} graba siempre
 * el asiento desde grabaFacturaERP. Si AGG debe omitir contabilidad por factura, hace falta extender
 * dicho servicio o duplicar grabación controlada; la clave sirve para procesos futuros / scripts.
 */
return [
    /**
     * Identificador fijo cuando no se usa IP del cliente (env explícito o hostname del servidor PHP).
     * Coincide con configuracion_puntoventa_gastronomia.identificador_pc en ese modo.
     */
    'identificador_pc' => env('GASTRONOMIA_IDENTIFICADOR_PC', (string) gethostname()),

    /**
     * true = el identificador efectivo es la IP del cliente ($request->ip()), para distinguir PCs en LAN.
     * Configure TrustProxies / proxies si hay nginx o balanceador (X-Forwarded-For).
     * En BD, configuracion_puntoventa_gastronomia.identificador_pc debe ser esa misma IP (texto).
     */
    'identificador_pc_usar_ip_cliente' => filter_var(env('GASTRONOMIA_IDENTIFICADOR_USAR_IP_CLIENTE', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Si es false, no se graba asiento contable al facturar desde gastronomía.
     * La cuenta corriente del cliente no se genera al facturar ni al cobrar desde este módulo (cobro en el momento vía caja).
     */
    'genera_contabilidad_al_facturar' => filter_var(env('GASTRONOMIA_GENERA_CONTABILIDAD_FACTURA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Exige jornada abierta por empresa para facturar / abrir cuentas en el POS gastronómico.
     */
    'jornada_obligatoria' => filter_var(env('GASTRONOMIA_JORNADA_OBLIGATORIA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Auto-descarte de cuentas ABIERTA sin ítems al cerrar último turno del día o jornada.
     * Actualización por lotes + retry ante deadlock (1213) / lock wait timeout (1205).
     */
    'auto_descarte_vacias' => [
        'tamano_lote' => max(1, (int) env('GASTRONOMIA_AUTO_DESCARTE_LOTE', 100)),
        'max_iteraciones' => max(1, (int) env('GASTRONOMIA_AUTO_DESCARTE_MAX_ITER', 500)),
        'reintentos_deadlock' => max(1, (int) env('GASTRONOMIA_AUTO_DESCARTE_REINTENTOS', 5)),
        'espera_reintento_ms' => max(50, (int) env('GASTRONOMIA_AUTO_DESCARTE_ESPERA_MS', 150)),
    ],

    /**
     * true = habilitación de turno por PC antes de facturar (AGG).
     * false = caja directo: sin habilitación/cierre de turno operativo.
     */
    'requiere_habilitacion_turno' => filter_var(env('GASTRONOMIA_REQUIERE_HABILITACION_TURNO', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Si es false, la cobranza registrada desde el POS no genera asiento contable de tesorería.
     */
    'genera_contabilidad_al_cobrar' => filter_var(env('GASTRONOMIA_GENERA_CONTABILIDAD_COBRANZA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Respaldo si la configuración del punto de venta gastronomía no define tipotransaccion_caja_id.
     */
    'tipotransaccion_caja_id' => env('GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID'),

    /**
     * Al recuperar un comprobante autorizado en ARCA (rollback/deadlock) genera la cobranza en
     * efectivo por el total, para que el cierre de jornada cuadre los medios de pago (la factura
     * quedaba sin cobranza y descuadraba el asiento vs Diferencia de caja). Cortesías $0,01 se omiten.
     */
    'recuperacion_arca_genera_cobranza_efectivo' => filter_var(env('GASTRONOMIA_RECUPERACION_ARCA_GENERA_COBRANZA_EFECTIVO', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Tras recuperar ARCA: si el cierre de jornada Waitry ya grabó el asiento «ventas_medio_real» de esa
     * fecha, recalcularlo completo (ERP + Anita ctamov) incluyendo la factura y cobranza nuevas.
     */
    'recuperacion_arca_actualiza_asiento_cierre_jornada' => filter_var(env('GASTRONOMIA_RECUPERACION_ARCA_ACTUALIZA_ASIENTO_CIERRE_JORNADA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Respaldo si la configuración del punto de venta gastronomía no define tipotransaccion_id.
     * Preferir el campo en configuracion_puntoventa_gastronomia (por terminal).
     */
    'tipotransaccion_factura_id' => env('GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID'),

    /**
     * Respaldo si la configuración del punto de venta gastronomía no define tipotransaccion_nota_credito_id.
     */
    'tipotransaccion_nota_credito_id' => env('GASTRONOMIA_TIPO_TRANSACCION_NOTA_CREDITO_ID'),

    /**
     * Tipo de transacción de caja para devolución de factura (nota de crédito) desde gastronomía.
     * Debe estar configurado con signo Egreso para que los importes se graben en negativo
     * y las consultas de saldos resten automáticamente.
     */
    'tipotransaccion_caja_devolucion_id' => (int) env('GASTRONOMIA_TIPO_TRANSACCION_CAJA_DEVOLUCION_ID', 3),

    /** Prefijo SKU para catálogo rápido en el POS (ej. consumibles venta salón). */
    'sku_catalogo_prefijo' => env('GASTRONOMIA_SKU_CATALOGO_PREFIJO', 'V'),

    /**
     * Sincronización puntual de precios Anita → ERP (stkpre) para SKU catálogo sin precio vigente.
     * Comando: php artisan gastronomia:sincronizar-precios-lista-anita
     */
    'precio_lista_sync' => [
        /** 0 = usar LISTAPRECIO_DEFAULT_ID (lista 1 por defecto). */
        'listaprecio_id' => (int) env('GASTRONOMIA_PRECIOS_LISTA_ID', 0),
    ],

    /**
     * Cantidad de dígitos numéricos tras el prefijo en el catálogo gastronomía (ej. 5 → se ingresa solo "00123" y el sistema arma V00123).
     * 0 = campo SKU completo (sin prefijo visual fijo); solo dígitos si es > 0.
     */
    'sku_catalogo_digitos_sufijo' => (int) env('GASTRONOMIA_SKU_CATALOGO_DIGITOS_SUFIJO', 0),

    /**
     * Uso de cuenta de caja para medios de cobro en el POS (tabla usocuentacaja).
     * Si no se define, se busca por nombre "Gastronomia".
     */
    'usocuentacaja_id' => env('GASTRONOMIA_USO_CUENTACAJA_ID'),

    /** Moneda por defecto al emitir factura si no hay líneas de cobranza cargadas. */
    'moneda_factura_id' => env('GASTRONOMIA_MONEDA_FACTURA_ID'),

    /**
     * Condición IIBB del maestro (formacalculo N) para cálculo de impuestos cuando la cuenta factura como consumidor final.
     * Por defecto 4 = "No percibe".
     */
    'consumidor_final_condicioniibb_id' => (int) env('GASTRONOMIA_CONSUMIDOR_FINAL_CONDICIONIIBB_ID', 4),

    /**
     * Impuesto maestro sin IVA (tabla impuesto, valor 0) para factura de cortesía ($0,01, descuento 100 %).
     */
    'impuesto_exento_id' => (int) env('GASTRONOMIA_IMPUESTO_EXENTO_ID', 1),

    /**
     * Bridge hacia Informix legacy (respaldo si env() no carga en el proceso PHP).
     */
    'anita_bridge_type' => env('ANITA_BRIDGE_TYPE', 'HTTP'),

    /**
     * Replica la venta en Informix legacy vía bridge HTTP al facturar desde gastronomía.
     */
    'sincronizar_anita_al_facturar' => filter_var(env('GASTRONOMIA_SINCRONIZAR_ANITA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Desde esta fecha (inclusive) las ventas POS no se replican en Informix; conciliación solo ERP ↔ rendg.
     * Override por empresa en ventas_solo_erp_desde_por_empresa.
     */
    'ventas_solo_erp_desde' => env('GASTRONOMIA_VENTAS_SOLO_ERP_DESDE', '2026-07-01'),

    /**
     * @var array<int, string>
     */
    'ventas_solo_erp_desde_por_empresa' => [
        1 => env('GASTRONOMIA_VENTAS_SOLO_ERP_DESDE', '2026-07-01'),
        2 => env('GASTRONOMIA_VENTAS_SOLO_ERP_KANDIKO', env('GASTRONOMIA_VENTAS_SOLO_ERP_DESDE', '2026-07-01')),
        3 => env('GASTRONOMIA_VENTAS_SOLO_ERP_REBISCO', env('GASTRONOMIA_VENTAS_SOLO_ERP_DESDE', '2026-07-01')),
    ],

    /**
     * Gastronomía: en Anita venta + vengrav + vencae; omite comprob, compaux, venibr, climov.
     * stkmov por plato: anita_omitir_stkmov (solo gastronomía; Ventas/administración no usa esta clave).
     * Insumos por fórmula: anita_replicar_insumos_al_facturar.
     */
    'anita_modo_minimo' => filter_var(env('GASTRONOMIA_ANITA_MODO_MINIMO', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Gastronomía: no grabar stkmov por ítem/plato en Informix al replicar Anita (cola, backfill, auditoría).
     * false = comportamiento legacy con stkmov por línea. No afecta facturación Ventas/administración.
     */
    'anita_omitir_stkmov' => filter_var(env('GASTRONOMIA_ANITA_OMITIR_STKMOV', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Al facturar (y NC gastronomía): replica en Informix vía bridge cada stkmov de insumo de fórmula
     * (GastronomiaInsumoStkmovAnitaService, 1 HTTP por línea). false = solo stock ERP; no bloquea el POS.
     * Faltantes en Anita: auditoría diaria si GASTRONOMIA_AUDITORIA_ANITA_REPLICAR_INSUMOS=true.
     */
    'anita_replicar_insumos_al_facturar' => filter_var(env('GASTRONOMIA_ANITA_REPLICAR_INSUMOS_AL_FACTURAR', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Tras commit de la emisión gastronomía: replica venta + vencae en Informix sin bloquear cobranza/locks MySQL.
     * CAE/ARCA sigue dentro de la transacción (rollback si falla).
     */
    'anita_tras_commit_al_facturar' => filter_var(env('GASTRONOMIA_ANITA_TRAS_COMMIT_AL_FACTURAR', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Anita (venta + vencae + insumos opcionales) después de responder al POS.
     * Insumos stkmov: GASTRONOMIA_ANITA_REPLICAR_INSUMOS_AL_FACTURAR (default true en config, false en prod).
     */
    'anita_tras_respuesta' => filter_var(env('GASTRONOMIA_ANITA_TRAS_RESPUESTA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Replica Anita en cola Laravel (ReplicarAnitaGastronomiaJob) en lugar de terminating() en Apache.
     * Requiere QUEUE_CONNECTION=database|redis y worker supervisor activo.
     */
    'anita_en_cola' => filter_var(env('GASTRONOMIA_ANITA_EN_COLA', true), FILTER_VALIDATE_BOOLEAN),
    'anita_cola' => env('GASTRONOMIA_ANITA_COLA', 'default'),
    'anita_job_tries' => max(1, (int) env('GASTRONOMIA_ANITA_JOB_TRIES', 3)),
    'anita_job_backoff_segundos' => [60, 300, 900],
    'anita_job_timeout' => max(60, (int) env('GASTRONOMIA_ANITA_JOB_TIMEOUT', 300)),

    /**
     * Auditoría ERP ↔ Anita diaria (06:30): venta.fechajornada, gastronomía + estacionamiento.
     * Replica faltantes vía bridge y alerta por mail.
     */
    'auditoria_anita_diaria' => [
        'habilitada' => filter_var(env('GASTRONOMIA_AUDITORIA_ANITA_DIARIA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('GASTRONOMIA_AUDITORIA_ANITA_HORA', '06:30'),
        'empresa_id' => (int) env('GASTRONOMIA_AUDITORIA_ANITA_EMPRESA_ID', 1),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GASTRONOMIA_AUDITORIA_ANITA_EMPRESAS_IDS', '1,2,3')),
        ))),
        'usuario_id' => (int) env('GASTRONOMIA_AUDITORIA_ANITA_USUARIO_ID', 1),
        'email' => env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com'),
        'tolerancia' => (float) env('GASTRONOMIA_AUDITORIA_ANITA_TOLERANCIA', 0.02),
        'replicar_insumos' => filter_var(env('GASTRONOMIA_AUDITORIA_ANITA_REPLICAR_INSUMOS', true), FILTER_VALIDATE_BOOLEAN),
        'email_si_ok' => filter_var(env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL_SI_OK', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * Reporte mensual solo Anita (venta/vengrav/ctamov/rendg) — CSV por mail.
     */
    'auditoria_mes_totales_anita' => [
        'email' => env('GASTRONOMIA_AUDITORIA_MES_ANITA_EMAIL', env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
    ],

    /**
     * Cuadre jornada: contabilidad ctamov vs rendiciones (Z−NC) vs venta Informix vs venta ERP vs flash (caja).
     * Comando: gastronomia:control-ctamov-rendg-dia-anita
     */
    'control_ctamov_rendg_dia_anita' => [
        'tolerancia' => (float) env('GASTRONOMIA_CONTROL_CTAMOV_RENDG_TOLERANCIA', 2.0),
        /** Base Informix del bridge para tabla flash (Σ flash_ayb + flash_estac). */
        'flash_sistema' => env('GASTRONOMIA_CONTROL_CUADRE_JORNADA_FLASH_SISTEMA', 'caja'),
        /** Directorio CSV del cuadre jornada (relativo a storage/app o ruta absoluta). */
        'directorio_reportes' => env('GASTRONOMIA_CONTROL_CUADRE_JORNADA_DIR', 'reportes/gastronomia/cuadre_jornada'),
        'email' => env('GASTRONOMIA_CONTROL_CUADRE_JORNADA_EMAIL', env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
        /** @var array<int|string, list<int>> empresa_id => códigos ctav_cuenta (sin IVA; se suma cuenta_iva del cierre) */
        'cuentas_ventas_por_empresa' => [
            1 => [413010001, 414010001, 415010003, 414020001],
            2 => [413010001, 414010001, 415010003, 414020001],
            3 => [413010001, 414010001, 415010003, 414020001],
        ],
        /** @var array<int|string, list<int>> IVA crédito fiscal (114010-011: reversa IVA NC estacionamiento en ctamov) */
        'cuentas_iva_credito_fiscal_por_empresa' => [
            1 => [114010011],
            2 => [114010011],
            3 => [114010011],
        ],
    ],

    /**
     * Reporte conciliación jornada: ventas ERP vs Anita vs rendgastro Z por PV (CSV por mail).
     * Schedule después de auditorías nocturnas (@ GASTRONOMIA_CONCILIACION_DIARIA_HORA).
     */
    'conciliacion_diaria_reporte' => [
        'habilitada' => filter_var(env('GASTRONOMIA_CONCILIACION_DIARIA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('GASTRONOMIA_CONCILIACION_DIARIA_HORA', '09:00'),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GASTRONOMIA_CONCILIACION_DIARIA_EMPRESAS_IDS', '1,2,3')),
        ))),
        'email' => env('GASTRONOMIA_CONCILIACION_DIARIA_EMAIL', env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
        'tolerancia' => (float) env('GASTRONOMIA_CONCILIACION_DIARIA_TOLERANCIA', 0.02),
        /**
         * Jornada mínima por empresa_id (Y-m-d). Anterior = pre-migración ERP; no conciliar ni alertar.
         * Biyemas gastronomía ERP desde 2026-06-01; Kandiko desde 2026-06-08; Rebisco desde 2026-06-10.
         */
        'fecha_jornada_desde_por_empresa' => [
            1 => env('GASTRONOMIA_CONCILIACION_BIYEMAS_JORNADA_DESDE', '2026-06-01'),
            2 => env('GASTRONOMIA_CONCILIACION_KANDIKO_JORNADA_DESDE', '2026-06-08'),
            3 => env('GASTRONOMIA_CONCILIACION_REBISCO_JORNADA_DESDE', '2026-06-10'),
        ],
        /** Circuito vending ERP↔rendgastro: jornadas anteriores no se auditan (pre go-live rendiciones ERP). */
        'vending_jornada_desde' => env('GASTRONOMIA_CONCILIACION_VENDING_JORNADA_DESDE', '2026-06-28'),
        /** Cache Anita bulk (storage/app/anita_audit_cache) en lugar de N consultas live por PV×día. */
        'usar_cache_anita' => filter_var(env('GASTRONOMIA_CONCILIACION_USAR_CACHE_ANITA', true), FILTER_VALIDATE_BOOLEAN),
        /** Re-descarga cache Anita al generar el reporte (1 bulk por empresa/rango; evita cache stale). */
        'refrescar_cache_anita' => filter_var(env('GASTRONOMIA_CONCILIACION_REFRESCAR_CACHE_ANITA', true), FILTER_VALIDATE_BOOLEAN),
        /** Reintentos bridge cuando usar_cache_anita=false o fallback live. */
        'anita_reintentos_bridge' => max(1, (int) env('GASTRONOMIA_CONCILIACION_ANITA_REINTENTOS_BRIDGE', 3)),
        /** Control flash (Informix caja: flash_ayb + flash_estac) vs rendgastro neto por unidad de negocio (AyB / estacionamiento). */
        'control_flash_habilitado' => filter_var(env('GASTRONOMIA_CONCILIACION_CONTROL_FLASH_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        /**
         * Días hacia atrás respecto de la jornada del reporte para el cuadro FLASH.
         * Default 1: el Informix flash del mismo día suele demorar; se concilia flash/rendg/ERP del día anterior.
         */
        'control_flash_jornada_offset_dias' => max(0, (int) env('GASTRONOMIA_CONCILIACION_CONTROL_FLASH_OFFSET_DIAS', 1)),
    ],

    /**
     * Waitry (comanda / sync pago) después de responder al POS; no bloquea emitir-factura.
     */
    'waitry_tras_respuesta' => filter_var(env('GASTRONOMIA_WAITRY_TRAS_RESPUESTA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Registra tiempos por etapa en log (gastronomia.emision.profile) al emitir factura.
     */
    'emision_profile' => filter_var(env('GASTRONOMIA_EMISION_PROFILE', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Incluye el desglose de tiempos en la respuesta JSON de emitir-factura (requiere emision_profile).
     */
    'emision_profile_en_respuesta' => filter_var(env('GASTRONOMIA_EMISION_PROFILE_EN_RESPUESTA', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Si el total de emisión supera este umbral (ms), se loguea gastronomia.emision.lento con las etapas más costosas.
     */
    'emision_umbral_advertencia_ms' => max(0, (int) env('GASTRONOMIA_EMISION_UMBRAL_ADVERTENCIA_MS', 10000)),

    /**
     * Cuenta de caja de efectivo por empresa para efectivizar (F5): cobro en efectivo + factura + impresión.
     * Clave = empresa_id, valor = cuentacaja_id.
     * Alternativa: env JSON GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA='{"1":42,"2":15}' (fusiona/sobrescribe).
     *
     * @var array<int, int>
     */
    /**
     * Segundos máximos que una emisión retiene el candado del PV (factura + cobranza + ARCA).
     * Mientras dura, otra sesión sobre el mismo puntoventa_id no puede facturar.
     */
    'emision_lock_segundos' => (int) env('GASTRONOMIA_EMISION_LOCK_SEGUNDOS', 180),

    /**
     * Al abrir mesa o cuenta libre: exige cubiertos > 0 si es true.
     */
    'cubiertos_obligatorio_al_abrir' => filter_var(env('GASTRONOMIA_CUBIERTOS_OBLIGATORIO_AL_ABRIR', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Valor de cubiertos si no se ingresa ninguno al abrir (cuando no es obligatorio o el campo queda vacío).
     */
    'cubiertos_default_al_abrir' => max(0, (int) env('GASTRONOMIA_CUBIERTOS_DEFAULT_AL_ABRIR', 1)),

    /**
     * Al abrir mesa o cuenta libre: exige mozo asignado si es true.
     */
    'mozo_obligatorio_al_abrir' => filter_var(env('GASTRONOMIA_MOZO_OBLIGATORIO_AL_ABRIR', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Habilita modo «Cuentas libres» en el POS y el atajo + para nueva cuenta.
     */
    'cuentas_libres_habilitadas' => filter_var(env('GASTRONOMIA_CUENTAS_LIBRES_HABILITADAS', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Tras emitir en el POS: imprimir ticket térmico vía salida_factura (comando con %s).
     * El PDF (listaunafactura) queda para consulta y reimpresión manual.
     */
    'ticket_impresion_automatica' => filter_var(env('GASTRONOMIA_TICKET_IMPRESION_AUTOMATICA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Imprimir el ticket térmico DESPUÉS de responder al POS (Laravel defer()): no bloquea
     * la liberación de la terminal aunque la impresora esté lenta o el comando demore hasta
     * el timeout. Si falla, se loguea en gastronomia.ticket_factura.defer.*.
     * Desactivar (=false) restaura el comportamiento sincrónico previo.
     */
    'ticket_impresion_async' => filter_var(env('GASTRONOMIA_TICKET_IMPRESION_ASYNC', true), FILTER_VALIDATE_BOOLEAN),

    /** Ancho en caracteres del papel (80 mm ≈ 42). */
    'ticket_ancho_caracteres' => max(32, (int) env('GASTRONOMIA_TICKET_ANCHO', 42)),

    /** Codificación de caracteres para ESC/POS (ISO-8859-1 o UTF-8 según impresora). */
    'ticket_codificacion' => env('GASTRONOMIA_TICKET_CODIFICACION', 'ISO-8859-1'),

    /** Tamaño del QR en impresora Epson (1–8). */
    'ticket_qr_size' => max(1, min(8, (int) env('GASTRONOMIA_TICKET_QR_SIZE', 6))),

    /** Timeout del comando de salida (segundos). */
    'ticket_comando_timeout_segundos' => max(5, (int) env('GASTRONOMIA_TICKET_COMANDO_TIMEOUT', 30)),

    /**
     * Guarda copia legible (.txt) en storage/app/gastronomia/tickets/preview/ (pruebas sin impresora).
     * Por defecto activo en APP_ENV=local.
     */
    'ticket_guardar_preview' => filter_var(
        env('GASTRONOMIA_TICKET_GUARDAR_PREVIEW', env('APP_ENV', 'production') === 'local'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Viandas — al marchar la comanda, mostrar el voucher en pantalla (preview + botón
     * Reimprimir) antes de liberar la terminal.
     *   false (default): imprime directo a la impresora (como facturación) y vuelve al login
     *                    del próximo empleado; solo muestra el comprobante si la impresión falla.
     *   true:            muestra el voucher en pantalla con la vista previa y el botón imprimir.
     */
    'vianda_voucher_preview_pantalla' => filter_var(
        env('VIANDA_VOUCHER_PREVIEW_PANTALLA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Días de validez del ticket tarjeta gastronomía desde la fecha de emisión (ifecha en Anita).
     */
    'ticket_tarjeta_vencimiento_dias' => max(1, (int) env('GASTRONOMIA_TICKET_TARJETA_VENCIMIENTO_DIAS', 30)),

    /**
     * Tolerancia ($) por la cual el importe del ticket puede superar el saldo pendiente de la factura.
     * En ese caso la cobranza se registra por el saldo pendiente y el ticket se canjea igualmente.
     */
    'ticket_tarjeta_tolerancia_excedente_factura' => max(0., (float) env('GASTRONOMIA_TICKET_TARJETA_TOLERANCIA_EXCEDENTE', 5.)),

    /** Código de cuenta de caja para canje ticket tarjeta (uso Gastronomía). */
    'ticket_tarjeta_cuentacaja_codigo' => env('GASTRONOMIA_TICKET_TARJETA_CUENTACAJA_CODIGO', 'CTG'),

    /** Cuenta de caja para órdenes Waitry ya cobradas en el tótem (cobranza automática bloqueada en POS). */
    'cuentacaja_totem_codigo' => env('GASTRONOMIA_CUENTACAJA_TOTEM_CODIGO', 'TOTEM'),

    /** Base Informix donde está la tabla tickettarj (bridge Anita). */
    'ticket_tarjeta_anita_sistema' => env('GASTRONOMIA_TICKET_TARJETA_ANITA_SISTEMA', 'base_admin'),

    /** Base Informix donde está clivipg (clientes VIP canjes). Reutiliza bridge por empresa de tickettarj. */
    'cliente_vip_anita_sistema' => env('GASTRONOMIA_CLIENTE_VIP_ANITA_SISTEMA', 'base_admin'),
    'cliente_vip_anita_tabla' => env('GASTRONOMIA_CLIENTE_VIP_ANITA_TABLA', 'clivipg'),
    'cliente_vip_anita_campos_listado' => env(
        'GASTRONOMIA_CLIENTE_VIP_ANITA_CAMPOS_LISTADO',
        'inumeroid,cnrodocumento,capellido,cnombre,iusualtaid,ifechaalta,choraalta,iusuumodid,ifechaumod,choraumod,clivig_nickname,clivig_localidad'
    ),

    /**
     * Orden de importación inicial desde Anita: 1=Biyemas, 2=Kandiko, 3=Rebisco.
     *
     * @var list<int>
     */
    'cliente_vip_anita_empresas_sync' => [1, 2, 3],

    /**
     * Bridge Anita por empresa para canje CTG (tickettarj). Clave = empresa_id.
     * Biyemas (1) usa ANITA_IP + IFX_SERVER global; Kandiko/Rebisco host e IFX propios.
     * Sistema tickettarj: GASTRONOMIA_TICKET_TARJETA_ANITA_SISTEMA (base_admin) para las 3 empresas.
     * Override: env JSON GASTRONOMIA_TICKET_TARJETA_ANITA_POR_EMPRESA.
     *
     * @var array<int, array<string, string>>
     */
    'ticket_tarjeta_anita_por_empresa' => (static function (): array {
        $raw = env('GASTRONOMIA_TICKET_TARJETA_ANITA_POR_EMPRESA');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $empresaId => $cfg) {
                    if (is_array($cfg) && $cfg !== []) {
                        $map[(int) $empresaId] = $cfg;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [
            2 => [
                'servidor' => '192.168.20.100:8080',
                'path_sistema' => '/usr2/biyemas',
                'ifx_server' => 'kancadmin',
            ],
            3 => [
                'servidor' => '192.168.40.100:8080',
                'path_sistema' => '/usr2/biyemas',
                'ifx_server' => 'rencadmin',
            ],
        ];
    })(),

    /**
     * Al cerrar jornada gastronomía: registrar rango de órdenes Waitry del tótem (waitry_order_id)
     * vía getordersdetails + PDF. Requiere WAITRY_HABILITADO. Siguiente cierre: id &gt; último hasta guardado.
     */
    'cierre_totem_jornada_habilitado' => filter_var(env('GASTRONOMIA_CIERRE_TOTEM_JORNADA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    /** Máximo de órdenes Waitry nuevas por cierre (protección de memoria). */
    'cierre_totem_jornada_max_ordenes' => max(100, (int) env('GASTRONOMIA_CIERRE_TOTEM_MAX_ORDENES', 15000)),

    /** Máximo de líneas en el detalle JSON / PDF del comprobante. */
    'cierre_totem_jornada_max_lineas_detalle' => max(100, (int) env('GASTRONOMIA_CIERRE_TOTEM_MAX_LINEAS_DETALLE', 3000)),

    /**
     * Obsoleto: ya no se recuperan huecos con getOrdersPOS al cerrar jornada (solo getordersdetails).
     * Los huecos quedan en ids_huecos_secuencia para auditoría del día.
     */
    'cierre_totem_jornada_max_ids_gap_recuperar' => 0,

    /** Consultas getOrdersPOS por orderId si getordersdetails no trae payment.type (0 = solo bulk, recomendado). */
    'cierre_totem_enriquecer_payment_individual_max' => max(
        0,
        (int) env('GASTRONOMIA_CIERRE_TOTEM_ENRIQUECER_PAYMENT_INDIVIDUAL_MAX', 0),
    ),

    /**
     * Si no hay cierre_en al armar el cierre, tope de madrugada del día siguiente (hora local) para ventana Waitry.
     */
    'cierre_totem_jornada_hora_corte_madrugada' => max(0, min(23, (int) env('GASTRONOMIA_CIERRE_TOTEM_HORA_CORTE_MADRUGADA', 7))),

    /** Tolerancia $ al comparar Informe Z del tótem vs totales Waitry/ERP. */
    'cierre_totem_informe_z_tolerancia' => max(0.0, (float) env('GASTRONOMIA_CIERRE_TOTEM_INFORME_Z_TOLERANCIA', 0.02)),

    /**
     * Cuentas contables por defecto del proceso de cierre de jornada (tabla gastronomia_cierre_jornada_config por empresa).
     */
    'cierre_jornada_cuenta_ventas_id' => env('GASTRONOMIA_CIERRE_JORNADA_CUENTA_VENTAS_ID'),
    'cierre_jornada_cuenta_iva_id' => env('GASTRONOMIA_CIERRE_JORNADA_CUENTA_IVA_ID'),
    /** Haber ventas kiosco / cigarrillos (gravado + imp. interno) en asientos del cierre Waitry. */
    'cierre_jornada_cuenta_ventas_kiosco_id' => env('GASTRONOMIA_CIERRE_JORNADA_CUENTA_VENTAS_KIOSCO_ID'),
    'cierre_jornada_cuenta_fondo_fijo_maquinas_id' => env('GASTRONOMIA_CIERRE_JORNADA_CUENTA_FONDO_FIJO_MAQUINAS_ID'),
    /** Invitaciones / cortesía $0,01 sin cobranza (redondeo rendiciones); debe del asiento 2. */
    'cierre_jornada_cuenta_diferencia_caja_id' => env('GASTRONOMIA_CIERRE_JORNADA_CUENTA_DIFERENCIA_CAJA_ID'),

    /**
     * Límite de memoria PHP para cierre Waitry (Informe Z jornada, analizar tramo, recalcular %, detalle).
     * Jornadas con alto volumen de órdenes/emisiones pueden superar 512M.
     */
    'cierre_jornada_proceso_memory_limit' => env('GASTRONOMIA_CIERRE_JORNADA_PROCESO_MEMORY_LIMIT', '1024M'),

    /**
     * Tipo transacción stock (tipotransaccion_stock.id) para ajuste por consumo de insumos
     * de comandas Waitry no facturadas (parte efectivo tras redistribución). Default 7 = salida.
     */
    'cierre_jornada_tipotransaccion_stock_ajuste_consumo_id' => (int) env(
        'GASTRONOMIA_CIERRE_JORNADA_TIPOTRANSACCION_STOCK_AJUSTE_CONSUMO_ID',
        7,
    ),

    /** Abreviatura tipoasiento para grabación de asientos del proceso cierre Waitry (default VTA). */
    'cierre_jornada_tipoasiento_abreviatura' => env('GASTRONOMIA_CIERRE_JORNADA_TIPOASIENTO_ABREVIATURA', 'VTA'),

    /** Centro de costo (código ccosto) en líneas de asientos del cierre Waitry cuando la cuenta maneja CC. */
    'cierre_jornada_centrocosto_codigo' => env('GASTRONOMIA_CIERRE_JORNADA_CENTROCOSTO_CODIGO', '85'),

    /**
     * Porcentaje del tope CF (ARCA) para agrupar comandas en cada lote de facturación del proceso Waitry.
     * Default 20 = lotes ~20 % de ARCA_WSFE_RECEPTOR_CF_UMBRAL_MONTO.
     */
    'cierre_jornada_cf_lote_porcentaje_tope' => (float) env('GASTRONOMIA_CIERRE_JORNADA_CF_LOTE_PORCENTAJE_TOPE', 20),

    /**
     * Porcentaje de redistribución QR/efectivo del proceso Waitry (manual y automático).
     * Prioridad por empresa: gastronomia_cierre_jornada_config.porcentaje → mapa env → este default.
     */
    'cierre_jornada_porcentaje' => (float) env('GASTRONOMIA_CIERRE_JORNADA_PORCENTAJE', 0),

    /**
     * @var array<int, float>
     */
    'cierre_jornada_porcentaje_por_empresa' => (static function (): array {
        $raw = env('GASTRONOMIA_CIERRE_JORNADA_PORCENTAJE_POR_EMPRESA');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $empresaId => $pct) {
            if (is_numeric($pct)) {
                $map[(int) $empresaId] = (float) $pct;
            }
        }

        return $map;
    })(),

    /**
     * Proceso automático de cierre Waitry (gastronomia:cierre-jornada-waitry-automatico).
     * Deshabilitado por defecto hasta validar en producción con el botón de prueba.
     */
    'cierre_jornada_automatico' => [
        'habilitado' => filter_var(env('GASTRONOMIA_CIERRE_JORNADA_AUTOMATICO_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('GASTRONOMIA_CIERRE_JORNADA_AUTOMATICO_HORA', '09:00'),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GASTRONOMIA_CIERRE_JORNADA_AUTOMATICO_EMPRESAS_IDS', '1,2,3')),
        ))),
        'email' => env(
            'GASTRONOMIA_CIERRE_JORNADA_AUTOMATICO_EMAIL',
            'sergiogranucci@gmail.com,ofalqui@grupoagg.com',
        ),
        'usuario_id' => max(1, (int) env('GASTRONOMIA_CIERRE_JORNADA_AUTOMATICO_USUARIO_ID', 1)),
    ],

    /**
     * Tras cerrar jornada: job diferido relee Waitry vs Z congelado; documenta faltantes y mail a Tesorería.
     * No pisa el Informe Z histórico del cierre.
     * Destinatarios = copia fija + lista por empresa_id (1 Biyemas, 2 Kandiko, 3 Rebisco).
     */
    'informe_z_transmision_faltante' => [
        'habilitado' => filter_var(
            env('GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_HABILITADO', true),
            FILTER_VALIDATE_BOOLEAN,
        ),
        /** Minutos de espera tras el cierre antes de releer Waitry (da tiempo a completar transmisión). */
        'delay_minutos' => max(1, (int) env('GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_DELAY_MINUTOS', 15)),
        'tolerancia' => max(0.0, (float) env(
            'GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_TOLERANCIA',
            env('GASTRONOMIA_CIERRE_TOTEM_INFORME_Z_TOLERANCIA', 0.02),
        )),
        /** Copia siempre (además de los de la empresa). */
        'email_copia' => env(
            'GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_EMAIL_COPIA',
            'sergiogranucci@gmail.com',
        ),
        /**
         * Destinatarios por empresa_id (CSV por empresa).
         *
         * @var array<int, string>
         */
        'email_por_empresa' => (static function (): array {
            $default = [
                1 => 'bcanosa@grupoagg.com,tesoreria.biy@grupoagg.com',
                2 => 'tesoreria.kan@grupoagg.com',
                3 => 'tesoreria.reb@grupoagg.com',
            ];
            $raw = env('GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_EMAIL_POR_EMPRESA');
            if ($raw === null || $raw === '') {
                return $default;
            }
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (! is_array($decoded)) {
                return $default;
            }
            $map = [];
            foreach ($decoded as $empresaId => $emails) {
                $lista = trim((string) $emails);
                if ($lista !== '') {
                    $map[(int) $empresaId] = $lista;
                }
            }

            return $map !== [] ? $map : $default;
        })(),
        'cola' => env('GASTRONOMIA_INFORME_Z_TRANSMISION_FALTANTE_COLA', 'default'),
    ],

    /**
     * Punto de venta fijo para facturación del proceso de cierre Waitry (una factura por permiso).
     * Clave = empresa_id, valor = código PV (ej. BSA empresa 1 → 00003).
     * Prioridad: gastronomia_cierre_jornada_config.puntoventa_id → este mapa.
     *
     * @var array<int, string>
     */
    'cierre_jornada_puntoventa_codigo_por_empresa' => (static function (): array {
        $raw = env('GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA_CODIGO_POR_EMPRESA');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $empresaId => $codigo) {
            $cod = trim((string) $codigo);
            if ($cod !== '') {
                $map[(int) $empresaId] = $cod;
            }
        }

        return $map;
    })(),

    /** Código de descuento gastronomía para facturar canjes de premios Wigos ($0,01). */
    'canje_premio_descuento_codigo' => env('GASTRONOMIA_CANJE_PREMIO_DESCUENTO_CODIGO', '10'),

    /** Código de cliente para facturar canjes de premios Wigos. */
    'canje_premio_cliente_codigo' => env('GASTRONOMIA_CANJE_PREMIO_CLIENTE_CODIGO', '500'),

    /** Cliente interno canje premio Wigos categoría platino (Anita resv_cliente 001500 / CANJE PLATINO). */
    'canje_premio_platino_cliente_codigo' => env('GASTRONOMIA_CANJE_PREMIO_PLATINO_CLIENTE_CODIGO', '1500'),

    /** levelCode Wigos que imputan a canje_premio_platino_cliente_codigo (categoría platino = 3). */
    'canje_premio_platino_level_codes' => env('GASTRONOMIA_CANJE_PREMIO_PLATINO_LEVEL_CODES', '3'),

    /** Días de validez del ticket Wigos desde la fecha del premio (COMAND_pide_canje: 2 días). */
    'canje_premio_vencimiento_dias' => max(1, (int) env('GASTRONOMIA_CANJE_PREMIO_VENCIMIENTO_DIAS', 2)),

    /** Descuento y cliente para canje diario fidelidad por tarjeta (factura $0,01). */
    'canje_fidelidad_descuento_codigo' => env('GASTRONOMIA_CANJE_FIDELIDAD_DESCUENTO_CODIGO', '10'),

    /** Cliente interno fidelidad no platino; platino usa canje_premio_platino_cliente_codigo (1500). */
    'canje_fidelidad_cliente_codigo' => env('GASTRONOMIA_CANJE_FIDELIDAD_CLIENTE_CODIGO', '500'),

    /**
     * Facturador canjes marketing (comandera en sala, menú Canjes).
     * Descuento prefijado (código en descuento_gastronomia), cliente VIP beneficiario, sin medios de pago.
     */
    'canje_marketing_descuento_codigo' => env('GASTRONOMIA_CANJE_MARKETING_DESCUENTO_CODIGO', '40'),

    /** Comandera marketing: sin habilitación de turno por PC (siempre activa con login mozo). */
    'canje_marketing_requiere_habilitacion_turno' => filter_var(
        env('GASTRONOMIA_CANJE_MARKETING_REQUIERE_HABILITACION_TURNO', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Cubiertos al abrir cuenta en facturador marketing (típ. no aplica). */
    'canje_marketing_cubiertos_obligatorio' => filter_var(
        env('GASTRONOMIA_CANJE_MARKETING_CUBIERTOS_OBLIGATORIO', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'canje_marketing_cubiertos_default' => max(0, (int) env('GASTRONOMIA_CANJE_MARKETING_CUBIERTOS_DEFAULT', 1)),

    /** Canje marketing: nunca genera asiento contable al facturar. */
    'canje_marketing_genera_contabilidad_al_facturar' => filter_var(
        env('GASTRONOMIA_CANJE_MARKETING_GENERA_CONTABILIDAD_FACTURA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Listado marketing: código de lista de precios para CMV provisorio (Anita prem_lista; no confundir con id ERP). */
    'canje_marketing_listado_listaprecio_cmv_codigo' => max(1, (int) env(
        'GASTRONOMIA_CANJE_MARKETING_LISTADO_LISTAPRECIO_CMV_CODIGO',
        env('GASTRONOMIA_CANJE_MARKETING_LISTADO_LISTAPRECIO_CMV_ID', 50)
    )),

    /**
     * Base Informix para recepciones (recepmae / recepmov) en informe gerente.
     */
    'recepciones_anita_sistema' => env('GASTRONOMIA_RECEPCIONES_ANITA_SISTEMA', 'compras'),

    /**
     * Centro de costo (recv_ccosto en recepmov) para filtrar recepciones del informe gerente.
     * Vacío = sin filtro por CC.
     */
    'recepciones_centro_costo_codigo' => env('GASTRONOMIA_RECEPCIONES_CENTRO_COSTO_CODIGO', '85'),

    /**
     * Informe gerente: base de listas Anita stkpre para costo (5000 + mes). Ej. junio → 5006, mayo → 5005.
     */
    'informe_gerente_costo_lista_base' => (int) env('GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE', 5000),

    /**
     * Reporte ventas de artículos: lista de precios de venta (P.Vta.). 0 = LISTAPRECIO_DEFAULT_ID (lista 1).
     */
    'ventas_articulos_listaprecio_venta_id' => (int) env('GASTRONOMIA_VENTAS_ARTICULOS_LISTAPRECIO_VENTA_ID', 0),

    /**
     * Costo catálogo V…: fórmula + última compra Anita → lista 5000+mes (ERP precio; stkpre opcional).
     * Comando: php artisan gastronomia:actualizar-costo-mensual-catalogo
     * Schedule: diario al inicio de jornada + último día del mes (cierre).
     */
    'costo_mensual_catalogo' => [
        'habilitado' => filter_var(env('GASTRONOMIA_COSTO_MENSUAL_CATALOGO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('GASTRONOMIA_COSTO_MENSUAL_CATALOGO_HORA', '07:00'),
        'hora_ultimo_dia_mes' => env('GASTRONOMIA_COSTO_MENSUAL_CATALOGO_HORA_ULTIMO_DIA', '23:30'),
        'usuario_id' => (int) env('GASTRONOMIA_COSTO_MENSUAL_CATALOGO_USUARIO_ID', 1),
        'moneda_id' => (int) env('GASTRONOMIA_COSTO_MENSUAL_CATALOGO_MONEDA_ID', 1),
        'sincronizar_anita' => filter_var(env('GASTRONOMIA_COSTO_MENSUAL_SINCRONIZAR_ANITA', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'cuentacaja_efectivo_por_empresa' => (static function (): array {
        $raw = env('GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $empresaId => $cuentacajaId) {
            $ccId = (int) $cuentacajaId;
            if ($ccId > 0) {
                $map[(int) $empresaId] = $ccId;
            }
        }

        return $map;
    })(),

];
