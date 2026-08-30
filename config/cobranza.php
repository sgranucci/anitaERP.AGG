<?php

/**
 * Mapa empresa_id => puntoventa_id para NC de descuentos en cobranza.
 * Override: COBRANZA_NC_PUNTOVENTA_POR_EMPRESA='{"1":5}'
 *
 * @return array<int, int>
 */
$ncPuntoventaPorEmpresa = (static function (): array {
    $raw = env('COBRANZA_NC_PUNTOVENTA_POR_EMPRESA');
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (! is_array($decoded)) {
        return [];
    }
    $map = [];
    foreach ($decoded as $empresaId => $pvId) {
        if ((int) $empresaId > 0 && (int) $pvId > 0) {
            $map[(int) $empresaId] = (int) $pvId;
        }
    }

    return $map;
})();

/**
 * Mapa letra => tipotransaccion_id para NC (opcional; fallback nc_tipotransaccion_id).
 *
 * @return array<string, int>
 */
$ncTipotransaccionPorLetra = (static function (): array {
    $raw = env('COBRANZA_NC_TIPOTRANSACCION_POR_LETRA');
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (! is_array($decoded)) {
        return [];
    }
    $map = [];
    foreach ($decoded as $letra => $tipoId) {
        $l = strtoupper(trim((string) $letra));
        if ($l !== '' && (int) $tipoId > 0) {
            $map[$l] = (int) $tipoId;
        }
    }

    return $map;
})();

return [
	"GRABACION" => "CON_PRECARGA",

    /** Descuentos en cobranza → NC fiscal en ARCA al confirmar/grabar */
    'descuento_nc_habilitado' => filter_var(env('COBRANZA_DESCUENTO_NC_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    'nc_puntoventa_por_empresa' => $ncPuntoventaPorEmpresa,
    'nc_tipotransaccion_id' => (int) env('COBRANZA_NC_TIPOTRANSACCION_ID', 4),
    'nc_tipotransaccion_por_letra' => $ncTipotransaccionPorLetra,
    'nc_articulo_id' => (int) env('COBRANZA_NC_ARTICULO_ID', 0),
    'nc_articulo_sku' => (string) env('COBRANZA_NC_ARTICULO_SKU', ''),
    /**
     * NCP (NC de descuento en cobranza): si false, no lleva percepción IIBB
     * (ni prorrateo ni recálculo). El Bierzo = false; AGG y resto = true.
     */
    'nc_percepcion_iibb' => filter_var(
        env(
            'COBRANZA_NC_PERCEPCION_IIBB',
            strtoupper(trim((string) env('EMPRESA', ''))) === 'EL BIERZO' ? 'false' : 'true'
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Lock de numeración tesorería (cobranza + caja_movimiento sin cobranza_id).
     * Clave: empresa_id + tipotransaccion_caja_id. Serializa gastronomía, cobranzas e ingreso/egreso.
     * Ver .env.example (COBRANZA_NUMERACION_*).
     */
    'numeracion_lock_segundos' => (int) env('COBRANZA_NUMERACION_LOCK_SEGUNDOS', 120),
    'numeracion_lock_espera_segundos' => (int) env('COBRANZA_NUMERACION_LOCK_ESPERA_SEGUNDOS', 90),

    /**
     * tipotransaccion_caja_id que usan numerador secuencial (MAX+1 solo dígitos).
     * Gastronomía (p. ej. id 2) queda fuera: numerotransaccion = B-00008-00807543 desde venta.codigo.
     * Incluir REM (5), RMI (6), TRA (7), ING (8), EGR (9), OPP (10) y OPA (11) además de COB (1).
     * OPA (anticipo SP) también entra por semilla Anita si el id no está en esta lista.
     */
    'tipotransaccion_caja_ids_secuencial' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('COBRANZA_TIPOTRANSACCION_SECUENCIAL_IDS', '1,5,6,7,8,9,10,11')),
    ))),

    "VALORES_A_DEPOSITAR" => [
            '1' => 111040000,
            '2' => 111040000,
            '3' => 111040000
            ],
    ];
