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
     * Respaldo si la configuración del punto de venta gastronomía no define tipotransaccion_id.
     * Preferir el campo en configuracion_puntoventa_gastronomia (por terminal).
     */
    'tipotransaccion_factura_id' => env('GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID'),

    /** Prefijo SKU para catálogo rápido en el POS (ej. consumibles venta salón). */
    'sku_catalogo_prefijo' => env('GASTRONOMIA_SKU_CATALOGO_PREFIJO', 'V'),

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
