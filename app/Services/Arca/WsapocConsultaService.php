<?php

namespace App\Services\Arca;

use Exception;
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
        $this->lastSoapTrace = null;

        if (! $this->habilitado()) {
            throw new Exception('WSAPOC deshabilitado (ARCA_WSAPOC_HABILITADO=false).');
        }

        $client = $this->crearClienteSoap();

        try {
            $resp = $client->Dummy();
            $result = $resp->DummyResult ?? null;
            if ($result === null) {
                throw new Exception('WSAPOC: respuesta inválida (sin DummyResult).');
            }

            $data = [
                'appserver' => isset($result->appserver) ? (string) $result->appserver : null,
                'dbserver' => isset($result->dbserver) ? (string) $result->dbserver : null,
                'authserver' => isset($result->authserver) ? (string) $result->authserver : null,
                'soap' => $this->captureSoapTrace($client),
            ];
            $data['ok'] = ($data['appserver'] ?? '') === 'OK' && ($data['dbserver'] ?? '') === 'OK';

            return $data;
        } catch (SoapFault $e) {
            $this->lastSoapTrace = $this->captureSoapTrace($client);
            throw new Exception($this->formatSoapFault($e, $client), (int) $e->getCode(), $e);
        } finally {
            if ($this->lastSoapTrace === null) {
                $this->lastSoapTrace = $this->captureSoapTrace($client);
            }
        }
    }

    /**
     * Consulta si una CUIT está publicada en la base APOC.
     *
     * @return array<string, mixed>
     */
    public function getPublicacionApoc(string $cuitConsultada): array
    {
        $this->lastSoapTrace = null;

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

        $client = $this->crearClienteSoap();

        try {
            $resp = $client->GetPublicacionAPOC([
                'Credencial' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'CUITDelegado' => $cuitRepresentada,
                ],
                'cuit' => (float) $cuitConsultada,
            ]);

            $result = $resp->GetPublicacionAPOCResult ?? null;
            if ($result === null) {
                throw new Exception('WSAPOC: respuesta inválida (sin GetPublicacionAPOCResult).');
            }

            $data = $this->normalizarMessageResponse($result, $cuitConsultada);
            $data['soap'] = $this->captureSoapTrace($client);

            return $data;
        } catch (SoapFault $e) {
            $this->lastSoapTrace = $this->captureSoapTrace($client);
            throw new Exception($this->formatSoapFault($e, $client), (int) $e->getCode(), $e);
        } finally {
            if ($this->lastSoapTrace === null) {
                $this->lastSoapTrace = $this->captureSoapTrace($client);
            }
        }
    }

    /**
     * Novedades APOC en un rango de fechas (DD/MM/YYYY).
     *
     * @return array<string, mixed>
     */
    public function getAllByPublicacion(string $desde, string $hasta): array
    {
        $this->lastSoapTrace = null;

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

        $client = $this->crearClienteSoap();

        try {
            $resp = $client->GetAllByPublicacion([
                'Credencial' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'CUITDelegado' => $cuitRepresentada,
                ],
                'desde' => $desde,
                'hasta' => $hasta,
            ]);

            $result = $resp->GetAllByPublicacionResult ?? null;
            if ($result === null) {
                throw new Exception('WSAPOC: respuesta inválida (sin GetAllByPublicacionResult).');
            }

            $data = $this->normalizarMessageResponse($result, null);
            $data['soap'] = $this->captureSoapTrace($client);

            return $data;
        } catch (SoapFault $e) {
            $this->lastSoapTrace = $this->captureSoapTrace($client);
            throw new Exception($this->formatSoapFault($e, $client), (int) $e->getCode(), $e);
        } finally {
            if ($this->lastSoapTrace === null) {
                $this->lastSoapTrace = $this->captureSoapTrace($client);
            }
        }
    }

    private function habilitado(): bool
    {
        return filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
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
}
