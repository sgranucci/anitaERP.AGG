<?php

namespace App\Services\Caja;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InterbankingService
{
    public function __construct(
    ) {}

    private const TOKEN_USO_CONSULTA = 'consulta';

    private const TOKEN_USO_CONFECCION = 'confeccion';

    private function tokenStoragePath(int $empresaId, string $uso = self::TOKEN_USO_CONSULTA): string
    {
        return $uso === self::TOKEN_USO_CONFECCION
            ? 'tokeninterbanking_confeccion_'.$empresaId.'.json'
            : 'tokeninterbanking_'.$empresaId.'.json';
    }

    /**
     * Credenciales OAuth por empresa (mismo índice que customer_id en config).
     *
     * @return array{client_id: string, client_secret: string}|null
     */
    private function credencialesPorEmpresa(int $empresaId): ?array
    {
        $idx = $empresaId - 1;
        $clientIds = config('interbanking.client_id');
        $clientSecrets = config('interbanking.client_secret');

        if (! is_array($clientIds) || ! is_array($clientSecrets)) {
            return null;
        }

        if ($idx < 0
            || ! array_key_exists($idx, $clientIds)
            || ! array_key_exists($idx, $clientSecrets)) {
            return null;
        }

        $clientId = $clientIds[$idx];
        $clientSecret = $clientSecrets[$idx];

        if (! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    /**
     * Token OAuth y datos de cliente para llamadas a la API.
     * Consultas: client_credentials + info-financiera.
     * Confección: password + Operador API + scope confeccion-transferencias.
     *
     * @return array{ok: true, access_token: string, client_id: string, customer_id: string}|array{ok: false, error: string}
     */
    private function contextoApiInterbanking(int $empresaId, string $uso = self::TOKEN_USO_CONSULTA): array
    {
        $customerIds = config('interbanking.customer_id');
        if (! is_array($customerIds)) {
            return [
                'ok' => false,
                'error' => 'Configuración de Interbanking incompleta (customer_id).',
            ];
        }

        $idx = $empresaId - 1;
        if ($idx < 0 || ! array_key_exists($idx, $customerIds)) {
            return [
                'ok' => false,
                'error' => 'No hay customer-id configurado para esta empresa.',
            ];
        }

        $credenciales = $this->credencialesPorEmpresa($empresaId);
        if ($credenciales === null) {
            return [
                'ok' => false,
                'error' => 'No hay client_id / client_secret configurados para esta empresa.',
            ];
        }

        if ($uso === self::TOKEN_USO_CONFECCION && $this->credencialesOperadorApi() === null) {
            return [
                'ok' => false,
                'error' => 'Falta Operador API Interbanking (INTERBANKING_OPERADOR_CLAVE_SISTEMA / USUARIO / PASSWORD).',
            ];
        }

        try {
            $this->ensureTokenInterbanking($empresaId, $uso);
        } catch (Throwable $e) {
            Log::warning('Interbanking: fallo al asegurar token', [
                'uso' => $uso,
                'exception' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'No se pudo preparar el acceso a Interbanking.',
            ];
        }

        $path = $this->tokenStoragePath($empresaId, $uso);
        if (! Storage::exists($path)) {
            return [
                'ok' => false,
                'error' => $uso === self::TOKEN_USO_CONFECCION
                    ? 'No hay token de Confección Interbanking; verifique Operador API y conectividad.'
                    : 'No hay token de Interbanking; verifique credenciales y conectividad.',
            ];
        }

        $token = json_decode(Storage::get($path));
        if (! $token || empty($token->access_token)) {
            Log::warning('Interbanking: token ausente o JSON inválido', ['uso' => $uso]);

            return [
                'ok' => false,
                'error' => 'Token de Interbanking inválido o no generado.',
            ];
        }

        return [
            'ok' => true,
            'access_token' => (string) $token->access_token,
            'client_id' => $credenciales['client_id'],
            'customer_id' => (string) $customerIds[$idx],
        ];
    }

    /**
     * @return array{clave_sistema: string, usuario: string, password: string, username_oauth: string}|null
     */
    private function credencialesOperadorApi(): ?array
    {
        $clave = trim((string) config('interbanking.operador_api_clave_sistema', ''));
        $usuario = trim((string) config('interbanking.operador_api_usuario', ''));
        $password = (string) config('interbanking.operador_api_password', '');
        if ($clave === '' || $usuario === '' || $password === '') {
            return null;
        }

        return [
            'clave_sistema' => $clave,
            'usuario' => $usuario,
            'password' => $password,
            'username_oauth' => '-3|'.$clave.'|'.$usuario,
        ];
    }

    /**
     * GET autenticado a la API con renovación de token y un reintento ante HTTP 401.
     *
     * @param  callable(array{ok: true, access_token: string, client_id: string, customer_id: string}): string  $construirUrl
     * @return array{ok: bool, http_code: int, data: array|null, error: string|null}
     */
    private function ejecutarGetAutenticado(int $empresaId, callable $construirUrl, string $operacion): array
    {
        return $this->ejecutarRequestAutenticado($empresaId, 'GET', $construirUrl, null, $operacion);
    }

    /**
     * Request autenticado (GET/POST) con renovación de token y un reintento ante HTTP 401.
     *
     * @param  callable(array{ok: true, access_token: string, client_id: string, customer_id: string}): string  $construirUrl
     * @param  array<string, mixed>|null  $bodyJson  body JSON para POST; null en GET
     * @return array{ok: bool, http_code: int, data: array|null, error: string|null}
     */
    private function ejecutarRequestAutenticado(
        int $empresaId,
        string $method,
        callable $construirUrl,
        ?array $bodyJson,
        string $operacion,
        string $usoToken = self::TOKEN_USO_CONSULTA
    ): array {
        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST'], true)) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'Método HTTP no soportado para Interbanking: '.$method,
            ];
        }

        try {
            $ctx = $this->contextoApiInterbanking($empresaId, $usoToken);
            if (! $ctx['ok']) {
                return [
                    'ok' => false,
                    'http_code' => 0,
                    'data' => null,
                    'error' => $ctx['error'],
                ];
            }

            for ($intento = 1; $intento <= 2; $intento++) {
                if ($intento > 1) {
                    $this->pideTokenInterbanking($empresaId, $usoToken);
                    $ctx = $this->contextoApiInterbanking($empresaId, $usoToken);
                    if (! $ctx['ok']) {
                        return [
                            'ok' => false,
                            'http_code' => 401,
                            'data' => null,
                            'error' => $ctx['error'],
                        ];
                    }
                }

                $url = $construirUrl($ctx);
                $headers = $this->cabecerasApiInterbanking($ctx, $method === 'POST');

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                $this->aplicarProxyCurl($ch);
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bodyJson ?? new \stdClass));
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                }

                $response = curl_exec($ch);
                $errno = curl_errno($ch);
                $curlErr = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($errno !== 0) {
                    Log::warning("Interbanking {$operacion} cURL", [
                        'empresa_id' => $empresaId,
                        'errno' => $errno,
                        'error' => $curlErr,
                    ]);

                    return [
                        'ok' => false,
                        'http_code' => 0,
                        'data' => null,
                        'error' => 'No se pudo conectar con Interbanking: '.$curlErr,
                    ];
                }

                $data = json_decode($response, true);

                if ($httpCode === 401 && $intento === 1) {
                    Log::info("Interbanking {$operacion}: HTTP 401, renovando token OAuth", [
                        'empresa_id' => $empresaId,
                        'client_id' => $ctx['client_id'],
                    ]);

                    continue;
                }

                if ($response === '' || $response === false) {
                    if ($httpCode >= 200 && $httpCode < 300) {
                        return [
                            'ok' => true,
                            'http_code' => $httpCode,
                            'data' => [],
                            'error' => null,
                        ];
                    }

                    return [
                        'ok' => false,
                        'http_code' => $httpCode,
                        'data' => null,
                        'error' => 'Interbanking respondió HTTP '.$httpCode.' sin detalle en el cuerpo.',
                    ];
                }

                if (! is_array($data)) {
                    Log::warning("Interbanking {$operacion}: respuesta no es JSON", [
                        'empresa_id' => $empresaId,
                        'http' => $httpCode,
                        'fragmento' => is_string($response) ? substr($response, 0, 500) : null,
                    ]);

                    return [
                        'ok' => false,
                        'http_code' => $httpCode,
                        'data' => null,
                        'error' => 'Respuesta inválida del servicio (HTTP '.$httpCode.').',
                    ];
                }

                if ($httpCode < 200 || $httpCode >= 300) {
                    Log::warning("Interbanking {$operacion}: error HTTP", [
                        'empresa_id' => $empresaId,
                        'http' => $httpCode,
                        'client_id' => $ctx['client_id'],
                        'cuerpo' => $data,
                    ]);

                    return [
                        'ok' => false,
                        'http_code' => $httpCode,
                        'data' => $data,
                        'error' => $this->mensajeErrorHttpInterbanking($data, $httpCode),
                    ];
                }

                return [
                    'ok' => true,
                    'http_code' => $httpCode,
                    'data' => $data,
                    'error' => null,
                ];
            }
        } catch (Throwable $e) {
            Log::warning("Interbanking {$operacion}: excepción", [
                'empresa_id' => $empresaId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'Error al consultar Interbanking.',
            ];
        }

        return [
            'ok' => false,
            'http_code' => 401,
            'data' => null,
            'error' => 'No autorizado en Interbanking (HTTP 401) tras renovar token.',
        ];
    }

    /**
     * @param  array{ok: true, access_token: string, client_id: string, customer_id: string}  $ctx
     * @return list<string>
     */
    private function cabecerasApiInterbanking(array $ctx, bool $conJsonBody = false): array
    {
        $headers = [
            'Authorization: Bearer '.$ctx['access_token'],
            'accept: application/json',
            'client_id: '.$ctx['client_id'],
        ];
        if ($conJsonBody) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }

    /**
     * Salida HTTPS vía proxy corporativo (mismo patrón que ARCA / feriados / cotización).
     *
     * @param  \CurlHandle|resource  $ch
     */
    private function aplicarProxyCurl($ch): void
    {
        $host = trim((string) config('interbanking.proxy_host', ''));
        if ($host === '') {
            return;
        }

        $port = max(1, (int) config('interbanking.proxy_port', 3128));
        curl_setopt($ch, CURLOPT_PROXY, $host.':'.$port);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

        $user = trim((string) config('interbanking.proxy_user', ''));
        $password = (string) config('interbanking.proxy_password', '');
        if ($user !== '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user.':'.$password);
        }
    }

    private function mensajeErrorHttpInterbanking(array $data, int $httpCode): string
    {
        if (! empty($data['errors']) && is_array($data['errors'])) {
            $partes = [];
            foreach ($data['errors'] as $err) {
                if (! is_array($err)) {
                    continue;
                }
                $parte = trim((string) ($err['message'] ?? $err['code'] ?? ''));
                if ($parte !== '') {
                    $partes[] = $parte;
                }
            }
            if ($partes !== []) {
                return implode(' | ', $partes);
            }
        }

        $msg = $data['message']
            ?? $data['moreInformation']
            ?? $data['httpMessage']
            ?? $data['error']
            ?? $data['error_description']
            ?? ($data['code'] ?? null)
            ?? ('Código HTTP '.$httpCode);
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        $texto = (string) $msg;
        if ($httpCode === 403 && stripos($texto, 'Not registered to plan') !== false) {
            return 'La aplicación OAuth no está suscripta al producto/plan de esta API en el portal Interbanking (Not registered to plan).';
        }

        return $texto;
    }

    /**
     * Consulta saldos en Interbanking.
     *
     * @return array{ok: bool, accounts: array, error: string|null}
     */
    public function leeSaldos($empresa_id, $currency)
    {
        $empresaId = (int) $empresa_id;
        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v1/accounts/balances';

        $resultado = $this->ejecutarGetAutenticado(
            $empresaId,
            fn (array $ctx): string => $baseUrl.'?'.http_build_query([
                'currency' => $currency,
                'customer-id' => $ctx['customer_id'],
            ]),
            'saldos'
        );

        if (! $resultado['ok']) {
            return [
                'ok' => false,
                'accounts' => [],
                'error' => $resultado['error'],
            ];
        }

        $data = $resultado['data'];

        if (! array_key_exists('accounts', $data)) {
            Log::warning('Interbanking saldos: JSON sin clave accounts', [
                'empresa_id' => $empresaId,
                'claves' => array_keys($data),
            ]);

            return [
                'ok' => false,
                'accounts' => [],
                'error' => 'La respuesta no incluye el listado de cuentas (accounts).',
            ];
        }

        if (! is_array($data['accounts'])) {
            Log::warning('Interbanking saldos: accounts no es un array', ['empresa_id' => $empresaId]);

            return [
                'ok' => false,
                'accounts' => [],
                'error' => 'Formato inesperado en la respuesta de cuentas.',
            ];
        }

        return [
            'ok' => true,
            'accounts' => $data['accounts'],
            'error' => null,
        ];
    }

    /**
     * Movimientos según OpenAPI Movimientos v1.1.0.
     *
     * URL externa: `GET https://api-gw.interbanking.com.ar/api/prod/v2/accounts/{account-number}/movements/{movement-type}`
     * con query `bank-number`, `customer-id`, `account-type`, `currency`, `date-since`, `date-until`, `limit`, `page`
     * y cabeceras `Authorization: Bearer …`, `client_id: …` (mismo flujo OAuth que balances).
     *
     * Ejemplo desde código:
     * `$app->make(self::class)->leeMovimientos(1, '1234567890', 'dia', ['bank_number' => '011', 'currency' => 'ARS']);`
     * Para consumo HTTP vía Anita usar la ruta `interbanking_movimientos` (GET con query en snake_case).
     *
     * @param  array{bank_number: string, account_type?: string, currency?: string, date_since?: string|null, date_until?: string|null, limit?: int, page?: int}  $paramsQuery
     * @return array{ok: bool, general_data: array|null, movements_detail: array<int, mixed>, error: string|null}
     */
    public function leeMovimientos(int $empresaId, string $accountNumber, string $movementType, array $paramsQuery): array
    {
        $tipos = ['dia', 'diferidos', 'anteriores', 'zughus'];
        if (! in_array($movementType, $tipos, true)) {
            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => 'Tipo de movimiento no válido.',
            ];
        }

        $bankNumber = $paramsQuery['bank_number'] ?? '';
        if (! is_string($bankNumber) || ! preg_match('/^[0-9]{3}$/', $bankNumber)) {
            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => 'bank-number debe ser un código de 3 dígitos.',
            ];
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => 'Número de cuenta obligatorio.',
            ];
        }

        $pathAccount = rawurlencode($accountNumber);
        $pathTipo = rawurlencode($movementType);
        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v2/accounts/'.$pathAccount.'/movements/'.$pathTipo;

        $resultado = $this->ejecutarGetAutenticado(
            $empresaId,
            function (array $ctx) use ($baseUrl, $bankNumber, $paramsQuery): string {
                $query = [
                    'bank-number' => $bankNumber,
                    'customer-id' => $ctx['customer_id'],
                    'account-type' => $paramsQuery['account_type'] ?? 'CC',
                    'currency' => $paramsQuery['currency'] ?? 'ARS',
                ];

                if (! empty($paramsQuery['date_since'])) {
                    $query['date-since'] = $paramsQuery['date_since'];
                }
                if (! empty($paramsQuery['date_until'])) {
                    $query['date-until'] = $paramsQuery['date_until'];
                }
                if (isset($paramsQuery['limit'])) {
                    $query['limit'] = (string) max(1, (int) $paramsQuery['limit']);
                }
                if (isset($paramsQuery['page'])) {
                    $query['page'] = (string) max(0, (int) $paramsQuery['page']);
                }

                return $baseUrl.'?'.http_build_query($query);
            },
            'movimientos'
        );

        if (! $resultado['ok']) {
            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => $resultado['error'],
            ];
        }

        $data = $resultado['data'];
        $general = $data['general_data'] ?? null;
        $detalle = $data['movements_detail'] ?? null;

        if (! is_array($detalle)) {
            Log::warning('Interbanking movimientos: sin movements_detail', [
                'empresa_id' => $empresaId,
                'claves' => array_keys($data),
            ]);

            return [
                'ok' => false,
                'general_data' => is_array($general) ? $general : null,
                'movements_detail' => [],
                'error' => 'La respuesta no incluye movements_detail.',
            ];
        }

        return [
            'ok' => true,
            'general_data' => is_array($general) ? $general : null,
            'movements_detail' => $detalle,
            'error' => null,
        ];
    }

    /**
     * Comprobantes de transferencias (OpenAPI Transferencias v1.09).
     *
     * URL externa: `GET https://api-gw.interbanking.com.ar/api/prod/v1/transfers/vouchers`
     * con query `customer-id` (obligatorio), filtros opcionales de débito/crédito, `date-since`, `date-until`, `limit`, `page`
     * y cabeceras `Authorization: Bearer …`, `client_id: …`.
     *
     * @param  array{
     *     debit_account_number?: string|null,
     *     debit_account_type?: string|null,
     *     debit_bank_number?: string|null,
     *     debit_currency?: string|null,
     *     credit_account_number?: string|null,
     *     credit_account_type?: string|null,
     *     credit_bank_number?: string|null,
     *     credit_currency?: string|null,
     *     date_since?: string|null,
     *     date_until?: string|null,
     *     limit?: int|null,
     *     page?: int|null
     * }  $paramsQuery
     * @return array{ok: bool, general_data: array|null, transfers: array<int, mixed>, error: string|null}
     */
    public function leeTransferencias(int $empresaId, array $paramsQuery): array
    {
        foreach (['debit_bank_number', 'credit_bank_number'] as $bankKey) {
            if (! array_key_exists($bankKey, $paramsQuery) || $paramsQuery[$bankKey] === null || $paramsQuery[$bankKey] === '') {
                continue;
            }
            $bank = $paramsQuery[$bankKey];
            if (! is_string($bank) || ! preg_match('/^[0-9]{3}$/', $bank)) {
                return [
                    'ok' => false,
                    'general_data' => null,
                    'transfers' => [],
                    'error' => $bankKey.' debe ser un código de 3 dígitos.',
                ];
            }
        }

        foreach (['debit_account_type', 'credit_account_type'] as $typeKey) {
            if (! array_key_exists($typeKey, $paramsQuery) || $paramsQuery[$typeKey] === null || $paramsQuery[$typeKey] === '') {
                continue;
            }
            $tipo = $paramsQuery[$typeKey];
            if (! is_string($tipo) || ! in_array($tipo, ['CC', 'CA'], true)) {
                return [
                    'ok' => false,
                    'general_data' => null,
                    'transfers' => [],
                    'error' => $typeKey.' debe ser CC o CA.',
                ];
            }
        }

        foreach (['debit_currency', 'credit_currency'] as $currencyKey) {
            if (! array_key_exists($currencyKey, $paramsQuery) || $paramsQuery[$currencyKey] === null || $paramsQuery[$currencyKey] === '') {
                continue;
            }
            $moneda = $paramsQuery[$currencyKey];
            if (! is_string($moneda) || ! in_array($moneda, ['ARS', 'USD'], true)) {
                return [
                    'ok' => false,
                    'general_data' => null,
                    'transfers' => [],
                    'error' => $currencyKey.' debe ser ARS o USD.',
                ];
            }
        }

        $mapOptional = [
            'debit_account_number' => 'debit-account-number',
            'debit_account_type' => 'debit-account-type',
            'debit_bank_number' => 'debit-bank-number',
            'debit_currency' => 'debit-currency',
            'credit_account_number' => 'credit-account-number',
            'credit_account_type' => 'credit-account-type',
            'credit_bank_number' => 'credit-bank-number',
            'credit_currency' => 'credit-currency',
            'date_since' => 'date-since',
            'date_until' => 'date-until',
        ];

        $queryBase = [];

        foreach ($mapOptional as $inputKey => $apiKey) {
            if (empty($paramsQuery[$inputKey])) {
                continue;
            }
            $queryBase[$apiKey] = (string) $paramsQuery[$inputKey];
        }

        if (isset($paramsQuery['limit'])) {
            $queryBase['limit'] = (string) max(1, (int) $paramsQuery['limit']);
        }
        if (isset($paramsQuery['page'])) {
            $queryBase['page'] = (string) max(0, (int) $paramsQuery['page']);
        }

        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v1/transfers/vouchers';

        $resultado = $this->ejecutarGetAutenticado(
            $empresaId,
            fn (array $ctx): string => $baseUrl.'?'.http_build_query(array_merge($queryBase, [
                'customer-id' => $ctx['customer_id'],
            ])),
            'transferencias'
        );

        if (! $resultado['ok']) {
            return [
                'ok' => false,
                'general_data' => null,
                'transfers' => [],
                'error' => $resultado['error'],
            ];
        }

        $data = $resultado['data'];
        $general = $data['general_data'] ?? null;
        $transferencias = $data['transfers'] ?? null;

        if (! is_array($transferencias)) {
            Log::warning('Interbanking transferencias: sin transfers', [
                'empresa_id' => $empresaId,
                'claves' => array_keys($data),
            ]);

            return [
                'ok' => false,
                'general_data' => is_array($general) ? $general : null,
                'transfers' => [],
                'error' => 'La respuesta no incluye transfers.',
            ];
        }

        return [
            'ok' => true,
            'general_data' => is_array($general) ? $general : null,
            'transfers' => $transferencias,
            'error' => null,
        ];
    }

    /**
     * Pre-confección de transferencia a CBU (API transaccional Confección de Transferencias).
     *
     * Body real (v1.5.x): flags en root + `found_transfers[]` con
     * `debit_account.account_cbu` / `credit_account.account_cbu`, `confection_id` numérico,
     * `request_date`, `amount`.
     *
     * Enganche: OP/I-E → `supplier`; solicitud retiro juego → `third-party`.
     *
     * @param  array{
     *     debit_cbu: string,
     *     credit_cbu: string,
     *     amount: float|int|string,
     *     confection_type?: string,
     *     confection_id?: int|string|null,
     *     request_date?: string|null,
     *     debit_account_number?: string,
     *     debit_bank_number?: string,
     *     debit_account_type?: string,
     *     debit_currency?: string,
     *     concept?: string|null,
     *     description?: string|null,
     *     email?: string|null,
     *     resend_validated?: bool|null,
     *     consolidated_check?: bool|null,
     *     unified_send?: bool|null
     * }  $payload
     * @return array{ok: bool, http_code: int, data: array|null, error: string|null}
     */
    public function crearTransferenciaACbu(int $empresaId, array $payload): array
    {
        $debitCbu = preg_replace('/\D/', '', (string) ($payload['debit_cbu'] ?? '')) ?? '';
        $creditCbu = preg_replace('/\D/', '', (string) ($payload['credit_cbu'] ?? '')) ?? '';
        $amountRaw = $payload['amount'] ?? null;

        if (! preg_match('/^[0-9]{22}$/', $debitCbu)) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'debit_cbu (CBU cuenta origen) debe tener 22 dígitos.',
            ];
        }
        if (! preg_match('/^[0-9]{22}$/', $creditCbu)) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'credit_cbu debe ser un CBU de 22 dígitos.',
            ];
        }
        if ($amountRaw === null || $amountRaw === '' || ! is_numeric($amountRaw) || (float) $amountRaw <= 0) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'amount debe ser un importe numérico mayor a cero.',
            ];
        }

        $confectionId = (int) ($payload['confection_id'] ?? 0);
        if ($confectionId <= 0) {
            $confectionId = (int) substr((string) (time().random_int(100, 999)), -18);
        }
        // API: numérico, máx. 18 dígitos.
        $confectionId = (int) substr((string) $confectionId, 0, 18);

        $requestDate = trim((string) ($payload['request_date'] ?? ''));
        if ($requestDate === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
            $requestDate = date('Y-m-d');
        }

        $concept = trim((string) ($payload['concept'] ?? $payload['description'] ?? ''));
        if ($concept === '') {
            $concept = 'Transferencia';
        }

        $transfer = [
            'confection_id' => $confectionId,
            'request_date' => $requestDate,
            'amount' => round((float) $amountRaw, 2),
            'concept' => $concept,
            'description' => $concept,
            'debit_account' => [
                'account_cbu' => $debitCbu,
            ],
            'credit_account' => [
                'account_cbu' => $creditCbu,
            ],
        ];

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '') {
            $transfer['email'] = $email;
        }

        $body = [
            'consolidated_check' => array_key_exists('consolidated_check', $payload)
                ? (bool) $payload['consolidated_check']
                : false,
            'unified_send' => array_key_exists('unified_send', $payload)
                ? (bool) $payload['unified_send']
                : false,
            'resend_validated' => array_key_exists('resend_validated', $payload)
                ? (bool) $payload['resend_validated']
                : true,
            'found_transfers' => [$transfer],
        ];

        $confectionType = strtolower(trim((string) ($payload['confection_type'] ?? 'supplier')));
        $baseUrl = $this->urlConfeccionTransferencia($confectionType);
        if ($baseUrl === '') {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'Sin URL de confección Interbanking para tipo '.$confectionType.'.',
            ];
        }

        $resultado = $this->ejecutarRequestAutenticado(
            $empresaId,
            'POST',
            function (array $ctx) use ($baseUrl): string {
                $sep = str_contains($baseUrl, '?') ? '&' : '?';

                return $baseUrl.$sep.http_build_query([
                    'customer-id' => $ctx['customer_id'],
                ]);
            },
            $body,
            'crear-transferencia-cbu-'.$confectionType,
            self::TOKEN_USO_CONFECCION
        );

        return $this->interpretarRespuestaConfeccion($resultado);
    }

    /**
     * HTTP 200 puede traer status:false + rejected_transfers (negocio).
     *
     * @param  array{ok: bool, http_code: int, data: array|null, error: string|null}  $resultado
     * @return array{ok: bool, http_code: int, data: array|null, error: string|null}
     */
    private function interpretarRespuestaConfeccion(array $resultado): array
    {
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $data = $resultado['data'];
        if (! is_array($data)) {
            return $resultado;
        }

        $inner = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $accepted = $inner['accepted_transfers'] ?? null;
        $rejected = $inner['rejected_transfers'] ?? null;
        $statusOk = ! empty($inner['status']) || (is_array($accepted) && $accepted !== []);

        if ($statusOk && is_array($accepted) && $accepted !== []) {
            return [
                'ok' => true,
                'http_code' => (int) ($resultado['http_code'] ?? 200),
                'data' => $data,
                'error' => null,
            ];
        }

        $mensajes = [];
        if (is_array($rejected)) {
            foreach ($rejected as $rej) {
                if (! is_array($rej)) {
                    continue;
                }
                $errs = $rej['errors'] ?? [];
                if (! is_array($errs)) {
                    continue;
                }
                foreach ($errs as $err) {
                    if (! is_array($err)) {
                        continue;
                    }
                    $msg = trim((string) ($err['message'] ?? $err['code'] ?? ''));
                    if ($msg !== '') {
                        $mensajes[] = $msg;
                    }
                }
            }
        }

        if ($mensajes === [] && isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $err) {
                if (! is_array($err)) {
                    continue;
                }
                $msg = trim((string) ($err['message'] ?? $err['code'] ?? ''));
                if ($msg !== '') {
                    $mensajes[] = $msg;
                }
            }
        }

        return [
            'ok' => false,
            'http_code' => (int) ($resultado['http_code'] ?? 200),
            'data' => $data,
            'error' => $mensajes !== []
                ? implode(' | ', $mensajes)
                : 'Interbanking no aceptó la transferencia (sin detalle).',
        ];
    }

    /**
     * URL POST Confección según tipo (supplier, third-party, own-account, salary).
     * Si `transferencia_cbu_url` está seteada, manda siempre esa (override legacy).
     */
    private function urlConfeccionTransferencia(string $tipo): string
    {
        $override = trim((string) config('interbanking.transferencia_cbu_url', ''));
        if ($override !== '') {
            return $override;
        }

        $map = config('interbanking.transferencia_confection_urls');
        if (! is_array($map)) {
            return '';
        }

        $url = trim((string) ($map[$tipo] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return trim((string) ($map['supplier'] ?? ''));
    }

    /**
     * Consulta estado de proceso asíncrono de confección.
     *
     * @return array{ok: bool, http_code: int, data: array|null, error: string|null}
     */
    public function leeProcesoAsincronoTransferencia(int $empresaId, string $idProceso): array
    {
        $idProceso = trim($idProceso);
        if ($idProceso === '') {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'idProceso obligatorio.',
            ];
        }

        $base = rtrim((string) config(
            'interbanking.transferencia_proceso_async_url',
            'https://api-gw.interbanking.com.ar/api/prod/v1/transfers/asynchronous-process'
        ), '/');

        return $this->ejecutarRequestAutenticado(
            $empresaId,
            'GET',
            fn (array $ctx): string => $base.'/'.rawurlencode($idProceso).'?'.http_build_query([
                'customer-id' => $ctx['customer_id'],
            ]),
            null,
            'proceso-async-transferencia',
            self::TOKEN_USO_CONFECCION
        );
    }

    private function ensureTokenInterbanking(int $empresaId, string $uso = self::TOKEN_USO_CONSULTA): void
    {
        $path = $this->tokenStoragePath($empresaId, $uso);

        if (! Storage::exists($path)) {
            $this->pideTokenInterbanking($empresaId, $uso);

            return;
        }

        $raw = Storage::get($path);
        $token = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($token) || empty($token['access_token'])) {
            $this->pideTokenInterbanking($empresaId, $uso);

            return;
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        if ($expiresIn <= 0) {
            $expiresIn = 3600;
        }

        $obtainedAt = Storage::lastModified($path);
        $bufferSeconds = 120;
        if (time() >= $obtainedAt + $expiresIn - $bufferSeconds) {
            $this->pideTokenInterbanking($empresaId, $uso);
        }
    }

    public function pideTokenInterbanking(int $empresaId, string $uso = self::TOKEN_USO_CONSULTA): void
    {
        $credenciales = $this->credencialesPorEmpresa($empresaId);
        if ($credenciales === null) {
            Log::warning('Interbanking: sin credenciales OAuth para empresa', ['empresa_id' => $empresaId]);

            return;
        }

        $url = 'https://auth.interbanking.com.ar/cas/oidc/accessToken';
        $postData = [
            'client_id' => $credenciales['client_id'],
            'client_secret' => $credenciales['client_secret'],
        ];

        if ($uso === self::TOKEN_USO_CONFECCION) {
            $operador = $this->credencialesOperadorApi();
            if ($operador === null) {
                Log::warning('Interbanking: sin Operador API para confección', ['empresa_id' => $empresaId]);

                return;
            }
            $scope = trim((string) config('interbanking.confeccion_scope', 'confeccion-transferencias'));
            if ($scope === '') {
                $scope = 'confeccion-transferencias';
            }
            $postData['grant_type'] = 'password';
            $postData['username'] = $operador['username_oauth'];
            $postData['password'] = $operador['password'];
            $postData['scope'] = $scope;
        } else {
            $postData['grant_type'] = 'client_credentials';
            $postData['scope'] = 'info-financiera - Informacion Financiera';
        }

        $curl = curl_init();
        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);
        $this->aplicarProxyCurl($curl);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            Log::warning('Interbanking: error cURL al pedir token', [
                'empresa_id' => $empresaId,
                'uso' => $uso,
                'errno' => curl_errno($curl),
                'error' => curl_error($curl),
            ]);
        } else {
            $result = json_decode($response, true);

            if (isset($result['access_token'])) {
                Storage::put($this->tokenStoragePath($empresaId, $uso), json_encode($result));
            } else {
                Log::warning('Interbanking: error al obtener token', [
                    'empresa_id' => $empresaId,
                    'uso' => $uso,
                    'http' => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
                    'fragmento' => is_string($response) ? substr($response, 0, 500) : null,
                ]);
            }
        }

        curl_close($curl);
    }
}
