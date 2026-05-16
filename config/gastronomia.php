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
     * Si es false, los procesos gastronómicos pueden omitir pasos contables propios (no altera FacturacionService).
     */
    'genera_contabilidad_al_facturar' => filter_var(env('GASTRONOMIA_GENERA_CONTABILIDAD_FACTURA', true), FILTER_VALIDATE_BOOLEAN),

    /** Obligatorio para emitir: ID en tabla tipotransaccion (factura de venta estándar del cliente). */
    'tipotransaccion_factura_id' => env('GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID'),

    /** Prefijo SKU para catálogo rápido en el POS (ej. consumibles venta salón). */
    'sku_catalogo_prefijo' => env('GASTRONOMIA_SKU_CATALOGO_PREFIJO', 'V'),

    /**
     * Cantidad de dígitos numéricos tras el prefijo en el catálogo gastronomía (ej. 5 → se ingresa solo "00123" y el sistema arma V00123).
     * 0 = campo SKU completo (sin prefijo visual fijo); solo dígitos si es > 0.
     */
    'sku_catalogo_digitos_sufijo' => (int) env('GASTRONOMIA_SKU_CATALOGO_DIGITOS_SUFIJO', 0),

    /**
     * Si FacturacionService devolviera error textual por comunicación ARCA (sin dd), se podría reintentar con PV CAEA.
     * Hoy grabaFacturaERP hace dd() ante excepción: conviene corregir eso en FacturacionService para recuperación 24x7.
     */
    'reintentar_caea_si_error_comunicacion' => filter_var(env('GASTRONOMIA_REINTENTAR_CAEA', true), FILTER_VALIDATE_BOOLEAN),
];
