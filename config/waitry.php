<?php

/**
 * Integración Waitry — comandas a cocina (POS gastronomía).
 *
 * @see https://apidoc.waitry.net/#authorization-and-authentication
 * @see POST /interface/interface/pushExternalOrder
 */
return [
    /**
     * Master switch: sin esto no se llama a Waitry (emisión de factura no se ve afectada).
     */
    'habilitado' => filter_var(env('WAITRY_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),

    'login_url' => env('WAITRY_LOGIN_URL', 'https://api.waitry.net/1/user/login/login'),

    'push_order_url' => env(
        'WAITRY_PUSH_ORDER_URL',
        'https://api.waitry.net/1/interface/interface/pushExternalOrder'
    ),

    'sync_status_pos_url' => env(
        'WAITRY_SYNC_STATUS_POS_URL',
        'https://api.waitry.net/1/interface/interface/syncStatusPOS'
    ),

    /** Evento enviado al registrar cobro de una orden importada (enum Waitry). */
    'sync_status_pos_event' => env('WAITRY_SYNC_STATUS_POS_EVENT', 'accepted'),

    /**
     * Mapeo opcional cuentacaja_id → cash | credit_card | debit_card (JSON).
     * El efectivo de GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA se resuelve como cash sin estar aquí.
     *
     * @var array<int, string>
     */
    'cuentacaja_tipo_pago' => (static function (): array {
        $raw = env('WAITRY_CUENTACAJA_TIPO_PAGO');
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $cuentacajaId => $tipo) {
            $tipoNorm = mb_strtolower(trim((string) $tipo));
            if (in_array($tipoNorm, ['cash', 'credit_card', 'debit_card'], true)) {
                $map[(int) $cuentacajaId] = $tipoNorm;
            }
        }

        return $map;
    })(),

    'get_orders_url' => env(
        'WAITRY_GET_ORDERS_URL',
        'https://api.waitry.net/1/analytics/analytics/getOrdersPOS'
    ),

    /**
     * Ventana de consulta getOrdersPOS desde el facturador (parámetros from/to).
     * Minutos hacia atrás desde ahora; 0 = sin filtro horario (no envía from/to).
     */
    'get_orders_minutos_atras' => max(0, (int) env('WAITRY_GET_ORDERS_MINUTOS_ATRAS', 20)),

    'client_id' => env('WAITRY_CLIENT_ID'),
    'client_secret' => env('WAITRY_CLIENT_SECRET'),
    'user' => env('WAITRY_USER'),
    'password' => env('WAITRY_PASSWORD'),

    /**
     * Validez del access_token según documentación Waitry (~14 días).
     * Se renueva automáticamente antes de expirar.
     */
    'token_ttl_segundos' => max(3600, (int) env('WAITRY_TOKEN_TTL_SEGUNDOS', 14 * 24 * 3600)),

    /** Margen de seguridad antes del vencimiento para renovar en caliente. */
    'token_renovar_antes_segundos' => max(300, (int) env('WAITRY_TOKEN_RENOVAR_ANTES_SEGUNDOS', 24 * 3600)),

    'http_timeout_segundos' => max(5, (int) env('WAITRY_HTTP_TIMEOUT', 30)),
    'http_reintentos' => max(1, min(5, (int) env('WAITRY_HTTP_REINTENTOS', 2))),

    /**
     * placeId Waitry por empresa Anita (1 → Avellaneda, 2 y 3 según contrato).
     *
     * @var array<int, int>
     */
    'place_id_por_empresa' => (static function (): array {
        $raw = env('WAITRY_PLACE_ID_POR_EMPRESA');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $empresaId => $placeId) {
                    $pid = (int) $placeId;
                    if ($pid > 0) {
                        $map[(int) $empresaId] = $pid;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [
            1 => 11782,
            2 => 11783,
            3 => 11784,
        ];
    })(),

    /**
     * Objeto table por empresa (JSON). Preferido sobre WAITRY_TABLE_JSON.
     * Ej.: {"1":{"tableId":101066},"2":{"tableId":101067},"3":{"tableId":101068}}
     *
     * @var array<int, array<string, mixed>>
     */
    'table_por_empresa' => (static function (): array {
        $raw = env('WAITRY_TABLE_POR_EMPRESA');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $empresaId => $table) {
                    if (is_array($table) && $table !== []) {
                        $map[(int) $empresaId] = $table;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [
            1 => ['tableId' => 101066],
            2 => ['tableId' => 101067],
            3 => ['tableId' => 101068],
        ];
    })(),

    /**
     * Fallback legacy: un solo table para todas las empresas (si table_por_empresa no aplica).
     *
     * @var array<string, mixed>
     */
    'table' => (static function (): array {
        $raw = env('WAITRY_TABLE_JSON', '');
        if ($raw === '' || $raw === null) {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    })(),

    'max_intentos' => max(1, (int) env('WAITRY_MAX_INTENTOS', 8)),
    'encolar_reintentos' => filter_var(env('WAITRY_ENCOLAR_REINTENTOS', true), FILTER_VALIDATE_BOOLEAN),
    'reintento_backoff_segundos' => [30, 60, 120, 300, 600, 900, 1800, 3600],
    'job_tries' => max(1, (int) env('WAITRY_JOB_TRIES', 3)),
    'job_backoff_segundos' => [60, 300, 900],
    'cola' => env('WAITRY_COLA', 'default'),

    /** Ruta relativa en storage/app para cache del token OAuth. */
    'token_storage_path' => env('WAITRY_TOKEN_STORAGE_PATH', 'waitry/oauth_token.json'),
];
