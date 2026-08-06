<?php

namespace App\Services\Arca;

use Exception;
use SoapClient;
use SoapFault;

/**
 * Cliente SOAP wsremcarne (RemCarneService) — remito electrónico cárnico.
 *
 * Port de afip_recprod/include/RemCarne.php + remito_electronico.fc.
 * Certificados bajo storage/app/arca/wsremcarne/certs/{carpeta}/ (Surmar/El Bierzo).
 */
class ArcaWsremcarneService
{
    public function __construct(
        private WsaaService $wsaa,
    ) {
    }

    public function dummy(int $empresaId): array
    {
        $this->assertHabilitado();
        $client = $this->soapClient();
        try {
            $raw = $client->dummy();
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('dummy', $e, $client));
        }

        $ret = $raw->dummyReturn ?? $raw;

        return [
            'appserver' => (string) ($ret->appserver ?? ''),
            'authserver' => (string) ($ret->authserver ?? ''),
            'dbserver' => (string) ($ret->dbserver ?? ''),
        ];
    }

    /**
     * @param  array{
     *   id_req: int,
     *   cuit_representada?: int|string|null,
     *   remito: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function generarRemito(int $empresaId, array $payload): array
    {
        $this->assertHabilitado();
        $cuit = $this->cuitRepresentada($empresaId, $payload['cuit_representada'] ?? null);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsremcarne.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $req = [
            'authRequest' => [
                'token' => $ts['token'],
                'sign' => $ts['sign'],
                'cuitRepresentada' => $cuit,
            ],
            'idReq' => (int) $payload['id_req'],
            'remito' => $payload['remito'],
        ];

        try {
            $raw = $client->generarRemito($req);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('generarRemito', $e, $client));
        }

        $ret = $raw->generarRemitoReturn ?? null;
        if ($ret === null) {
            throw new Exception('wsremcarne: generarRemito sin generarRemitoReturn.');
        }

        $this->assertSinErrores($ret, 'generarRemito');

        return $this->normalizarRemitoReturn($ret);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultarUltimoRemitoEmitido(int $empresaId, int $puntoEmision, ?int $cuitOverride = null): array
    {
        $this->assertHabilitado();
        $cuit = $this->cuitRepresentada($empresaId, $cuitOverride);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsremcarne.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $req = [
            'authRequest' => [
                'token' => $ts['token'],
                'sign' => $ts['sign'],
                'cuitRepresentada' => $cuit,
            ],
            'tipoComprobante' => (int) config('arca_wsremcarne.defaults.tipo_comprobante', 995),
            'puntoEmision' => $puntoEmision,
        ];

        try {
            $raw = $client->consultarUltimoRemitoEmitido($req);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarUltimoRemitoEmitido', $e, $client));
        }

        $ret = $raw->consultarUltimoRemitoReturn
            ?? $raw->consultarUltimoRemitoEmitidoReturn
            ?? $raw;

        $remito = $ret->remito ?? null;
        $emision = is_object($remito) ? ($remito->datosEmision ?? null) : null;

        return [
            'raw' => $ret,
            'cod_remito' => (string) ($remito->codRemito ?? $ret->codRemito ?? ''),
            'id_req' => (int) ($ret->idReq ?? 0),
            'nro_remito' => (string) ($emision->nroRemito ?? $ret->codRemito ?? ''),
            'estado' => (string) ($remito->estado ?? $ret->estado ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function consultarRemito(int $empresaId, int $codRemito, ?int $cuitOverride = null): array
    {
        $this->assertHabilitado();
        $cuit = $this->cuitRepresentada($empresaId, $cuitOverride);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsremcarne.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $req = [
            'authRequest' => [
                'token' => $ts['token'],
                'sign' => $ts['sign'],
                'cuitRepresentada' => $cuit,
            ],
            'codRemito' => $codRemito,
        ];

        try {
            $raw = $client->consultarRemito($req);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarRemito', $e, $client));
        }

        $ret = $raw->consultarRemitoReturn ?? $raw;

        return ['raw' => $ret];
    }

    /**
     * @return list<array{codigo: int|string, descripcion: string}>
     */
    public function consultarGruposCarne(int $empresaId, ?int $cuitOverride = null): array
    {
        return $this->consultarCatalogo($empresaId, 'consultarGruposCarne', 'consultarGruposCarneReturn', $cuitOverride);
    }

    /**
     * @return list<array{codigo: int|string, descripcion: string}>
     */
    public function consultarTiposCarne(int $empresaId, ?int $cuitOverride = null): array
    {
        return $this->consultarCatalogo($empresaId, 'consultarTiposCarne', 'consultarTiposCarneReturn', $cuitOverride);
    }

    /**
     * @return list<array{codigo: int|string, descripcion: string}>
     */
    private function consultarCatalogo(int $empresaId, string $method, string $returnProp, ?int $cuitOverride): array
    {
        $this->assertHabilitado();
        $cuit = $this->cuitRepresentada($empresaId, $cuitOverride);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsremcarne.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $req = [
            'authRequest' => [
                'token' => $ts['token'],
                'sign' => $ts['sign'],
                'cuitRepresentada' => $cuit,
            ],
        ];

        try {
            $raw = $client->{$method}($req);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault($method, $e, $client));
        }

        $ret = $raw->{$returnProp} ?? $raw;
        $items = $ret->arrayCodigoDescripcion->codigoDescripcion
            ?? $ret->arrayCodigosDescripciones->codigoDescripcion
            ?? $ret->codigoDescripcion
            ?? [];

        if (is_object($items)) {
            $items = [$items];
        }

        $out = [];
        foreach ((array) $items as $item) {
            if (! is_object($item) && ! is_array($item)) {
                continue;
            }
            $item = (object) $item;
            $out[] = [
                'codigo' => $item->codigo ?? '',
                'descripcion' => (string) ($item->descripcion ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarRemitoReturn(object $ret): array
    {
        $resultado = (string) ($ret->resultado ?? '');
        if ($resultado !== '' && strtoupper($resultado[0]) !== 'A') {
            $msg = $this->formatErrList($this->normalizeErrCollection($ret->arrayErrores->codigoDescripcion ?? null));
            if ($msg === '') {
                $msg = 'resultado='.$resultado.' estado='.(string) ($ret->estado ?? '');
            }
            throw new Exception('wsremcarne generarRemito rechazado: '.$msg);
        }

        return [
            'cod_remito' => (string) ($ret->codRemito ?? ''),
            'tipo_comprobante' => (int) ($ret->tipoComprobante ?? 995),
            'punto_emision' => (int) ($ret->puntoEmision ?? 0),
            'estado' => (string) ($ret->estado ?? ''),
            'resultado' => $resultado,
            'cod_autorizacion' => (string) ($ret->codAutorizacion ?? ''),
            'fecha_emision' => (string) ($ret->fechaEmision ?? ''),
            'fecha_vencimiento' => (string) ($ret->fechaVencimiento ?? ''),
            'qr' => $ret->qr ?? null,
            'observaciones' => $this->normalizeErrCollection($ret->arrayObservaciones->codigoDescripcion ?? null),
            'errores' => $this->normalizeErrCollection($ret->arrayErrores->codigoDescripcion ?? null),
        ];
    }

    private function assertSinErrores(object $ret, string $op): void
    {
        $errs = $this->normalizeErrCollection($ret->arrayErrores->codigoDescripcion ?? null);
        if ($errs !== []) {
            throw new Exception("wsremcarne {$op}: ".$this->formatErrList($errs));
        }
    }

    /**
     * @return list<array{codigo: string, descripcion: string}>
     */
    private function normalizeErrCollection(mixed $errs): array
    {
        if ($errs === null || $errs === '') {
            return [];
        }
        if (is_object($errs)) {
            $errs = [$errs];
        }
        if (! is_array($errs)) {
            return [];
        }
        $out = [];
        foreach ($errs as $e) {
            if (is_object($e) || is_array($e)) {
                $e = (object) $e;
                $out[] = [
                    'codigo' => (string) ($e->codigo ?? ''),
                    'descripcion' => (string) ($e->descripcion ?? ''),
                ];
            }
        }

        return $out;
    }

    /** @param list<array{codigo: string, descripcion: string}> $errs */
    private function formatErrList(array $errs): string
    {
        return implode('; ', array_map(
            fn ($e) => trim(($e['codigo'] ?? '').' '.($e['descripcion'] ?? '')),
            $errs
        ));
    }

    private function assertHabilitado(): void
    {
        if (! filter_var(config('arca_wsremcarne.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            throw new Exception('wsremcarne está deshabilitado (ARCA_WSREMCARNE_HABILITADO).');
        }
    }

    private function soapClient(): SoapClient
    {
        $env = (string) config('arca.env', 'prod');
        $key = $env === 'homo' ? 'homo' : 'prod';
        $wsdl = (string) config('arca_wsremcarne.wsdl_path');
        if ($wsdl === '' || ! is_file($wsdl)) {
            $wsdl = (string) config("arca_wsremcarne.wsremcarne.{$key}.wsdl_local");
        }
        if ($wsdl === '' || ! is_file($wsdl)) {
            throw new Exception('wsremcarne: WSDL local no encontrado. Copie wsremcarne.wsdl a storage/app/arca/wsremcarne/.');
        }
        $location = (string) config("arca_wsremcarne.wsremcarne.{$key}.url");
        $timeout = max(10, (int) config('arca_wsremcarne.soap_timeout', 60));

        return new SoapClient($wsdl, [
            'soap_version' => SOAP_1_1,
            'location' => $location,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'anitaERP-ARCA-WSREMCARNE',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'ciphers' => 'DEFAULT@SECLEVEL=1',
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                ],
            ]),
        ]);
    }

    /**
     * @return array{cert_path: string, private_key_path: string, private_key_passphrase: string, ta_storage_dir: string, cache_key: string, tmp_dir: string}
     */
    private function resolveWsaaContext(int $empresaId): array
    {
        $base = rtrim((string) config('arca_wsremcarne.base_storage'), '/');
        $emp = config("arca_wsremcarne.empresas.{$empresaId}");
        if (! is_array($emp) || empty($emp['carpeta_cert'])) {
            $entorno = (string) config('app.empresa', '');
            throw new Exception(
                "ARCA wsremcarne: la empresa {$empresaId} no está configurada para «{$entorno}». ".
                'Revise EMPRESA y config/arca_wsremcarne.php, o ARCA_WSREMCARNE_CARPETA_CERT. '.
                'Certs en storage/app/arca/wsremcarne/certs/{carpeta}/'
            );
        }
        $cdir = $base.'/certs/'.$emp['carpeta_cert'];

        return [
            'cert_path' => $cdir.'/cert.crt',
            'private_key_path' => $cdir.'/privada.key',
            'private_key_passphrase' => (string) ($emp['private_key_passphrase'] ?? ''),
            'ta_storage_dir' => (string) config('arca_wsremcarne.ta_storage_dir', $base.'/ta'),
            'cache_key' => 'remcarne_emp'.$empresaId,
            'tmp_dir' => (string) config('arca_wsremcarne.tmp_dir', $base.'/tmp'),
        ];
    }

    private function cuitRepresentada(int $empresaId, int|string|null $override): int
    {
        if ($override !== null && trim((string) $override) !== '') {
            $d = preg_replace('/\D+/', '', (string) $override) ?? '';
            if (strlen($d) === 11) {
                return (int) $d;
            }
        }

        $cfg = preg_replace('/\D+/', '', (string) config('arca_wsremcarne.cuit_titular_default', '')) ?? '';
        if (strlen($cfg) === 11) {
            return (int) $cfg;
        }

        $row = \App\Models\Configuracion\Empresa::query()->find($empresaId);
        if ($row === null || $row->nroinscripcion === null || trim((string) $row->nroinscripcion) === '') {
            throw new Exception("wsremcarne: sin CUIT para empresa {$empresaId}.");
        }
        $d = preg_replace('/\D+/', '', (string) $row->nroinscripcion) ?? '';
        if (strlen($d) !== 11) {
            throw new Exception("wsremcarne: CUIT inválido en empresa {$empresaId}.");
        }

        return (int) $d;
    }

    private function formatSoapFault(string $op, SoapFault $e, ?SoapClient $client): string
    {
        $extra = '';
        if ($client !== null) {
            try {
                $resp = $client->__getLastResponse();
                if (is_string($resp) && $resp !== '') {
                    $extra = ' | resp='.mb_substr($resp, 0, 500);
                }
            } catch (\Throwable) {
            }
        }

        return "wsremcarne {$op} SOAP: [{$e->faultcode}] {$e->faultstring}{$extra}";
    }
}
