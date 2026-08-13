<?php

/**
 * Módulo Bingo (Caja): rendiciones por turno, jornada propia, presentación en caja.
 */
$empresaEntorno = strtoupper(trim((string) env('EMPRESA', 'AGG')));
$defaultHabilitado = $empresaEntorno === 'AGG';

return [
    'habilitado' => filter_var(
        env('BINGO_HABILITADO', $defaultHabilitado ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'jornada_obligatoria' => filter_var(
        env('BINGO_JORNADA_OBLIGATORIA', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'requiere_habilitacion_turno' => filter_var(
        env('BINGO_REQUIERE_HABILITACION_TURNO', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Identificador fijo cuando no se usa IP del cliente.
     * Coincide con configuracion_puntoventa_bingo.identificador_pc en ese modo.
     */
    'identificador_pc' => (static function (): string {
        $valor = trim((string) env('BINGO_IDENTIFICADOR_PC', ''));

        return $valor !== '' ? $valor : (string) gethostname();
    })(),

    /**
     * true = el identificador efectivo es la IP del cliente ($request->ip()).
     */
    'identificador_pc_usar_ip_cliente' => filter_var(
        env('BINGO_IDENTIFICADOR_USAR_IP_CLIENTE', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Replica rendición en Informix legacy (tabla rendbingo vía bridge HTTP).
     */
    'sincronizar_anita_al_rendir' => filter_var(
        env('BINGO_SINCRONIZAR_ANITA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'anita_sistema' => env('BINGO_ANITA_SISTEMA', 'caja'),
    'anita_tipo_oper' => env('BINGO_ANITA_TIPO_OPER', 'F'),

    /** Flash diario: si ERP no tiene rendiciones, leer rendbingo vía bridge Anita. */
    'flash_fallback_anita' => filter_var(
        env('BINGO_FLASH_FALLBACK_ANITA', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Cierre contable diario (módulo Contabilidad): empresa + fecha jornada.
     * Punto de venta (sucursal) solo para FBI exenta del día.
     */
    'cierre_rendicion_contable' => [
        /** Tipo comprobante Anita (legacy p-vtabingo: FBI). */
        'tipo_comprobante' => env('BINGO_CIERRE_TIPO_COMPROBANTE', 'FBI'),
        /** Letra comprobante (legacy: B exenta). */
        'letra_comprobante' => env('BINGO_CIERRE_LETRA_COMPROBANTE', 'B'),
        /** Cliente interno venta Anita (legacy: 000000 «Sala de bingo»). */
        'cliente_codigo' => env('BINGO_CIERRE_CLIENTE_CODIGO', '000000'),
        'cliente_nombre' => env('BINGO_CIERRE_CLIENTE_NOMBRE', 'Sala de bingo'),
        /** Abreviatura tipoasiento legacy (BIN). */
        'tipoasiento_abreviatura' => env('BINGO_CIERRE_TIPOASIENTO_ABREVIATURA', 'BIN'),
        /**
         * Punto de venta / sucursal FBI por empresa (legacy impcont sucursal).
         *
         * @var array<int, int>
         */
        'puntoventa_por_empresa' => (static function (): array {
            $raw = env('BINGO_CIERRE_PUNTOVENTA_POR_EMPRESA');
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
        /** Estado rendbingo tras cierre (legacy RENDB_FACTURADO = 'F'). */
        'estado_facturado_anita' => env('BINGO_CIERRE_ESTADO_FACTURADO_ANITA', 'F'),
        /** Pozo acumulado mensual: leer Anita hasta mes entero en ERP. */
        'pozo_acumulado_desde_anita' => filter_var(
            env('BINGO_CIERRE_POZO_ACUMULADO_DESDE_ANITA', 'true'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'conciliacion_flash_tolerancia' => (float) env('BINGO_CIERRE_FLASH_TOLERANCIA', 0.02),
    ],
];
