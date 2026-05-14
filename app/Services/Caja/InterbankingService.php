<?php

namespace App\Services\Caja;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InterbankingService
{
    public function __construct(
    ) {}

    private function tokenStoragePath(int $empresaId): string
    {
        return 'tokeninterbanking_'.$empresaId.'.json';
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
     * Token OAuth y datos de cliente para llamadas a la API (balances, movimientos, etc.).
     *
     * @return array{ok: true, access_token: string, client_id: string, customer_id: string}|array{ok: false, error: string}
     */
    private function contextoApiInterbanking(int $empresaId): array
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

        try {
            $this->ensureTokenInterbanking($empresaId);
        } catch (Throwable $e) {
            Log::warning('Interbanking: fallo al asegurar token', ['exception' => $e->getMessage()]);

            return [
                'ok' => false,
                'error' => 'No se pudo preparar el acceso a Interbanking.',
            ];
        }

        $path = $this->tokenStoragePath($empresaId);
        if (! Storage::exists($path)) {
            return [
                'ok' => false,
                'error' => 'No hay token de Interbanking; verifique credenciales y conectividad.',
            ];
        }

        $token = json_decode(Storage::get($path));
        if (! $token || empty($token->access_token)) {
            Log::warning('Interbanking: token ausente o JSON inválido');

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
     * Consulta saldos en Interbanking.
     *
     * @return array{ok: bool, accounts: array, error: string|null}
     */
    public function leeSaldos($empresa_id, $currency)
    {
        $ctx = $this->contextoApiInterbanking((int) $empresa_id);
        if (! $ctx['ok']) {
            return [
                'ok' => false,
                'accounts' => [],
                'error' => $ctx['error'],
            ];
        }

        $params = [
            'currency' => $currency,
            'customer-id' => $ctx['customer_id'],
        ];

        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v1/accounts/balances';
        $url = $baseUrl.'?'.http_build_query($params);

        $headers = [
            'Authorization: Bearer '.$ctx['access_token'],
            'accept: application/json',
            'client_id: '.$ctx['client_id'],
        ];

        $ch = curl_init();

        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($errno !== 0) {
                $curlErr = curl_error($ch);
                Log::warning('Interbanking cURL', ['errno' => $errno, 'error' => $curlErr]);

                return [
                    'ok' => false,
                    'accounts' => [],
                    'error' => 'No se pudo conectar con Interbanking: '.$curlErr,
                ];
            }

            $data = json_decode($response, true);

            if (! is_array($data)) {
                Log::warning('Interbanking: respuesta no es JSON', [
                    'http' => $httpCode,
                    'fragmento' => is_string($response) ? substr($response, 0, 500) : null,
                ]);

                return [
                    'ok' => false,
                    'accounts' => [],
                    'error' => 'Respuesta inválida del servicio (HTTP '.$httpCode.').',
                ];
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = $data['message']
                    ?? $data['error']
                    ?? $data['error_description']
                    ?? ('Código HTTP '.$httpCode);
                Log::warning('Interbanking: error HTTP', ['http' => $httpCode, 'cuerpo' => $data]);

                return [
                    'ok' => false,
                    'accounts' => [],
                    'error' => $msg,
                ];
            }

            if (! array_key_exists('accounts', $data)) {
                Log::warning('Interbanking: JSON sin clave accounts', ['claves' => array_keys($data)]);

                return [
                    'ok' => false,
                    'accounts' => [],
                    'error' => 'La respuesta no incluye el listado de cuentas (accounts).',
                ];
            }

            if (! is_array($data['accounts'])) {
                Log::warning('Interbanking: accounts no es un array');

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
        } catch (Throwable $e) {
            Log::warning('Interbanking: excepción en leeSaldos', ['exception' => $e->getMessage()]);

            return [
                'ok' => false,
                'accounts' => [],
                'error' => 'Error al consultar Interbanking.',
            ];
        } finally {
            curl_close($ch);
        }
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

        $ctx = $this->contextoApiInterbanking($empresaId);
        if (! $ctx['ok']) {
            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => $ctx['error'],
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

        $pathAccount = rawurlencode($accountNumber);
        $pathTipo = rawurlencode($movementType);
        $baseUrl = 'https://api-gw.interbanking.com.ar/api/prod/v2/accounts/'.$pathAccount.'/movements/'.$pathTipo;
        $url = $baseUrl.'?'.http_build_query($query);

        $headers = [
            'Authorization: Bearer '.$ctx['access_token'],
            'accept: application/json',
            'client_id: '.$ctx['client_id'],
        ];

        $ch = curl_init();

        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($errno !== 0) {
                $curlErr = curl_error($ch);
                Log::warning('Interbanking movimientos cURL', ['errno' => $errno, 'error' => $curlErr]);

                return [
                    'ok' => false,
                    'general_data' => null,
                    'movements_detail' => [],
                    'error' => 'No se pudo conectar con Interbanking: '.$curlErr,
                ];
            }

            $data = json_decode($response, true);

            if (! is_array($data)) {
                Log::warning('Interbanking movimientos: respuesta no es JSON', [
                    'http' => $httpCode,
                    'fragmento' => is_string($response) ? substr($response, 0, 500) : null,
                ]);

                return [
                    'ok' => false,
                    'general_data' => null,
                    'movements_detail' => [],
                    'error' => 'Respuesta inválida del servicio (HTTP '.$httpCode.').',
                ];
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $msg = $data['message']
                    ?? $data['error']
                    ?? $data['error_description']
                    ?? ($data['code'] ?? null)
                    ?? ('Código HTTP '.$httpCode);
                if (is_array($msg)) {
                    $msg = json_encode($msg);
                }
                Log::warning('Interbanking movimientos: error HTTP', ['http' => $httpCode, 'cuerpo' => $data]);

                return [
                    'ok' => false,
                    'general_data' => null,
                    'movements_detail' => [],
                    'error' => (string) $msg,
                ];
            }

            $general = $data['general_data'] ?? null;
            $detalle = $data['movements_detail'] ?? null;
            if (! is_array($detalle)) {
                Log::warning('Interbanking movimientos: sin movements_detail', ['claves' => array_keys($data)]);

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
        } catch (Throwable $e) {
            Log::warning('Interbanking: excepción en leeMovimientos', ['exception' => $e->getMessage()]);

            return [
                'ok' => false,
                'general_data' => null,
                'movements_detail' => [],
                'error' => 'Error al consultar movimientos en Interbanking.',
            ];
        } finally {
            curl_close($ch);
        }
    }

    private function ensureTokenInterbanking(int $empresaId): void
    {
        $path = $this->tokenStoragePath($empresaId);

        if (! Storage::exists($path)) {
            $this->pideTokenInterbanking($empresaId);

            return;
        }

        $raw = Storage::get($path);
        $token = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($token) || empty($token['access_token'])) {
            $this->pideTokenInterbanking($empresaId);

            return;
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        if ($expiresIn <= 0) {
            $expiresIn = 3600;
        }

        $obtainedAt = Storage::lastModified($path);
        $bufferSeconds = 120;
        if (time() >= $obtainedAt + $expiresIn - $bufferSeconds) {
            $this->pideTokenInterbanking($empresaId);
        }
    }

    public function pideTokenInterbanking(int $empresaId): void
    {
        $credenciales = $this->credencialesPorEmpresa($empresaId);
        if ($credenciales === null) {
            Log::warning('Interbanking: sin credenciales OAuth para empresa', ['empresa_id' => $empresaId]);

            return;
        }

        $url = 'https://auth.interbanking.com.ar/cas/oidc/accessToken';
        $clienteId = $credenciales['client_id'];
        $clientSecret = $credenciales['client_secret'];

        $curl = curl_init();

        $header = ['Content-Type: application/x-www-form-urlencoded'];

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $clienteId,
            'client_secret' => $clientSecret,
            'scope' => 'info-financiera - Informacion Financiera',
        ];

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0 );

        $response = curl_exec($curl);

        // 5. Manejar errores
        if (curl_errno($curl)) {
            Log::info('Error en cURL: '.curl_error($curl));
        } else {
            // 6. Decodificar la respuesta
            $result = json_decode($response, true);

            if (isset($result['access_token'])) {

                Storage::put($this->tokenStoragePath($empresaId), json_encode($result));

            } else {
                Log::info('Error al obtener token: '.$response);
            }
        }

        // 7. Cerrar sesi┬ón cURL
        curl_close($curl);

    }
}
