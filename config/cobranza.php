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
	/**
	 * CON_PRECARGA = alta en estado PRE CARGA (sin asiento hasta confirmar; circuito AGG).
	 * Cualquier otro valor = CONFIRMADA + asiento al grabar (Ferli y similares).
	 */
	'GRABACION' => (static function (): string {
		$override = env('COBRANZA_GRABACION');
		if ($override !== null && trim((string) $override) !== '') {
			return strtoupper(trim((string) $override));
		}
		$empresa = strtoupper(trim((string) env('EMPRESA', '')));
		if ($empresa === 'CALZADOS FERLI' || str_contains($empresa, 'FERLI')) {
			return 'SIN_PRECARGA';
		}

		return 'CON_PRECARGA';
	})(),

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
     * Gastronomía (p. ej. id 2 AGG) queda fuera: numerotransaccion = B-00008-00807543 desde venta.codigo.
     * AGG: COB(1), REM(5), RMI(6), TRA(7), ING(8), EGR(9), OPP(10), OPA(11).
     * Ferli: COB(12), OPP(13), DEV(14), ING(15), EGR(16) — ver .env del cliente.
     * Además CobranzaNumeracionTransaccion reconoce COB/REM/RMI/DEV y OPP/OPA/ING/EGR/TRA por abreviatura.
     */
    'tipotransaccion_caja_ids_secuencial' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('COBRANZA_TIPOTRANSACCION_SECUENCIAL_IDS', '1,5,6,7,8,9,10,11')),
    ))),

    /**
     * Alinear numerotransaccion de COB con MAX(pag_rec) de Anita (che_ban.pago).
     * Evita arrancar en 1 cuando el ERP está vacío y Anita ya tiene la serie viva.
     */
    'anita_numeracion_habilitada' => filter_var(
        env('COBRANZA_ANITA_NUMERACION_HABILITADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    'anita_numeracion_tipos_pago' => array_values(array_filter(array_map(
        static fn ($t) => strtoupper(trim((string) $t)),
        explode(',', (string) env('COBRANZA_ANITA_NUMERACION_TIPOS', 'COB')),
    ))),
    'anita_numeracion_fecha_desde' => (int) env('COBRANZA_ANITA_NUMERACION_FECHA_DESDE', 20200101),
    'anita_numeracion_pag_rec_max' => (int) env('COBRANZA_ANITA_NUMERACION_PAG_REC_MAX', 499999),

    "VALORES_A_DEPOSITAR" => [
            '1' => (int) env('CAJA_VALORES_A_DEPOSITAR_CUENTA_CODIGO', 111040000),
            '2' => (int) env('CAJA_VALORES_A_DEPOSITAR_CUENTA_CODIGO', 111040000),
            '3' => (int) env('CAJA_VALORES_A_DEPOSITAR_CUENTA_CODIGO', 111040000),
            ],
    ];
