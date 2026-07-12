<?php

namespace App\Services\Arca;

use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Exception;
use SoapClient;
use SoapFault;

/**
 * WSFEv1 (COMPG / RG 4291) vía SOAP, con certificados y TA bajo storage/app/arca/wsfe/
 * (independiente del padrón: config/arca.php → storage/app/arca/sr_padron/...).
 *
 * CUIT emisor hacia AFIP/ARCA: empresa.nroinscripcion (tabla empresa).
 * No usar ARCA_CUIT_REPRESENTADA (.env); esa variable es solo para el WS de padrón A5.
 */
class ArcaWsfeFacturaElectronicaService
{
    public function __construct(
        private WsaaService $wsaa,
        private CondicionivaRepositoryInterface $condicionivaRepository,
    ) {}

    /**
     * Último comprobante autorizado (equivalente a SolicitarUltimoCompEnviado para wsfev1).
     */
    public function feCompUltimoAutorizado(int $empresaId, int $ptoVta, int $cbteTipo, ?int $soapTimeoutSeconds = null): int
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        try {
            $raw = $client->FECompUltimoAutorizado([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'PtoVta' => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECompUltimoAutorizado', $e, $client));
        }

        $result = $raw->FECompUltimoAutorizadoResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE: FECompUltimoAutorizado sin resultado.');
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            throw new Exception('WSFE — FECompUltimoAutorizado: '.$this->formatErrList($errs));
        }

        return (int) ($result->CbteNro ?? 0);
    }

