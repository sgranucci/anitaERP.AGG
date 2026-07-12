<?php

/**
 * Sincronización Anita (Informix) → puntoventa del ERP.
 * Tabla origen: sucursal (ver esquema en sucursal.sql: suc_numero, suc_empresa, …).
 * suc_direccion = código actividad ARCA (561012, 524120, …) → actividad_arca_id en el ERP.
 */
return [
    'tabla' => 'sucursal',

    'sistema' => env('PUNTOVENTA_SYNC_ANITA_SISTEMA', 'ventas'),

    /** Si no resuelve empresa por suc_nroemp ni por fragmentos. */
    'empresa_id_default' => (int) env('PUNTOVENTA_SYNC_EMPRESA_ID', 0) ?: 3,

    /**
     * Misma secuencia que el repositorio histórico: se evalúa en orden;
     * un fragmento posterior puede sobrescribir (p. ej. FARLOC tras ARMETAL).
     */
    'empresa_por_fragmento_suc_empresa' => [
        ['fragmento' => 'ARMETAL', 'empresa_id' => 3],
        ['fragmento' => 'FARLOC', 'empresa_id' => 1],
    ],

    'default_pais_id' => (int) env('PUNTOVENTA_SYNC_DEFAULT_PAIS_ID', 1),
    'default_provincia_id' => (int) env('PUNTOVENTA_SYNC_DEFAULT_PROVINCIA_ID', 3),
    'default_localidad_id' => (int) env('PUNTOVENTA_SYNC_DEFAULT_LOCALIDAD_ID', 108),

    /**
     * Columnas del SELECT contra Informix (deben existir en la sucursal del cliente).
     * Por defecto: esquema mínimo tipo sucursal.sql (evita que falle el UNLOAD y devuelva 0 filas).
     * Si su base tiene columnas extra (póliza, remito, etc.), defina PUNTOVENTA_SYNC_ANITA_CAMPOS_LISTADO completo.
     */
    'campos_listado' => env('PUNTOVENTA_SYNC_ANITA_CAMPOS_LISTADO')
        ?: 'suc_numero,suc_empresa,suc_leyenda1,suc_leyenda2,suc_direccion,suc_telefono,suc_localidad,suc_cond_iva,suc_fiscal,suc_nroemp,suc_ubic_afip,suc_cuit',

    /** Lista extendida de referencia (solo para copiar a .env si existen en Informix). */
    // suc_cod_postal,suc_division,suc_poliza,suc_suc_remito,suc_nro_ibr,suc_fecha_inicio,suc_fl_retiene_ibr,suc_fl_retiene_iva,suc_sucursal_div
];
