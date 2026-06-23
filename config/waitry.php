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
     * Gateway en payment.payments (pushExternalOrder, Control Z).
     * Clave = tipo Waitry normalizado (mercadopago, cash, …); valor = texto gateway.
     *
     * @var array<string, string>
     */
    'pago_gateway_por_tipo' => (static function (): array {
        $raw = env('WAITRY_PAGO_GATEWAY_POR_TIPO');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $tipo => $gateway) {
                    $tipoNorm = mb_strtolower(trim(str_replace([' ', '-', '_'], '', (string) $tipo)));
                    $gw = trim((string) $gateway);
                    if ($tipoNorm !== '' && $gw !== '') {
                        $map[$tipoNorm] = $gw;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [];
    })(),

    /**
     * Medios Waitry (lecturas) → cuenta de caja Anita (multiempresa).
     * Gastronomía: mercadopago y totalcoin (QR Waitry) comparten cuenta MP (201); los tipos Waitry siguen separados.
     *
     * @var array<string, int>
     */
    'tipo_pago_cuentacaja' => (static function (): array {
        $raw = env('WAITRY_TIPO_PAGO_CUENTACAJA');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $tipo => $cuentacajaId) {
                    $tipoNorm = mb_strtolower(trim(str_replace([' ', '-', '_'], '', (string) $tipo)));
                    $id = (int) $cuentacajaId;
                    if ($tipoNorm !== '' && $id > 0) {
                        $map[$tipoNorm] = $id;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [
            'mercadopago' => 201,
            'totalcoin' => 201,
        ];
    })(),

    'get_orders_url' => env(
        'WAITRY_GET_ORDERS_URL',
        'https://api.waitry.net/1/analytics/analytics/getOrdersPOS'
    ),

    /**
     * Fallback cuando getOrdersPOS responde ok:false (p. ej. «Interface not available»).
     * POST analytics/getordersdetails — formato orderItems (doc. Waitry).
     */
    'get_orders_details_url' => env(
        'WAITRY_GET_ORDERS_DETAILS_URL',
        'https://api.waitry.net/1/analytics/analytics/getordersdetails'
    ),

    /**
     * Fallback getordersdetails en cuentas externas POS (listado/importación).
     * Deshabilitado por defecto: el POS usa solo getOrdersPOS (más rápido).
     * Cierre de jornada (tesorería) sigue usando getordersdetails por su propio servicio.
     */
    'get_orders_usar_detalles_fallback' => filter_var(
        env('WAITRY_GET_ORDERS_USAR_DETALLES_FALLBACK', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Ventana de consulta getOrdersPOS desde el facturador (parámetros from/to).
     * Formato Waitry: YYYY-MM-DD HH:mm:ss (app.timezone). Minutos hacia atrás desde ahora;
     * 0 = sin filtro horario (no envía from/to).
     */
    'get_orders_minutos_atras' => max(0, (int) env('WAITRY_GET_ORDERS_MINUTOS_ATRAS', 20)),

    /** Cache del listado getOrdersPOS (segundos). 0 = sin cache. ?refresh=1 en API lo omite. */
    'get_orders_cache_segundos' => max(0, (int) env('WAITRY_GET_ORDERS_CACHE_SEGUNDOS', 15)),

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

    /** Log waitry.http.timing (ms por operación HTTP) en laravel.log */
    'http_profile' => filter_var(env('WAITRY_HTTP_PROFILE', true), FILTER_VALIDATE_BOOLEAN),

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
