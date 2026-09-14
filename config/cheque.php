<?php

/**
 * Mapa empresa_id => puntoventa_id para ND por cheque rechazado.
 * Override: CHEQUE_ND_PUNTOVENTA_POR_EMPRESA='{"1":1}'
 *
 * @return array<int, int>
 */
$ndPuntoventaPorEmpresa = (static function (): array {
    $raw = env('CHEQUE_ND_PUNTOVENTA_POR_EMPRESA');
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
 * Mapa letra => tipotransaccion_id para ND (opcional; fallback nd_tipotransaccion_id).
 *
 * @return array<string, int>
 */
$ndTipotransaccionPorLetra = (static function (): array {
    $raw = env('CHEQUE_ND_TIPOTRANSACCION_POR_LETRA');
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
     * Rechazo CHT → Nota de Débito (NDR) vía FacturacionService.
     * Emisión por concepto_venta (sin artículo), como mostrador.
     */
    'nd_habilitado' => filter_var(env('CHEQUE_ND_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    'nd_puntoventa_por_empresa' => $ndPuntoventaPorEmpresa,
    'nd_tipotransaccion_id' => (int) env('CHEQUE_ND_TIPOTRANSACCION_ID', 43),
    'nd_tipotransaccion_por_letra' => $ndTipotransaccionPorLetra,
    'nd_concepto_codigo' => (string) env('CHEQUE_ND_CONCEPTO_CODIGO', 'NDR-CHEQUE'),
    'nd_concepto_id' => (int) env('CHEQUE_ND_CONCEPTO_ID', 0),
    'nd_gastos_concepto_codigo' => (string) env('CHEQUE_ND_GASTOS_CONCEPTO_CODIGO', 'NDR-GASTOS'),
    'nd_gastos_concepto_id' => (int) env('CHEQUE_ND_GASTOS_CONCEPTO_ID', 0),
    'nd_impuesto_id' => (int) env('CHEQUE_ND_IMPUESTO_ID', 3),

    /** Sync Anita: años hacia atrás (cter_fecha_cheque / cpro_fecha_cheque). */
    'sync_anios' => max(1, (int) env('CHEQUE_SYNC_ANIOS', 5)),

    /** Import masivo CHT: insertar también en Anita ctermae. */
    'import_push_anita' => filter_var(env('CHEQUE_IMPORT_PUSH_ANITA', true), FILTER_VALIDATE_BOOLEAN),

    /** Aviso diario aging cartera (vencidos + próximos). */
    'aging_aviso' => [
        'habilitado' => filter_var(env('CHEQUE_AGING_AVISO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'dias_proximos' => max(0, (int) env('CHEQUE_AGING_AVISO_DIAS_PROXIMOS', 7)),
        'hora' => (string) env('CHEQUE_AGING_AVISO_HORA', '09:25'),
        'emails' => (string) env('CHEQUE_AGING_AVISO_EMAILS', ''),
    ],

    /** Asiento TES al acreditar depósito CHT (Debe banco / Haber valores a depositar). */
    'acreditacion_asiento' => [
        'habilitado' => filter_var(env('CHEQUE_ACREDITACION_ASIENTO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /**
     * eCheq: API depende del banco de cada empresa.
     * provider=manual hasta cablear Galicia/Santander/etc.
     */
    'echeq' => [
        'habilitado' => filter_var(env('CHEQUE_ECHEQ_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'provider' => (string) env('CHEQUE_ECHEQ_PROVIDER', 'manual'),
    ],
];
