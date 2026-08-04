<?php

namespace App\Services\Arca;

use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * WSAPOC — Consulta de contribuyentes calificados como apócrifos (ARCA).
 *
 * Certificados y TA bajo config/arca_wsapoc.php (storage/app/arca/wsapoc/).
 */
class WsapocConsultaService
{
    /** @var array{request: string, response: string}|null */
    private ?array $lastSoapTrace = null;

    public function __construct(private WsaaService $wsaaService) {}

    /**
     * Aviso de UI cuando ARCA/WSAPOC no responde: no bloquea operaciones.
     */
    public static function mensajeAvisoNoDisponible(): string
    {
        return 'Consulta de facturas apócrifas no disponible por el momento (ARCA). Puede continuar con normalidad.';
    }

    /**
     * @return array{request: string, response: string}|null
     */
    public function getLastSoapTrace(): ?array
    {
        return $this->lastSoapTrace;
    }

    /**
     * Test de conectividad (Dummy).
     *
     * @return array<string, mixed>
     */
    public function dummy(): array
    {
        if (! $this->habilitado()) {
            throw new Exception('WSAPOC deshabilitado (ARCA_WSAPOC_HABILITADO=false).');
        }

        $result = $this->invocarConReintentos('Dummy', [], 'DummyResult');

        $data = [
            'appserver' => isset($result->appserver) ? (string) $result->appserver : null,
            'dbserver' => isset($result->dbserver) ? (string) $result->dbserver : null,
            'authserver' => isset($result->authserver) ? (string) $result->authserver : null,
            'soap' => $this->lastSoapTrace,
        ];
        $data['ok'] = ($data['appserver'] ?? '') === 'OK' && ($data['dbserver'] ?? '') === 'OK';

        return $data;
    }

    /**
     * Consulta si una CUIT está publicada en la base APOC.
     *
     * @return array<string, mixed>
     */
    public function getPublicacionApoc(string $cuitConsultada): array
    {
        if (! $this->habilitado()) {
            throw new Exception('WSAPOC deshabilitado (ARCA_WSAPOC_HABILITADO=false).');
        }

        $cuitConsultada = $this->soloDigitos($cuitConsultada);
        if (strlen($cuitConsultada) !== 11) {
            throw new Exception('WSAPOC: CUIT inválida (debe tener 11 dígitos).');
        }

        $cuitRepresentada = $this->soloDigitos((string) config('arca_wsapoc.cuit_representada'));
        if (strlen($cuitRepresentada) !== 11) {
            throw new Exception('ARCA_WSAPOC_CUIT_REPRESENTADA no configurada (ver config/arca_wsapoc.php).');
        }

        $serviceId = (string) config('arca_wsapoc.wsaa_service_id', 'wsapoc');
        $ts = $this->wsaaService->getTokenSign($serviceId, $this->wsaaContextWsapoc());

        $result = $this->invocarConReintentos('GetPublicacionAPOC', [
            'Credencial' => [
                'Token' => $ts['token'],
                'Sign' => $ts['sign'],
                'CUITDelegado' => $cuitRepresentada,
            ],
            'cuit' => (float) $cuitConsultada,
        ], 'GetPublicacionAPOCResult');

        $data = $this->normalizarMessageResponse($result, $cuitConsultada);
        $data['soap'] = $this->lastSoapTrace;

        return $data;
    }

    /**
     * Novedades APOC en un rango de fechas (DD/MM/YYYY).
     *
     * @return array<string, mixed>
     */
    public function getAllByPublicacion(string $desde, string $hasta): array
    {
        if (! $this->habilitado()) {
            throw new Exception('WSAPOC deshabilitado (ARCA_WSAPOC_HABILITADO=false).');
        }

        $this->validarFechaApoc($desde, 'desde');
        $this->validarFechaApoc($hasta, 'hasta');

        $cuitRepresentada = $this->soloDigitos((string) config('arca_wsapoc.cuit_representada'));
        if (strlen($cuitRepresentada) !== 11) {
            throw new Exception('ARCA_WSAPOC_CUIT_REPRESENTADA no configurada (ver config/arca_wsapoc.php).');
        }

        $serviceId = (string) config('arca_wsapoc.wsaa_service_id', 'wsapoc');
        $ts = $this->wsaaService->getTokenSign($serviceId, $this->wsaaContextWsapoc());

        $result = $this->invocarConReintentos('GetAllByPublicacion', [
            'Credencial' => [
                'Token' => $ts['token'],
                'Sign' => $ts['sign'],
                'CUITDelegado' => $cuitRepresentada,
            ],
            'desde' => $desde,
            'hasta' => $hasta,
        ], 'GetAllByPublicacionResult');

        $data = $this->normalizarMessageResponse($result, null);
        $data['soap'] = $this->lastSoapTrace;

        return $data;
    }