    /**
     * Catálogo AFIP de tipos de comprobante (FEParamGetTiposCbte).
     *
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    public function feParamGetTiposCbte(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FEParamGetTiposCbte([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEParamGetTiposCbte', $e, $client));
        }

        $result = $raw->FEParamGetTiposCbteResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE: FEParamGetTiposCbte sin resultado.');
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            throw new Exception('WSFE — FEParamGetTiposCbte: '.$this->formatErrList($errs));
        }

        $items = [];
        foreach ($this->normalizeCbteTipoCollection($result->ResultGet ?? null) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'codigo' => str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                'descripcion' => (string) ($row['descripcion'] ?? ''),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $items;
    }

    /**
     * Puntos de venta registrados en AFIP (FEParamGetPtosVenta).
     *
     * @return list<array{
     *     codigo: string,
     *     numero: int,
     *     descripcion: string,
     *     emision_tipo: string,
     *     bloqueado: bool,
     *     fecha_baja: ?string
     * }>
     */
    public function feParamGetPtosVenta(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FEParamGetPtosVenta([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEParamGetPtosVenta', $e, $client));
        }

        $result = $raw->FEParamGetPtosVentaResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE: FEParamGetPtosVenta sin resultado.');
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            throw new Exception('WSFE — FEParamGetPtosVenta: '.$this->formatErrList($errs));
        }

        $items = [];
        foreach ($this->normalizePtoVentaCollection($result->ResultGet ?? null) as $row) {
            $nro = (int) ($row['nro'] ?? 0);
            if ($nro < 1) {
                continue;
            }
            $emision = trim((string) ($row['emision_tipo'] ?? ''));
            $bloqueado = $this->valorBloqueado($row['bloqueado'] ?? null);
            $fechaBaja = $this->normalizarFechaBaja($row['fecha_baja'] ?? null);
            $items[] = [
                'codigo' => Puntoventa::normalizarCodigoArca((string) $nro) ?? (string) $nro,
                'numero' => $nro,
                'descripcion' => $this->etiquetaPuntoVentaWsfe($nro, $emision, $bloqueado, $fechaBaja),
                'emision_tipo' => $emision,
                'bloqueado' => $bloqueado,
                'fecha_baja' => $fechaBaja,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['numero'] <=> $b['numero']);

        return $items;
    }

    /**
     * Consulta un comprobante ya emitido (verificación post-FECAESolicitar).
     */
    public function feCompConsultar(int $empresaId, int $ptoVta, int $cbteTipo, int $cbteNro): object
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FECompConsultar([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCompConsReq' => [
                    'CbteTipo' => $cbteTipo,
                    'CbteNro' => $cbteNro,
                    'PtoVta' => $ptoVta,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECompConsultar', $e, $client));
        }

        return $raw->FECompConsultarResult ?? $raw;
    }

    /**
     * Autorización CAE para comprobante nacional (wsfev1), misma forma de datos que FacturaElectronicaService::solicitaCAE.
     *
     * @param  object  $puntoventa  modelo Puntoventa (codigo, webservice)
     * @return array{cae: string, fechavencimientocae: string, resultado: string, observaciones: string, emision_tipo: ?string}
     *
     * @throws Exception mensajes listos para mostrar al usuario
     */
    public function solicitaCaeDomestico(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        array $datos,
        ?int $soapTimeoutSeconds = null,
    ): array {
        $this->assertTransporteSoap();
        if (($puntoventa->webservice ?? '') !== 'wsfev1') {
            throw new Exception('ARCA WSFE: solo aplica a webservice wsfev1 (comprobantes nacionales).');
        }

        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        $ptoVta = (int) $puntoventa->codigo;
        $det = $this->buildFecaDetRequest($cbteTipo, $ptoVta, $datos);

        $feReq = [
            'FeCabReq' => [
                'CantReg' => 1,
                'PtoVta' => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ],
            'FeDetReq' => [
                'FECAEDetRequest' => $det,
            ],
        ];

        try {
            $raw = $client->FECAESolicitar([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCAEReq' => $feReq,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECAESolicitar', $e, $client));
        }

        $result = $raw->FECAESolicitarResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE: FECAESolicitar sin resultado.');
        }

        $cabErrs = $this->normalizeErrCollection($result->Errors ?? null);
        $feCab = $result->FeCabResp ?? null;
        $feDet = $result->FeDetResp ?? null;
        $detResp = $this->unwrapFecaDetResponse($feDet);

        $detErrs = $this->normalizeErrCollection($detResp->Errors ?? null);
        $obs = $this->normalizeObsCollection($detResp->Observaciones ?? null);

        $allErrs = array_merge($cabErrs, $detErrs);
        if ($allErrs !== []) {
            throw new Exception('WSFE — FECAESolicitar: '.$this->formatErrList($allErrs));
        }

        $resultado = (string) ($detResp->Resultado ?? '');
        $cae = trim((string) ($detResp->CAE ?? ''));
        $caeVto = (string) ($detResp->CAEFchVto ?? '');

        if ($resultado !== 'A' && $resultado !== 'P') {
            $msg = 'Resultado: '.$resultado;
            if ($obs !== []) {
                $msg .= ' | '.$this->formatObsList($obs);
            }

            throw new Exception('WSFE — comprobante no autorizado. '.$msg);
        }

        if ($cae === '' || $caeVto === '') {
            throw new Exception('WSFE — respuesta sin CAE o fecha de vencimiento (Resultado='.$resultado.').');
        }

        // Verificación explícita en ARCA (manual COMPG: FECompConsultar)
        $cbteNro = (int) $datos['numerocomprobante'];
        $this->verificarConConsulta(
            $empresaId,
            $cuit,
            $ptoVta,
            $cbteTipo,
            $cbteNro,
            $cae,
            (float) $datos['total'],
            $client,
            $ctx
        );

        $obsText = $obs !== [] ? $this->formatObsList($obs) : '';

        return [
            'cae' => $cae,
            'fechavencimientocae' => $caeVto,
            'resultado' => $resultado,
            'observaciones' => $obsText,
            'emision_tipo' => isset($detResp->EmisionTipo) ? (string) $detResp->EmisionTipo : null,
        ];
    }

    /**
     * Solicita CAEA para periodo/orden (WSFE FECAEASolicitar).
     *
     * @return array{
     *     caea: string,
     *     periodo: int,
     *     orden: int,
     *     fch_vig_desde: string,
     *     fch_vig_hasta: string,
     *     fch_tope_inf: string,
     *     fch_proceso: string,
     *     observaciones: string,
     *     tiene_observaciones: bool
     * }
     */
    public function feCaeaSolicitar(int $empresaId, int $periodo, int $orden): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FECAEASolicitar([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'Periodo' => $periodo,
                'Orden' => $orden,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECAEASolicitar', $e, $client));
        }

        return $this->parseFeCaeaResult($raw->FECAEASolicitarResult ?? null, 'FECAEASolicitar');
    }

