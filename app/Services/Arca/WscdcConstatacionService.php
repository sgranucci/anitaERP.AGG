<?php

namespace App\Services\Arca;

use Exception;
use SoapClient;
use SoapFault;

/**
 * WSCDC — Constatación de comprobantes (ComprobanteConstatar).
 *
 * Certificados y TA bajo config/arca_wscdc.php (storage/app/arca/wscdc/),
 * independiente del padrón y de WSFE/MTXCA.
 */
class WscdcConstatacionService
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
     * @param  array{
     *     cbte_modo: string,
     *     cuit_emisor: string,
     *     pto_vta: int,
     *     cbte_tipo: int,
     *     cbte_nro: int,
     *     cbte_fch: string,
     *     imp_total: float,
     *     cod_autorizacion: string,
     *     doc_tipo_receptor?: string|null,
     *     doc_nro_receptor?: string|null,
     * }  $cmpReq
     * @return array<string, mixed>
     */
    public function comprobanteConstatar(array $cmpReq): array
    {
        $this->lastSoapTrace = null;

        if (! filter_var(config('arca_wscdc.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            throw new Exception('WSCDC deshabilitado (ARCA_WSCDC_HABILITADO=false).');
        }

        $cuitRepresentada = $this->soloDigitos((string) config('arca_wscdc.cuit_representada'));
        if (strlen($cuitRepresentada) !== 11) {
            throw new Exception('ARCA_WSCDC_CUIT_REPRESENTADA no configurada (ver config/arca_wscdc.php).');
        }

        $this->validarCmpReq($cmpReq);

        $serviceId = (string) config('arca_wscdc.wsaa_service_id', 'wscdc');
        $ts = $this->wsaaService->getTokenSign($serviceId, $this->wsaaContextWscdc());

        $timeout = max(5, (int) config('arca_wscdc.soap_timeout', 30));
        $client = new SoapClient($this->resolveWsdl(), [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => $this->soapStreamContext($timeout),
        ]);

        $payload = [
            'Auth' => [
                'Token' => $ts['token'],
                'Sign' => $ts['sign'],
                'Cuit' => (float) $cuitRepresentada,
            ],
            'CmpReq' => [
                'CbteModo' => (string) $cmpReq['cbte_modo'],
                'CuitEmisor' => (float) $this->soloDigitos((string) $cmpReq['cuit_emisor']),
                'PtoVta' => (int) $cmpReq['pto_vta'],
                'CbteTipo' => (int) $cmpReq['cbte_tipo'],
                'CbteNro' => (int) $cmpReq['cbte_nro'],
                'CbteFch' => (string) $cmpReq['cbte_fch'],
                'ImpTotal' => round((float) $cmpReq['imp_total'], 2),
                'CodAutorizacion' => (string) $cmpReq['cod_autorizacion'],
            ],
        ];

        if (! empty($cmpReq['doc_tipo_receptor']) && ! empty($cmpReq['doc_nro_receptor'])) {
            $payload['CmpReq']['DocTipoReceptor'] = (string) $cmpReq['doc_tipo_receptor'];
            $payload['CmpReq']['DocNroReceptor'] = $this->soloDigitos((string) $cmpReq['doc_nro_receptor']);
        }

        try {
            $resp = $client->ComprobanteConstatar($payload);
            $result = $resp->ComprobanteConstatarResult ?? null;
            if ($result === null) {
                throw new Exception('WSCDC: respuesta inválida (sin ComprobanteConstatarResult).');
            }

            $data = $this->normalizarResultado($result);
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
     * @return array<string, string>
     */
    private function wsaaContextWscdc(): array
    {
        return [
            'cert_path' => (string) config('arca_wscdc.cert_path'),
            'private_key_path' => (string) config('arca_wscdc.private_key_path'),
            'private_key_passphrase' => (string) config('arca_wscdc.private_key_passphrase', ''),
            'ta_storage_dir' => (string) config('arca_wscdc.ta_storage_dir'),
            'cache_key' => 'wscdc',
            'tmp_dir' => (string) config('arca_wscdc.tmp_dir'),
        ];
    }

    private function resolveWsdl(): string
    {
        $env = (string) config('arca.env', 'homo');

        $override = env('ARCA_WSCDC_WSDL_LOCAL');
        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $configured = config("arca_wscdc.wscdc.{$env}.wsdl_local");
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $remote = config("arca_wscdc.wscdc.{$env}.wsdl");
        if (! is_string($remote) || $remote === '') {
            throw new Exception("WSCDC: WSDL no configurado para env={$env}");
        }

        if (is_string($configured) && $configured !== '' && $this->tryCacheWsdlFromRemote($remote, $configured)) {
            return $configured;
        }

        return $remote;
    }

    /**
     * @return resource
     */
    private function soapStreamContext(int $timeout)
    {
        return stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'anitaERP-ARCA-WSCDC',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'ciphers' => 'DEFAULT@SECLEVEL=1',
            ],
        ]);
    }

    private function tryCacheWsdlFromRemote(string $remoteUrl, string $localPath): bool
    {
        try {
            $dir = dirname($localPath);
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return false;
            }

            $timeout = max(10, (int) config('arca_wscdc.soap_timeout', 30));

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

            return $this->tryCacheWsdlViaCurl($remoteUrl, $localPath, $timeout);
        } catch (\Throwable) {
            return false;
        }
    }

    private function tryCacheWsdlViaCurl(string $remoteUrl, string $localPath, int $timeout): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $cmd = sprintf(
            'curl -fsSL --max-time %d --ciphers %s %s -o %s 2>/dev/null',
            $timeout,
            escapeshellarg('DEFAULT@SECLEVEL=1'),
            escapeshellarg($remoteUrl),
            escapeshellarg($localPath)
        );

        @exec($cmd, $out, $code);

        return $code === 0 && is_readable($localPath);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarResultado(object $result): array
    {
        $cmp = $result->CmpResp ?? null;

        return [
            'resultado' => isset($result->Resultado) ? (string) $result->Resultado : null,
            'fch_proceso' => isset($result->FchProceso) ? (string) $result->FchProceso : null,
            'cmp_resp' => $cmp !== null ? [
                'cbte_modo' => isset($cmp->CbteModo) ? (string) $cmp->CbteModo : null,
                'cuit_emisor' => isset($cmp->CuitEmisor) ? (string) $cmp->CuitEmisor : null,
                'pto_vta' => isset($cmp->PtoVta) ? (int) $cmp->PtoVta : null,
                'cbte_tipo' => isset($cmp->CbteTipo) ? (int) $cmp->CbteTipo : null,
                'cbte_nro' => isset($cmp->CbteNro) ? (int) $cmp->CbteNro : null,
                'cbte_fch' => isset($cmp->CbteFch) ? (string) $cmp->CbteFch : null,
                'imp_total' => isset($cmp->ImpTotal) ? (float) $cmp->ImpTotal : null,
                'cod_autorizacion' => isset($cmp->CodAutorizacion) ? (string) $cmp->CodAutorizacion : null,
                'doc_tipo_receptor' => isset($cmp->DocTipoReceptor) ? (string) $cmp->DocTipoReceptor : null,
                'doc_nro_receptor' => isset($cmp->DocNroReceptor) ? (string) $cmp->DocNroReceptor : null,
            ] : null,
            'observaciones' => $this->normalizarMensajes($result->Observaciones ?? null, 'Obs'),
            'errores' => $this->normalizarMensajes($result->Errors ?? null, 'Err'),
            'eventos' => $this->normalizarMensajes($result->Events ?? null, 'Evt'),
            'raw' => $result,
        ];
    }

    /**
     * @return list<array{code: int|null, msg: string}>
     */
    private function normalizarMensajes(mixed $contenedor, string $nodo): array
    {
        if ($contenedor === null) {
            return [];
        }

        $items = $contenedor->{$nodo} ?? null;
        if ($items === null) {
            return [];
        }

        $lista = is_array($items) ? $items : [$items];
        $out = [];
        foreach ($lista as $item) {
            if (! is_object($item)) {
                continue;
            }
            $msg = trim((string) ($item->Msg ?? ''));
            if ($msg === '') {
                continue;
            }
            $out[] = [
                'code' => isset($item->Code) ? (int) $item->Code : null,
                'msg' => $msg,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cmpReq
     */
    private function validarCmpReq(array $cmpReq): void
    {
        foreach (['cbte_modo', 'cuit_emisor', 'pto_vta', 'cbte_tipo', 'cbte_nro', 'cbte_fch', 'cod_autorizacion'] as $key) {
            if (! array_key_exists($key, $cmpReq) || $cmpReq[$key] === '' || $cmpReq[$key] === null) {
                throw new Exception("WSCDC: falta campo obligatorio «{$key}» en la solicitud.");
            }
        }

        if (strlen($this->soloDigitos((string) $cmpReq['cuit_emisor'])) !== 11) {
            throw new Exception('WSCDC: CUIT emisor inválido.');
        }

        if (! preg_match('/^\d{8}$/', (string) $cmpReq['cbte_fch'])) {
            throw new Exception('WSCDC: fecha de comprobante inválida (se espera yyyymmdd).');
        }

        $cae = preg_replace('/\D/', '', (string) $cmpReq['cod_autorizacion']) ?? '';
        if (strlen($cae) !== 14) {
            throw new Exception('WSCDC: código de autorización debe tener 14 dígitos.');
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

        return 'WSCDC SOAP: '.$detalle;
    }

    private function soloDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }
}