    private function habilitado(): bool
    {
        return filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Invoca un método WSAPOC con reintentos ante fallas transitorias de ARCA
     * (SoapFault de transporte o respuesta 200 sin el elemento *Result esperado).
     * Actualiza $this->lastSoapTrace con la traza del último intento.
     *
     * @param  array<string, mixed>  $args
     *
     * @throws Exception  cuando se agotan los reintentos o el fallo no es transitorio
     */
    private function invocarConReintentos(string $metodo, array $args, string $resultProp): object
    {
        $this->lastSoapTrace = null;
        $maxIntentos = max(1, (int) config('arca_wsapoc.reintentos', 3));
        $pausaMs = max(0, (int) config('arca_wsapoc.reintento_pausa_ms', 500));

        $ultimoError = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $client = $this->crearClienteSoap();

            try {
                $resp = $args === [] ? $client->{$metodo}() : $client->{$metodo}($args);
                $this->lastSoapTrace = $this->captureSoapTrace($client);

                $result = $resp->{$resultProp} ?? null;
                if (is_object($result)) {
                    return $result;
                }

                // HTTP 200 pero sin el elemento esperado: intermitencia de ARCA
                // (a veces body HTML vacío/con ticket en lugar de SOAP).
                $respBody = (string) ($this->lastSoapTrace['response'] ?? '');
                $detalle = $this->esRespuestaHtmlOVacia($respBody)
                    ? 'WSAPOC: ARCA devolvió HTML/vacío en lugar de SOAP (sin '.$resultProp.').'
                    : "WSAPOC: respuesta inválida (sin {$resultProp}).";
                $ultimoError = new Exception($detalle);
                $this->logIntentoFallido($metodo, $intento, $maxIntentos, $ultimoError->getMessage(), true);
            } catch (SoapFault $e) {
                $this->lastSoapTrace = $this->captureSoapTrace($client);
                $mensaje = $this->formatSoapFault($e, $client);

                if (! $this->esFallaTransitoria($e)) {
                    throw new Exception($mensaje, (int) $e->getCode(), $e);
                }

                $ultimoError = new Exception($mensaje, (int) $e->getCode(), $e);
                $this->logIntentoFallido($metodo, $intento, $maxIntentos, $e->getMessage(), false);
            }

            if ($intento < $maxIntentos && $pausaMs > 0) {
                usleep($pausaMs * 1000);
            }
        }

        throw $ultimoError ?? new Exception("WSAPOC: respuesta inválida (sin {$resultProp}).");
    }

    private function esFallaTransitoria(SoapFault $e): bool
    {
        $msg = mb_strtolower(trim((string) $e->getMessage()));
        if ($msg === '') {
            return true;
        }

        $patronesTransitorios = [
            'error fetching http headers',
            'could not connect to host',
            'failed to connect',
            'connection reset',
            'connection timed out',
            'timed out',
            'timeout',
            'error interno',
            'service unavailable',
            'bad gateway',
            'gateway time',
            'http error',
            'error de transporte',
            // ARCA a veces responde HTML vacío/con ticket en vez de SOAP (WAF/caída).
            'looks like we got no xml',
            'no xml document',
            'unexpected end of file',
            'start tag expected',
            '<html',
            '502',
            '503',
            '504',
        ];

        foreach ($patronesTransitorios as $patron) {
            if (str_contains($msg, $patron)) {
                return true;
            }
        }

        return false;
    }

    private function logIntentoFallido(string $metodo, int $intento, int $maxIntentos, string $error, bool $sinResultado): void
    {
        $response = (string) ($this->lastSoapTrace['response'] ?? '');

        Log::warning('WSAPOC intento fallido', [
            'metodo' => $metodo,
            'intento' => $intento,
            'max_intentos' => $maxIntentos,
            'sin_resultado' => $sinResultado,
            'error' => $error,
            'response' => $response !== '' ? mb_substr($response, 0, 1000) : null,
        ]);
    }