    /**
     * Consulta CAEA otorgado (WSFE FECAEAConsultar).
     *
     * @return array{
     *     caea: string,
     *     periodo: int,
     *     orden: int,
     *     fch_vig_desde: string,
     *     fch_vig_hasta: string,
     *     fch_tope_inf: string,
     *     fch_proceso: string,
     *     observaciones: string,
     *     tiene_observaciones: bool
     * }
     */
    public function feCaeaConsultar(int $empresaId, int $periodo, int $orden): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FECAEAConsultar([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'Periodo' => $periodo,
                'Orden' => $orden,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECAEAConsultar', $e, $client));
        }

        return $this->parseFeCaeaResult($raw->FECAEAConsultarResult ?? null, 'FECAEAConsultar');
    }

    /**
     * Informa comprobante emitido bajo CAEA (WSFE FECAEARegInformativo / RG 4291).
     *
     * @param  object  $puntoventa
     * @param  array<string, mixed>  $datos
     * @param  array{caea: string, fechavencimientocae?: string, fechavencimiento?: string}  $caeaVigente
     * @return array{resultado: string, observaciones: string, caea: string}
     *
     * @throws Exception
     */
    public function feCaeaRegInformativoDomestico(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        array $datos,
        array $caeaVigente,
    ): array {
        $this->assertTransporteSoap();
        if (($puntoventa->webservice ?? '') !== 'wsfev1') {
            throw new Exception('ARCA WSFE: FECAEARegInformativo solo aplica a webservice wsfev1.');
        }

        $caeaNum = preg_replace('/\D+/', '', (string) ($caeaVigente['caea'] ?? '')) ?? '';
        if ($caeaNum === '') {
            throw new Exception('WSFE — FECAEARegInformativo: CAEA vacío.');
        }

        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $ptoVta = (int) $puntoventa->codigo;
        $det = $this->buildFeCaeaDetRequest($cbteTipo, $ptoVta, $datos, $caeaNum);

        $feReq = [
            'FeCabReq' => [
                'CantReg' => 1,
                'PtoVta' => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ],
            'FeDetReq' => [
                'FECAEADetRequest' => $det,
            ],
        ];

        try {
            $raw = $client->FECAEARegInformativo([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCAEARegInfReq' => $feReq,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FECAEARegInformativo', $e, $client));
        }

        $result = $raw->FECAEARegInformativoResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE: FECAEARegInformativo sin resultado.');
        }

        $cabErrs = $this->normalizeErrCollection($result->Errors ?? null);
        $feDet = $result->FeDetResp ?? null;
        $detResp = $this->unwrapFeCaeaDetResponse($feDet);

        $detErrs = $this->normalizeErrCollection($detResp->Errors ?? null);
        $obs = $this->normalizeObsCollection($detResp->Observaciones ?? null);

        $allErrs = array_merge($cabErrs, $detErrs);
        if ($allErrs !== []) {
            throw new Exception('WSFE — FECAEARegInformativo: '.$this->formatErrList($allErrs));
        }

        $resultado = (string) ($detResp->Resultado ?? '');
        if ($resultado !== 'A' && $resultado !== 'P') {
            $msg = 'Resultado: '.$resultado;
            if ($obs !== []) {
                $msg .= ' | '.$this->formatObsList($obs);
            }

            throw new Exception('WSFE — comprobante CAEA no informado. '.$msg);
        }

        return [
            'resultado' => $resultado,
            'observaciones' => $obs !== [] ? $this->formatObsList($obs) : '',
            'caea' => $caeaNum,
        ];
    }

    /**
     * @return array{
     *     caea: string,
     *     periodo: int,
     *     orden: int,
     *     fch_vig_desde: string,
     *     fch_vig_hasta: string,
     *     fch_tope_inf: string,
     *     fch_proceso: string,
     *     observaciones: string,
     *     tiene_observaciones: bool
     * }
     */
    private function parseFeCaeaResult(?object $result, string $operacion): array
    {
        if ($result === null) {
            throw new Exception("WSFE: {$operacion} sin resultado.");
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            throw new Exception('WSFE — '.$operacion.': '.$this->formatErrList($errs));
        }

        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            throw new Exception("WSFE: {$operacion} sin ResultGet.");
        }

        $caea = trim((string) ($rg->CAEA ?? ''));
        if ($caea === '') {
            throw new Exception("WSFE — {$operacion} sin CAEA en la respuesta.");
        }

        $obs = $this->normalizeObsCollection($rg->Observaciones ?? null);

        return [
            'caea' => $caea,
            'periodo' => (int) ($rg->Periodo ?? 0),
            'orden' => (int) ($rg->Orden ?? 0),
            'fch_vig_desde' => (string) ($rg->FchVigDesde ?? ''),
            'fch_vig_hasta' => (string) ($rg->FchVigHasta ?? ''),
            'fch_tope_inf' => (string) ($rg->FchTopeInf ?? ''),
            'fch_proceso' => (string) ($rg->FchProceso ?? ''),
            'observaciones' => $obs !== [] ? $this->formatObsList($obs) : '',
            'tiene_observaciones' => $obs !== [],
        ];
    }

    public function feDummy(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $ctx = $this->resolveWsaaContext($empresaId);
        $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FEDummy();
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEDummy', $e, $client));
        }

        $r = $raw->FEDummyResult ?? null;
        if ($r === null) {
            throw new Exception('WSFE: FEDummy sin resultado.');
        }

        return [
            'appserver' => (string) ($r->AppServer ?? ''),
            'dbserver' => (string) ($r->DbServer ?? ''),
            'authserver' => (string) ($r->AuthServer ?? ''),
        ];
    }

    private function assertTransporteSoap(): void
    {
        if ((string) config('arca_wsfe.transporte', 'afip_php') !== 'soap') {
            throw new Exception(
                'ARCA WSFE: el transporte SOAP solo está activo con arca_wsfe.transporte=soap (env ARCA_WSFE_TRANSPORTE).'
            );
        }
    }

    /**
     * Consulta si un comprobante figura autorizado en ARCA (mismo criterio que ConsultaCompEnviado vía XML).
     *
     * @return array{cae: string, fechavencimientocae: string}|int -1 si no hay match autorizado
     */
    public function consultaComprobanteEmitido(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): array|int
    {
        $this->assertTransporteSoap();
        try {
            $result = $this->feCompConsultar($empresaId, $ptoVta, $cbteTipo, $numero);
        } catch (Exception $e) {
            return -1;
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            return -1;
        }

        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            return -1;
        }

        $res = (string) ($rg->Resultado ?? '');
        if (! in_array($res, ['A', 'P'], true)) {
            return -1;
        }

        $cae = trim((string) ($rg->CodAutorizacion ?? ''));
        $vto = (string) ($rg->FchVto ?? '');
        if ($cae === '' || $vto === '') {
            return -1;
        }

        return ['cae' => $cae, 'fechavencimientocae' => $vto];
    }

    private function soapClient(?int $timeoutOverrideSeconds = null): SoapClient
    {
        $env = (string) config('arca.env', 'homo');
        $wsdl = $this->resolveWsfeWsdl($env);
        $timeout = $timeoutOverrideSeconds !== null && $timeoutOverrideSeconds > 0
            ? max(5, $timeoutOverrideSeconds)
            : max(10, (int) config('arca_wsfe.soap_timeout', 60));

        return new SoapClient($wsdl, [
            'soap_version' => SOAP_1_2,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => $this->soapStreamContext($timeout),
        ]);
    }

    /**
     * Contexto SSL para AFIP/ARCA (OpenSSL 3: sin SECLEVEL=1 falla con "dh key too small" / "Could not connect to host").
     */
    /**
     * @return resource
     */
    private function soapStreamContext(int $timeout)
    {
        return stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'anitaERP-ARCA-WSFE',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'ciphers' => 'DEFAULT@SECLEVEL=1',
            ],
        ]);
    }

    /**
     * WSDL local (storage o ARCA_WSFE_WSDL_LOCAL) evita descargar el XML en cada request
     * cuando el servidor no puede resolver o abrir la URL remota de AFIP.
     */
    private function resolveWsfeWsdl(string $env): string
    {
        $override = env('ARCA_WSFE_WSDL_LOCAL');
        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $configured = config("arca_wsfe.wsfe.{$env}.wsdl_local");
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $base = rtrim((string) config('arca_wsfe.base_storage'), '/');
        $localDefault = $base.'/wsdl/'.$env.'/service.wsdl';
        if (is_readable($localDefault)) {
            return $localDefault;
        }

        $remote = config("arca_wsfe.wsfe.{$env}.wsdl");
        if (! is_string($remote) || $remote === '') {
            throw new Exception("ARCA WSFE: WSDL no configurado para env={$env}.");
        }

        if ($this->tryCacheWsdlFromRemote($remote, $localDefault)) {
            return $localDefault;
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

            $timeout = max(10, (int) config('arca_wsfe.soap_timeout', 60));

            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
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

    /**
     * AFIP/ARCA a veces exige OpenSSL SECLEVEL=1 (error "dh key too small" con el default del sistema).
     */
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
     * @return array<string, string>
     */
    private function resolveWsaaContext(int $empresaId): array
    {
        $base = rtrim((string) config('arca_wsfe.base_storage'), '/');
        $emp = config("arca_wsfe.empresas.{$empresaId}");
        if (! is_array($emp) || empty($emp['carpeta_cert'])) {
            $entorno = (string) config('app.empresa', '');
            throw new Exception(
                "ARCA WSFE: la empresa {$empresaId} no está configurada para el entorno «{$entorno}». ".
                'Revise EMPRESA en .env y config/arca_wsfe.php (empresas_por_entorno), '.
                'o defina ARCA_WSFE_CARPETA_CERT / ARCA_WSFE_EMPRESAS_JSON. '.
                'Copie cert.crt y privada.key en storage/app/arca/wsfe/certs/{carpeta}/'
            );
        }
        $cdir = $base.'/certs/'.$emp['carpeta_cert'];

        return [
            'cert_path' => $cdir.'/cert.crt',
            'private_key_path' => $cdir.'/privada.key',
            'private_key_passphrase' => (string) ($emp['private_key_passphrase'] ?? ''),
            'ta_storage_dir' => $base.'/ta',
            'cache_key' => 'fe_emp'.$empresaId,
            'tmp_dir' => $base.'/tmp',
        ];
    }

    /**
     * CUIT del emisor en WSFEv1. Fuente única: tabla empresa (no ARCA_CUIT_REPRESENTADA del .env).
     */
    private function cuitEmisor(int $empresaId): int
    {
        $row = \App\Models\Configuracion\Empresa::query()->find($empresaId);
        if ($row === null || $row->nroinscripcion === null || trim((string) $row->nroinscripcion) === '') {
            throw new Exception(
                "ARCA WSFE: la empresa {$empresaId} no tiene CUIT en empresa.nroinscripcion ".
                '(no se usa ARCA_CUIT_REPRESENTADA; esa clave es solo para consulta de padrón).'
            );
        }
        $d = preg_replace('/\D+/', '', (string) $row->nroinscripcion) ?? '';
        if ($d === '' || strlen($d) !== 11) {
            throw new Exception("ARCA WSFE: CUIT inválido en empresa.nroinscripcion para empresa {$empresaId}.");
        }

        return (int) $d;
    }

    private function buildFecaDetRequest(int $cbteTipo, int $ptoVta, array $datos): array
    {
        $condIvaRec = null;
        if ((int) $datos['fechacomprobante'] >= 20250406) {
            $condicioniva = $this->condicionivaRepository->find($datos['condicioniva_id']);
            $condIvaRec = $condicioniva ? (int) $condicioniva->codigoexterno : 1;
        }

        $concepto = isset($datos['concepto']) ? (int) $datos['concepto'] : 1;

        $det = [
            'Concepto' => $concepto,
            'DocTipo' => (int) $datos['tipodoc'],
            'DocNro' => (int) preg_replace('/\D+/', '', (string) $datos['numerodocumento']),
            'CbteDesde' => (int) $datos['numerocomprobante'],
            'CbteHasta' => (int) $datos['numerocomprobante'],
            'CbteFch' => (string) $datos['fechacomprobante'],
            'ImpTotal' => $this->money($datos['total']),
            'ImpTotConc' => $this->money($datos['nogravado']),
            'ImpNeto' => $this->money($datos['gravado']),
            'ImpOpEx' => $this->money($datos['exento']),
            'ImpTrib' => $this->money($datos['tributo']),
            'ImpIVA' => $this->money($datos['iva']),
            'MonId' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) ($datos['moneda'] ?? 'PES')),
            'MonCotiz' => $this->moneyCotiz($this->cotizacionParaMonedaAfip($datos)),
        ];

        if ($concepto === 2 || $concepto === 3) {
            $det['FchServDesde'] = $datos['fecha_serv_desde'] ?? $datos['fechacomprobante'];
            $det['FchServHasta'] = $datos['fecha_serv_hasta'] ?? $datos['fechacomprobante'];
            $det['FchVtoPago'] = $datos['fechavencimiento'] ?? $datos['fechacomprobante'];
        }

        if ($condIvaRec !== null) {
            $det['CondicionIVAReceptorId'] = $condIvaRec;
        }

        $periodo = $this->buildPeriodoAsocIfApplies($cbteTipo, $datos);
        if ($periodo !== null) {
            $det['PeriodoAsoc'] = $periodo;
        }

        $cbtesAsoc = $this->buildCbtesAsoc($datos['comprobantesasociados'] ?? []);
        if ($cbtesAsoc !== null) {
            $det['CbtesAsoc'] = $cbtesAsoc;
        }

        $tributos = $this->buildTributos($datos['tributos'] ?? []);
        if ($tributos !== null) {
            $det['Tributos'] = $tributos;
        }

        $iva = $this->buildIva($datos['impuestos'] ?? []);
        if ($iva !== null) {
            $det['Iva'] = $iva;
        }

        return $det;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function buildFeCaeaDetRequest(int $cbteTipo, int $ptoVta, array $datos, string $caeaNum): array
    {
        $det = $this->buildFecaDetRequest($cbteTipo, $ptoVta, $datos);
        $det['CAEA'] = $caeaNum;

        $cbteFchHsGen = trim((string) ($datos['cbte_fch_hs_gen'] ?? ''));
        if ($cbteFchHsGen !== '') {
            $det['CbteFchHsGen'] = preg_replace('/\D+/', '', $cbteFchHsGen);
        }

        return $det;
    }

    /**
     * @return array{FchDesde: string, FchHasta: string}|null
     */
    private function buildPeriodoAsocIfApplies(int $cbteTipo, array $datos): ?array
    {
        $desde = (int) ($datos['fechaasignaciondesde'] ?? 0);
        $asoc = $datos['comprobantesasociados'] ?? [];
        if ($desde <= 0 || count($asoc) > 0) {
            return null;
        }
        if (! in_array($cbteTipo, [3, 8, 203, 53], true)) {
            return null;
        }

        return [
            'FchDesde' => (string) $datos['fechaasignaciondesde'],
            'FchHasta' => (string) $datos['fechaasignacionhasta'],
        ];
    }

    /**
     * Shape WSDL para tns:ArrayOfCbteAsoc (lista de structs CbteAsoc).
     *
     * El SoapClient nativo de PHP espera la forma canónica:
     *   ['CbteAsoc' => [ {Tipo,PtoVta,Nro}, {Tipo,PtoVta,Nro}, ... ]]
     * y NO la forma envuelta [['CbteAsoc' => {...}], ['CbteAsoc' => {...}]].
     * La forma envuelta hace que el encoder trate cada entrada como tns:CbteAsoc
     * directamente y aborte con "object has no 'Tipo' property".
     */
    private function buildCbtesAsoc(array $lista): ?array
    {
        $items = [];
        foreach ($lista as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'Tipo' => (int) ($row['tipo'] ?? 0),
                'PtoVta' => (int) ($row['ptovta'] ?? 0),
                'Nro' => (int) ($row['nro'] ?? 0),
            ];
        }

        return $items === [] ? null : ['CbteAsoc' => $items];
    }

    /**
     * Shape WSDL para tns:ArrayOfTributo: ['Tributo' => [ ...items ]].
     */
    private function buildTributos(array $lista): ?array
    {
        $items = [];
        foreach ($lista as $t) {
            if (! is_array($t) || (float) ($t['importe'] ?? 0) == 0.0) {
                continue;
            }
            $items[] = [
                'Id' => (int) ($t['id'] ?? 0),
                'Desc' => (string) ($t['desc'] ?? ''),
                'BaseImp' => $this->money($t['base_imp'] ?? 0),
                'Alic' => $this->money($t['alicuota'] ?? 0),
                'Importe' => $this->money($t['importe'] ?? 0),
            ];
        }

        return $items === [] ? null : ['Tributo' => $items];
    }

    /**
     * Shape WSDL para tns:ArrayOfAlicIva: ['AlicIva' => [ ...items ]].
     *
     * El error histórico "FECAESolicitar: SOAP-ERROR: Encoding: object has no 'Id'
     * property" venía de devolver [['AlicIva' => {...}], ['AlicIva' => {...}]]:
     * PHP iteraba la lista externa y encodeaba cada entrada como tns:AlicIva,
     * sin encontrar Id/BaseImp/Importe.
     */
    private function buildIva(array $lista): ?array
    {
        $items = [];
        foreach ($lista as $i) {
            if (! is_array($i) || (float) ($i['importe'] ?? 0) == 0.0) {
                continue;
            }
            $items[] = [
                'Id' => (int) ($i['id'] ?? 0),
                'BaseImp' => $this->money($i['base_imp'] ?? 0),
                'Importe' => $this->money($i['importe'] ?? 0),
            ];
        }

        return $items === [] ? null : ['AlicIva' => $items];
    }

    private function verificarConConsulta(
        int $empresaId,
        int $cuit,
        int $ptoVta,
        int $cbteTipo,
        int $cbteNro,
        string $caeEsperado,
        float $impTotalEsperado,
        SoapClient $client,
        array $ctx,
    ): void {
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfe.wsaa_service_id'), $ctx);

        try {
            $raw = $client->FECompConsultar([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'FeCompConsReq' => [
                    'CbteTipo' => $cbteTipo,
                    'CbteNro' => $cbteNro,
                    'PtoVta' => $ptoVta,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception(
                'WSFE — el CAE fue otorgado pero la verificación FECompConsultar falló: '.
                $this->formatSoapFault('FECompConsultar', $e, $client).
                ' No se debe persistir el comprobante hasta resolver la inconsistencia.'
            );
        }

        $result = $raw->FECompConsultarResult ?? null;
        if ($result === null) {
            throw new Exception('WSFE — FECompConsultar sin resultado tras autorizar. No persistir el comprobante.');
        }

        $errs = $this->normalizeErrCollection($result->Errors ?? null);
        if ($errs !== []) {
            throw new Exception(
                'WSFE — tras autorizar, FECompConsultar devolvió errores: '.$this->formatErrList($errs).
                ' No persistir el comprobante.'
            );
        }

        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            throw new Exception('WSFE — FECompConsultar sin ResultGet. No persistir el comprobante.');
        }

        $res = (string) ($rg->Resultado ?? '');
        if ($res !== 'A' && $res !== 'P') {
            throw new Exception('WSFE — consulta post-emisión con Resultado='.$res.'. No persistir el comprobante.');
        }

        $caeOk = trim((string) ($rg->CodAutorizacion ?? ''));
        if ($caeOk !== $caeEsperado) {
            throw new Exception(
                "WSFE — CAE divergente tras consulta (emitido {$caeEsperado}, consulta {$caeOk}). No persistir el comprobante."
            );
        }

        $impCons = (float) ($rg->ImpTotal ?? 0);
        if (abs($impCons - $impTotalEsperado) > 0.02) {
            throw new Exception(
                'WSFE — importe total divergente en consulta (enviado '.
                number_format($impTotalEsperado, 2, '.', '').
                ', ARCA '.number_format($impCons, 2, '.', '').
                '). No persistir el comprobante.'
            );
        }
    }

    /**
     * @return list<array{id: int, descripcion: string}>
     */
    private function normalizeCbteTipoCollection(mixed $resultGet): array
    {
        if ($resultGet === null) {
            return [];
        }

        $nodes = $resultGet->CbteTipo ?? null;
        if ($nodes === null) {
            return [];
        }

        $out = [];
        $list = is_array($nodes) ? $nodes : [$nodes];
        foreach ($list as $node) {
            if (! is_object($node)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($node->Id ?? 0),
                'descripcion' => trim((string) ($node->Desc ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{nro: int, emision_tipo: string, bloqueado: mixed, fecha_baja: mixed}>
     */
    private function normalizePtoVentaCollection(mixed $resultGet): array
    {
        if ($resultGet === null) {
            return [];
        }

        $nodes = $resultGet->PtoVenta ?? null;
        if ($nodes === null) {
            return [];
        }

        $out = [];
        $list = is_array($nodes) ? $nodes : [$nodes];
        foreach ($list as $node) {
            if (! is_object($node)) {
                continue;
            }
            $out[] = [
                'nro' => (int) ($node->Nro ?? 0),
                'emision_tipo' => trim((string) ($node->EmisionTipo ?? '')),
                'bloqueado' => $node->Bloqueado ?? null,
                'fecha_baja' => $node->FchBaja ?? null,
            ];
        }

        return $out;
    }

    private function valorBloqueado(mixed $bloqueado): bool
    {
        return strtoupper(trim((string) $bloqueado)) === 'S';
    }

    private function normalizarFechaBaja(mixed $fecha): ?string
    {
        $fch = trim((string) ($fecha ?? ''));
        if ($fch === '' || strtoupper($fch) === 'NULL') {
            return null;
        }
        $soloDigitos = preg_replace('/\D+/', '', $fch);
        if ($soloDigitos === '' || preg_match('/^0+$/', $soloDigitos)) {
            return null;
        }

        return $fch;
    }

    private function etiquetaPuntoVentaWsfe(int $nro, string $emision, bool $bloqueado, ?string $fechaBaja): string
    {
        $partes = [str_pad((string) $nro, 5, '0', STR_PAD_LEFT)];
        if ($emision !== '') {
            $partes[] = $emision;
        }
        if ($bloqueado) {
            $partes[] = 'bloqueado';
        }
        if ($fechaBaja !== null) {
            $partes[] = 'baja '.$fechaBaja;
        }

        return implode(' — ', $partes);
    }

    private function unwrapFecaDetResponse(?object $feDet): object
    {
        if ($feDet === null) {
            return (object) [];
        }
        $r = $feDet->FECAEDetResponse ?? null;
        if (is_array($r)) {
            return (object) ($r[0] ?? []);
        }
        if (is_object($r)) {
            return $r;
        }

        return (object) [];
    }

    private function unwrapFeCaeaDetResponse(?object $feDet): object
    {
        if ($feDet === null) {
            return (object) [];
        }
        $r = $feDet->FECAEADetResponse ?? null;
        if (is_array($r)) {
            return (object) ($r[0] ?? []);
        }
        if (is_object($r)) {
            return $r;
        }

        return (object) [];
    }

    /**
     * @return list<array{code: string, msg: string}>
     */
    private function normalizeErrCollection(mixed $errors): array
    {
        if ($errors === null) {
            return [];
        }
        $errs = $errors->Err ?? null;
        if ($errs === null) {
            return [];
        }

        return $this->normalizeErrLike($errs);
    }

    /**
     * @return list<array{code: string, msg: string}>
     */
    private function normalizeObsCollection(mixed $obs): array
    {
        if ($obs === null) {
            return [];
        }
        $o = $obs->Obs ?? null;
        if ($o === null) {
            return [];
        }

        return $this->normalizeErrLike($o);
    }

    /**
     * @return list<array{code: string, msg: string}>
     */
    private function normalizeErrLike(mixed $node): array
    {
        $out = [];
        if (is_array($node)) {
            foreach ($node as $item) {
                foreach ($this->normalizeErrLike($item) as $e) {
                    $out[] = $e;
                }
            }

            return $out;
        }
        if (! is_object($node)) {
            return [];
        }
        if (isset($node->Code) || isset($node->Msg)) {
            $out[] = [
                'code' => (string) ($node->Code ?? ''),
                'msg' => (string) ($node->Msg ?? ''),
            ];

            return $out;
        }

        foreach (get_object_vars($node) as $child) {
            foreach ($this->normalizeErrLike($child) as $e) {
                $out[] = $e;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, msg: string}>  $list
     */
    private function formatErrList(array $list): string
    {
        $parts = [];
        foreach ($list as $e) {
            $parts[] = '['.$e['code'].'] '.$e['msg'];
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  list<array{code: string, msg: string}>  $list
     */
    private function formatObsList(array $list): string
    {
        return $this->formatErrList($list);
    }

    private function formatSoapFault(string $op, SoapFault $e, SoapClient $client): string
    {
        $base = $op.': '.$e->getMessage();
        if ($client->__getLastResponse()) {
            $base .= ' | Última respuesta (recorte): '.mb_substr($client->__getLastResponse(), 0, 500);
        }

        return $base;
    }

    private function money(mixed $v): float
    {
        return round((float) $v, 2);
    }

    private function moneyCotiz(mixed $v): string
    {
        return number_format((float) $v, 6, '.', '');
    }

    /**
     * WSFE [726]: MonCotiz obligatorio e igual a 1 cuando MonId=PES.
     *
     * @param  array<string, mixed>  $datos
     */
    private function cotizacionParaMonedaAfip(array $datos): float
    {
        $monId = LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) ($datos['moneda'] ?? 'PES'));
        if ($monId === 'PES') {
            return 1.0;
        }

        $cotizacion = (float) ($datos['cotizacion'] ?? 1);
        if ($cotizacion <= 0) {
            return 1.0;
        }

        return $cotizacion;
    }
}