    private function crearClienteSoap(): SoapClient
    {
        $timeout = max(5, (int) config('arca_wsapoc.soap_timeout', 30));

        return new SoapClient($this->resolveWsdl(), [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'anitaERP-ARCA-WSAPOC',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'ciphers' => 'DEFAULT@SECLEVEL=1',
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function wsaaContextWsapoc(): array
    {
        return [
            'cert_path' => (string) config('arca_wsapoc.cert_path'),
            'private_key_path' => (string) config('arca_wsapoc.private_key_path'),
            'private_key_passphrase' => (string) config('arca_wsapoc.private_key_passphrase', ''),
            'ta_storage_dir' => (string) config('arca_wsapoc.ta_storage_dir'),
            'cache_key' => 'wsapoc',
            'tmp_dir' => (string) config('arca_wsapoc.tmp_dir'),
        ];
    }

    private function resolveWsdl(): string
    {
        $env = (string) config('arca.env', 'homo');

        $override = env('ARCA_WSAPOC_WSDL_LOCAL');
        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $configured = config("arca_wsapoc.wsapoc.{$env}.wsdl_local");
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $remote = config("arca_wsapoc.wsapoc.{$env}.wsdl");
        if (! is_string($remote) || $remote === '') {
            throw new Exception("WSAPOC: WSDL no configurado para env={$env}");
        }

        if (is_string($configured) && $configured !== '' && $this->tryCacheWsdlFromRemote($remote, $configured)) {
            return $configured;
        }

        return $remote;
    }

    private function tryCacheWsdlFromRemote(string $remoteUrl, string $localPath): bool
    {
        try {
            $dir = dirname($localPath);
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return false;
            }

            $timeout = max(10, (int) config('arca_wsapoc.soap_timeout', 30));
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'ciphers' => 'DEFAULT@SECLEVEL=1',
                ],
            ]);

            $body = @file_get_contents($remoteUrl, false, $ctx);
            if (is_string($body) && $body !== '' && @file_put_contents($localPath, $body) !== false) {
                return is_readable($localPath);
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarMessageResponse(object $result, ?string $cuitConsultada): array
    {
        $codigo = trim((string) ($result->codigo ?? $result->Codigo ?? '0'));
        $descripcion = trim((string) ($result->descripcion ?? $result->Descripcion ?? ''));
        $publicaciones = $this->extraerPublicaciones($result->resultados ?? $result->Resultados ?? null);

        $codigoInt = is_numeric($codigo) ? (int) $codigo : -1;
        $esApocrifo = $codigoInt === 0 && $publicaciones !== [];

        return [
            'codigo' => $codigo,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'cuit_consultada' => $cuitConsultada,
            'publicaciones' => $publicaciones,
            'es_apocrifo' => $esApocrifo,
            'ok' => $codigoInt === 0,
            'error_servicio' => $codigoInt > 0,
            'raw' => $result,
        ];
    }

    /**
     * @return list<array{cuit: string, descripcion: string|null, fecha_condicion: string|null, fecha_publicacion: string|null}>
     */
    private function extraerPublicaciones(mixed $resultados): array
    {
        if ($resultados === null) {
            return [];
        }

        $items = $resultados->PublicacionAPOC ?? null;
        if ($items === null) {
            return [];
        }

        $lista = is_array($items) ? $items : [$items];
        $out = [];

        foreach ($lista as $item) {
            if (! is_object($item)) {
                continue;
            }

            $cuit = isset($item->Cuit) ? $this->soloDigitos((string) $item->Cuit) : '';
            if ($cuit === '') {
                continue;
            }

            $out[] = [
                'cuit' => $cuit,
                'descripcion' => isset($item->Descripcion) ? trim((string) $item->Descripcion) : null,
                'fecha_condicion' => isset($item->FechaCondicion) ? trim((string) $item->FechaCondicion) : null,
                'fecha_publicacion' => isset($item->FechaPublicacion) ? trim((string) $item->FechaPublicacion) : null,
            ];
        }

        return $out;
    }

    private function validarFechaApoc(string $fecha, string $campo): void
    {
        if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            throw new Exception("WSAPOC: fecha «{$campo}» inválida (formato DD/MM/YYYY).");
        }
    }

    /**
     * @return array{request: string, response: string}
     */
    private function captureSoapTrace(SoapClient $client): array
    {
        return [
            'request' => (string) ($client->__getLastRequest() ?: ''),
            'response' => (string) ($client->__getLastResponse() ?: ''),
        ];
    }

    private function formatSoapFault(SoapFault $e, SoapClient $client): string
    {
        $detalle = trim((string) $e->getMessage());
        $response = (string) ($client->__getLastResponse() ?: '');
        if ($response !== '' && strlen($response) < 500) {
            $detalle .= ' — '.$response;
        }

        return 'WSAPOC SOAP: '.$detalle;
    }

    private function soloDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function esRespuestaHtmlOVacia(string $response): bool
    {
        $trim = trim($response);
        if ($trim === '') {
            return true;
        }

        $lower = mb_strtolower($trim);

        return str_starts_with($lower, '<html')
            || str_contains($lower, '<html')
            || (! str_contains($lower, 'soap') && ! str_contains($lower, '<?xml'));
    }
}
