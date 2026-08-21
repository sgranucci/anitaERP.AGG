<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ArcaMtxcaComprobanteTotalesSupport;
use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * WSMTXCA (Factura con Detalle / Codificación de Productos) vía SOAP.
 * Certificados y TA bajo storage/app/arca/mtxca/ (independiente de WSFE y padrón).
 */
class ArcaMtxcaFacturaElectronicaService
{
    public function __construct(
        private WsaaService $wsaa,
        private CondicionivaRepositoryInterface $condicionivaRepository,
    ) {}

    /**
     * Catálogo AFIP de tipos de comprobante (consultarTiposComprobante).
     *
     * @return list<array{id: int, codigo: string, descripcion: string}>
     */
    public function consultarTiposComprobante(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->consultarTiposComprobante([
                'authRequest' => $this->authRequest($ts, $cuit),
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarTiposComprobante', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'consultarTiposComprobanteResponse');
        if ($result === null) {
            throw new Exception('MTXCA: consultarTiposComprobante sin resultado.');
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — consultarTiposComprobante: '.$this->formatCodigoDescripcionList($errs));
        }

        $items = [];
        $array = $result->arrayTiposComprobante ?? null;
        $nodes = is_object($array) ? ($array->codigoDescripcion ?? null) : null;
        if ($nodes === null && isset($result->codigoDescripcion)) {
            $nodes = $result->codigoDescripcion;
        }
        if ($nodes !== null) {
            foreach ($this->normalizeCodigoDescripcionLike($nodes) as $row) {
                $id = (int) ($row['code'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $items[] = [
                    'id' => $id,
                    'codigo' => str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                    'descripcion' => (string) ($row['msg'] ?? ''),
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $items;
    }

    /**
     * Puntos de venta habilitados en WSMTXCA (CAE o CAEA según $caea).
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
    public function consultarPuntosVenta(int $empresaId, bool $caea = false): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $operacion = $caea ? 'consultarPuntosVentaCAEA' : 'consultarPuntosVentaCAE';
        $responseProp = $caea ? 'consultarPuntosVentaCAEAResponse' : 'consultarPuntosVentaCAEResponse';
        $emisionTipo = $caea ? 'CAEA' : 'CAE';

        try {
            $raw = $client->{$operacion}([
                'authRequest' => $this->authRequest($ts, $cuit),
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault($operacion, $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, $responseProp);
        if ($result === null) {
            throw new Exception("MTXCA: {$operacion} sin resultado.");
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — '.$operacion.': '.$this->formatCodigoDescripcionList($errs));
        }

        $items = [];
        foreach ($this->normalizePuntosVentaMtxca($result->arrayPuntosVenta ?? null) as $row) {
            $nro = (int) ($row['numero'] ?? 0);
            if ($nro < 1) {
                continue;
            }
            $bloqueado = $this->valorBloqueadoMtxca($row['bloqueado'] ?? null);
            $fechaBaja = $this->normalizarFechaBajaMtxca($row['fecha_baja'] ?? null);
            $items[] = [
                'codigo' => Puntoventa::normalizarCodigoArca((string) $nro) ?? (string) $nro,
                'numero' => $nro,
                'descripcion' => $this->etiquetaPuntoVentaMtxca($nro, $emisionTipo, $bloqueado, $fechaBaja),
                'emision_tipo' => $emisionTipo,
                'bloqueado' => $bloqueado,
                'fecha_baja' => $fechaBaja,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['numero'] <=> $b['numero']);

        return $items;
    }

    public function consultarUltimoComprobanteAutorizado(int $empresaId, int $ptoVta, int $cbteTipo, ?int $soapTimeoutSeconds = null): int
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        try {
            $raw = $client->consultarUltimoComprobanteAutorizado([
                'authRequest' => $this->authRequest($ts, $cuit),
                'consultaUltimoComprobanteAutorizadoRequest' => [
                    'codigoTipoComprobante' => $cbteTipo,
                    'numeroPuntoVenta' => $ptoVta,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarUltimoComprobanteAutorizado', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'consultarUltimoComprobanteAutorizadoResponse');
        if ($result === null) {
            throw new Exception('MTXCA: consultarUltimoComprobanteAutorizado sin resultado.');
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — consultarUltimoComprobanteAutorizado: '.$this->formatCodigoDescripcionList($errs));
        }

        return (int) ($result->numeroComprobante ?? 0);
    }

    /**
     * @return array{cae: string, fechavencimientocae: string}|int -1 si no autorizado
     */
    public function consultaComprobanteEmitido(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): array|int
    {
        $this->assertTransporteSoap();
        try {
            $result = $this->consultarComprobante($empresaId, $ptoVta, $cbteTipo, $numero);
        } catch (Exception $e) {
            return -1;
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            return -1;
        }

        $comp = $result->comprobante ?? null;
        if ($comp === null) {
            return -1;
        }

        $cae = trim((string) ($comp->codigoAutorizacion ?? ''));
        $vto = (string) ($comp->fechaVencimiento ?? '');
        if ($cae === '' || $vto === '') {
            return -1;
        }

        return [
            'cae' => $cae,
            'fechavencimientocae' => $this->fechaArcaToYmd($vto),
        ];
    }

    public function consultarComprobante(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): object
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->consultarComprobante([
                'authRequest' => $this->authRequest($ts, $cuit),
                'consultaComprobanteRequest' => [
                    'codigoTipoComprobante' => $cbteTipo,
                    'numeroPuntoVenta' => $ptoVta,
                    'numeroComprobante' => $numero,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarComprobante', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'consultarComprobanteResponse');
        if ($result === null) {
            throw new Exception('MTXCA: consultarComprobante sin resultado.');
        }

        return $result;
    }

    /**
     * Autorización CAE (autorizarComprobante).
     *
     * @param  object  $puntoventa  Puntoventa (codigo, webservice, actividad_arca_id)
     * @return array{cae: string, fechavencimientocae: string, resultado: string, observaciones: string}
     */
    public function solicitaCaeDomestico(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        array $datos,
        ?int $soapTimeoutSeconds = null,
    ): array {
        $this->assertTransporteSoap();
        if (($puntoventa->webservice ?? '') !== 'wsmtxca'
            && ! \App\Support\Ventas\ArcaPuntoventaWebserviceSupport::esMtxca((string) ($puntoventa->webservice ?? ''))) {
            throw new Exception('ARCA MTXCA: solo aplica a webservice wsmtxca.');
        }
        $puntoventa = \App\Support\Ventas\ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);

        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        $ptoVta = (int) $puntoventa->codigo;
        $comprobante = $this->buildComprobanteRequest($empresaId, $puntoventa, $cbteTipo, $ptoVta, $datos, null);

        try {
            $raw = $client->autorizarComprobante([
                'authRequest' => $this->authRequest($ts, $cuit),
                'comprobanteCAERequest' => $comprobante,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('autorizarComprobante', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'autorizarComprobanteResponse');

        try {
            return $this->parseAutorizacionResponse(
                $result,
                'autorizarComprobante',
                $empresaId,
                $cuit,
                $ptoVta,
                $cbteTipo,
                (int) $datos['numerocomprobante'],
                (float) $datos['total'],
                $client,
                $ctx,
            );
        } catch (Exception $e) {
            $this->logRechazoComprobante('autorizarComprobante', $comprobante, $e);

            throw $e;
        }
    }

    /**
     * ARCA rechaza citando el campo pero no los importes enviados: sin el request en el log
     * cada rechazo obliga a reconstruir el comprobante a mano.
     *
     * @param  array<string, mixed>  $comprobante
     */
    private function logRechazoComprobante(string $operacion, array $comprobante, Exception $e): void
    {
        Log::error('MTXCA '.$operacion.' rechazado: '.$e->getMessage(), [
            'comprobante' => $comprobante,
        ]);
    }

    /**
     * Informa comprobante emitido bajo CAEA (informarComprobanteCAEA).
     *
     * @param  object  $puntoventa
     * @param  array{caea: string, fechavencimiento: string}  $caeaVigente  fechavencimiento en Ymd o Y-m-d
     */
    public function informarComprobanteCaeaDomestico(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        array $datos,
        array $caeaVigente,
    ): array {
        $this->assertTransporteSoap();
        if (($puntoventa->webservice ?? '') !== 'wsmtxca'
            && ! \App\Support\Ventas\ArcaPuntoventaWebserviceSupport::esMtxca((string) ($puntoventa->webservice ?? ''))) {
            throw new Exception('ARCA MTXCA: solo aplica a webservice wsmtxca.');
        }
        $puntoventa = \App\Support\Ventas\ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);

        $caeaNum = preg_replace('/\D+/', '', (string) ($caeaVigente['caea'] ?? '')) ?? '';
        if ($caeaNum === '') {
            throw new Exception('MTXCA — informarComprobanteCAEA: CAEA vacío.');
        }

        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        $ptoVta = (int) $puntoventa->codigo;
        $vtoCaea = $this->formatFechaSalida($caeaVigente['fechavencimiento'] ?? $caeaVigente['fechavencimientocae'] ?? '');
        $comprobante = $this->buildComprobanteRequest($empresaId, $puntoventa, $cbteTipo, $ptoVta, $datos, [
            'codigoTipoAutorizacion' => 'A',
            'codigoAutorizacion' => (int) $caeaNum,
            'fechaVencimiento' => $vtoCaea,
        ]);

        try {
            $raw = $client->informarComprobanteCAEA([
                'authRequest' => $this->authRequest($ts, $cuit),
                'comprobanteCAEARequest' => $comprobante,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('informarComprobanteCAEA', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'informarComprobanteCAEAResponse');
        try {
            $parsed = $this->parseAutorizacionResponse(
                $result,
                'informarComprobanteCAEA',
                $empresaId,
                $cuit,
                $ptoVta,
                $cbteTipo,
                (int) $datos['numerocomprobante'],
                (float) $datos['total'],
                $client,
                $ctx,
                false,
            );
        } catch (Exception $e) {
            $this->logRechazoComprobante('informarComprobanteCAEA', $comprobante, $e);

            throw $e;
        }

        return [
            'cae' => $caeaNum,
            'fechavencimientocae' => $this->fechaArcaToYmd($vtoCaea),
            'resultado' => $parsed['resultado'],
            'observaciones' => $parsed['observaciones'],
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
    public function solicitarCaea(int $empresaId, int $periodo, int $orden): array
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->solicitarCAEA([
                'authRequest' => $this->authRequest($ts, $cuit),
                'solicitudCAEA' => [
                    'periodo' => $periodo,
                    'orden' => $orden,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('solicitarCAEA', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'solicitarCAEAResponse');

        return $this->parseCaeaResult($result, 'solicitarCAEA');
    }

    /**
     * Recupera CAEA otorgado por periodo/orden (WSMTXCA consultarCAEAEntreFechas).
     * consultarCAEA exige el número de CAEA; no aplica a pedido quincenal por periodo.
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
    public function consultarCaea(int $empresaId, int $periodo, int $orden): array
    {
        $fechas = CaeaQuincenaSupport::fechasQuincena($periodo, $orden);

        return $this->consultarCaeaEntreFechas(
            $empresaId,
            $fechas['desde']->format('Y-m-d'),
            $fechas['hasta']->format('Y-m-d'),
            $periodo,
            $orden,
        );
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
    private function consultarCaeaEntreFechas(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        int $periodo,
        int $orden,
    ): array {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->consultarCAEAEntreFechas([
                'authRequest' => $this->authRequest($ts, $cuit),
                'fechaDesde' => $fechaDesde,
                'fechaHasta' => $fechaHasta,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('consultarCAEAEntreFechas', $e, $client));
        }

        $result = $this->unwrapSoapResponse($raw, 'consultarCAEAEntreFechasResponse');

        return $this->parseCaeaEntreFechasResult($result, 'consultarCAEAEntreFechas', $periodo, $orden);
    }

    public function dummy(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $ctx = $this->resolveWsaaContext($empresaId);
        $this->wsaa->getTokenSign((string) config('arca_mtxca.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->dummy();
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('dummy', $e, $client));
        }

        $r = $raw->dummyResponse ?? null;
        if ($r === null) {
            throw new Exception('MTXCA: dummy sin resultado.');
        }

        return [
            'appserver' => (string) ($r->appserver ?? ''),
            'dbserver' => (string) ($r->dbserver ?? ''),
            'authserver' => (string) ($r->authserver ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $authCaea
     * @return array<string, mixed>
     */
    private function buildComprobanteRequest(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        int $ptoVta,
        array $datos,
        ?array $authCaea,
    ): array {
        $condIvaRec = null;
        if ((int) $datos['fechacomprobante'] >= 20250406) {
            $condicioniva = $this->condicionivaRepository->find($datos['condicioniva_id']);
            $condIvaRec = $condicioniva ? (int) $condicioniva->codigoexterno : 1;
        }

        $gravado = $this->money($datos['gravado']);
        $nogravado = $this->money($datos['nogravado']);
        $exento = $this->money($datos['exento']);
        $subtotal = round($gravado + $nogravado + $exento, 2);
        $concepto = isset($datos['concepto']) ? (int) $datos['concepto'] : 1;

        $req = [
            'codigoTipoComprobante' => $cbteTipo,
            'numeroPuntoVenta' => $ptoVta,
            'numeroComprobante' => (int) $datos['numerocomprobante'],
            'fechaEmision' => $this->formatFechaSalida($datos['fechacomprobante']),
            'codigoTipoDocumento' => (int) $datos['tipodoc'],
            'numeroDocumento' => (int) preg_replace('/\D+/', '', (string) $datos['numerodocumento']),
            'importeGravado' => $gravado,
            'importeNoGravado' => $nogravado,
            'importeExento' => $exento,
            'importeSubtotal' => $subtotal,
            'importeOtrosTributos' => $this->money($datos['tributo']),
            'importeTotal' => $this->money($datos['total']),
            'codigoMoneda' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) ($datos['moneda'] ?? 'PES')),
            'cotizacionMoneda' => $this->moneyCotiz($this->cotizacionParaMonedaAfip($datos)),
            'codigoConcepto' => $concepto,
        ];

        // AFIP 118: el flag solo se admite en facturas (1, 6, 51, 201, 206).
        // En NC/ND, aunque vaya en N, ARCA rechaza el comprobante.
        if (in_array($cbteTipo, [1, 6, 51, 201, 206], true)) {
            $req['cancelaEnMismaMonedaExtranjera'] = 'N';
        }

        if ($condIvaRec !== null) {
            $req['condicionIVAReceptor'] = $condIvaRec;
        }

        if ($authCaea !== null) {
            $req['codigoTipoAutorizacion'] = (string) $authCaea['codigoTipoAutorizacion'];
            $req['codigoAutorizacion'] = (int) $authCaea['codigoAutorizacion'];
            $req['fechaVencimiento'] = (string) $authCaea['fechaVencimiento'];
        }

        if ($concepto === 2 || $concepto === 3) {
            $req['fechaServicioDesde'] = $this->formatFechaSalida($datos['fecha_serv_desde'] ?? $datos['fechacomprobante']);
            $req['fechaServicioHasta'] = $this->formatFechaSalida($datos['fecha_serv_hasta'] ?? $datos['fechacomprobante']);
            $req['fechaVencimientoPago'] = $this->formatFechaSalida($datos['fechavencimiento'] ?? $datos['fechacomprobante']);
        }

        // FCE MiPyME (201/206/…): fechaVencimientoPago es obligatoria aunque el concepto sea Productos.
        if (in_array($cbteTipo, [201, 206, 211], true) && ! isset($req['fechaVencimientoPago'])) {
            $req['fechaVencimientoPago'] = $this->formatFechaSalida($datos['fechavencimiento'] ?? $datos['fechacomprobante']);
        }

        // RG 5782 / CAEA contingencia: fechaHoraGen obligatoria (equivalente WSFE CbteFchHsGen).
        if ($authCaea !== null) {
            $fechaHoraGen = $this->formatFechaHoraGen($datos);
            if ($fechaHoraGen !== null) {
                $req['fechaHoraGen'] = $fechaHoraGen;
            }
        }

        $tributos = $this->buildOtrosTributos($datos['tributos'] ?? []);
        if ($tributos !== null) {
            $req['arrayOtrosTributos'] = $tributos;
        }

        $items = $this->buildArrayItems($datos['items'] ?? [], $datos, $cbteTipo);
        if ($items !== null) {
            $req['arrayItems'] = $items;
        }

        $subtotalesIva = $this->buildSubtotalesIva($datos['impuestos'] ?? []);
        if ($subtotalesIva !== null) {
            $req['arraySubtotalesIVA'] = $subtotalesIva;
        }

        $asoc = $this->buildComprobantesAsociados($datos['comprobantesasociados'] ?? []);
        if ($asoc !== null) {
            $req['arrayComprobantesAsociados'] = $asoc;
        }

        $periodo = $this->buildPeriodoAsocIfApplies($cbteTipo, $datos);
        if ($periodo !== null) {
            $req['periodoComprobantesAsociados'] = $periodo;
        }

        $actividades = $this->buildActividades($puntoventa, $datos);
        if ($actividades !== null) {
            $req['arrayActividades'] = $actividades;
        }

        $datosAdic = $this->buildDatosAdicionales($datos['datos_adicionales'] ?? $datos['opcionales'] ?? []);
        if ($datosAdic !== null) {
            $req['arrayDatosAdicionales'] = $datosAdic;
        }

        $this->assertTotalesCoherentes($req);

        return $req;
    }

    /**
     * Corta antes del SOAP cuando el detalle no reproduce la cabecera: ARCA devuelve el
     * rechazo sin decir qué importe no cierra.
     *
     * @param  array<string, mixed>  $req
     */
    private function assertTotalesCoherentes(array $req): void
    {
        $errores = ArcaMtxcaComprobanteTotalesSupport::inconsistencias($req);
        if ($errores === []) {
            return;
        }

        throw new Exception(
            'MTXCA — el detalle de ítems no coincide con los totales del comprobante: '
            .implode(' | ', $errores)
        );
    }

    /**
     * @param  list<array{t?:int|string, codigo?:int|string, c1?:string, valor?:string}>  $lista
     * @return array{datoAdicional: list<array{t:int, c1:string}>}|null
     */
    private function buildDatosAdicionales(array $lista): ?array
    {
        $out = [];
        foreach ($lista as $row) {
            if (! is_array($row)) {
                continue;
            }
            $t = (int) ($row['t'] ?? $row['codigo'] ?? 0);
            $c1 = trim((string) ($row['c1'] ?? $row['valor'] ?? ''));
            if ($t <= 0 || $c1 === '') {
                continue;
            }
            $out[] = ['t' => $t, 'c1' => $c1];
        }

        return $out === [] ? null : ['datoAdicional' => $out];
    }

    /**
     * @param  list<mixed>  $lineas
     * @param  array<string, mixed>  $datos
     * @return array{item: list<array<string, mixed>>}|null
     */
    private function buildArrayItems(array $lineas, array $datos, int $cbteTipo = 1): ?array
    {
        // a-comprob.c ~7219: logística se agrega como ítem simulado solo al arma FACEL/MTXCA.
        $filas = $this->agregarItemLogisticaSimuladoBierzo(
            $this->filasItemsDesdeLineas($lineas),
            $datos,
        );
        $filas = ArcaMtxcaComprobanteTotalesSupport::conciliar($filas, $datos);
        if ($filas === []) {
            return null;
        }

        $claseB = $this->esComprobanteMtxcaClaseB($cbteTipo);

        return ['item' => array_map(fn (array $fila): array => $this->itemSoapDesdeFila($fila, $claseB), $filas)];
    }

    /**
     * El Bierzo (a-comprob.c FACEL_tmov "Logistica"): ítem simulado gravado 21 %.
     * No toca dataFactura / stock. AGG y demás: no-op.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    private function agregarItemLogisticaSimuladoBierzo(array $filas, array $datos): array
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return $filas;
        }

        $logistica = round((float) ($datos['logistica'] ?? 0), 2);
        if ($logistica < 0.01) {
            return $filas;
        }

        foreach ($filas as $fila) {
            if (($fila['descripcion'] ?? '') === 'Logistica') {
                return $filas;
            }
        }

        $codigo = ArcaMtxcaComprobanteTotalesSupport::codigoPorTasa(21.0) ?? 5;
        $alicuota = ArcaMtxcaComprobanteTotalesSupport::alicuotaPorCodigo($codigo) ?? 21.0;

        $filas[] = [
            'codigo' => 'texto',
            'descripcion' => 'Logistica',
            'cantidad' => 1.0,
            'codigo_unidad_medida' => 7,
            'precio_lista' => $logistica,
            'bonificacion' => 0.0,
            'codigo_condicion_iva' => $codigo,
            'alicuota' => $alicuota,
            'neto' => $logistica,
            'iva' => round($logistica * $alicuota / 100, 2),
            'codigo_mtx' => '7790000000000',
            'unidades_mtx' => 1,
        ];

        return $filas;
    }

    /**
     * Normaliza las líneas del comprobante a magnitudes numéricas (neto sin IVA e IVA por
     * alícuota real) para poder conciliarlas después contra los totales de cabecera.
     *
     * @param  list<mixed>  $lineas
     * @return list<array<string, mixed>>
     */
    private function filasItemsDesdeLineas(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        $articuloIds = [];
        $impuestoIds = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            if (! empty($linea['articulo_id'])) {
                $articuloIds[] = (int) $linea['articulo_id'];
            }
            if (! empty($linea['impuesto_id'])) {
                $impuestoIds[] = (int) $linea['impuesto_id'];
            }
        }

        $articulos = $articuloIds !== []
            ? Articulo::query()->whereIn('id', array_unique($articuloIds))->get()->keyBy('id')
            : collect();

        $impuestos = $impuestoIds !== []
            ? Impuesto::query()->whereIn('id', array_unique($impuestoIds))->get()->keyBy('id')
            : collect();

        $filas = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }

            $cantidad = (float) ($linea['cantidad'] ?? $linea['kilodescuento'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $impuesto = ! empty($linea['impuesto_id']) ? $impuestos->get((int) $linea['impuesto_id']) : null;
            $tasa = $impuesto ? (float) $impuesto->valor : (float) ($linea['tasa_iva'] ?? $linea['tasa'] ?? 0);
            $codigoCondicionIva = ArcaMtxcaComprobanteTotalesSupport::resolverCodigoCondicion(
                $impuesto?->codigoarca ?? $linea['codigo_condicion_iva'] ?? $linea['codigoCondicionIVA'] ?? null,
                $tasa,
            );
            $alicuota = ArcaMtxcaComprobanteTotalesSupport::alicuotaPorCodigo($codigoCondicionIva) ?? 0.0;

            $precioLista = $this->precioNetoUnitarioItem($linea, (float) ($linea['precio'] ?? 0), $alicuota);
            $neto = $this->importeNetoItem($linea, $cantidad, $precioLista);
            if ($neto <= 0) {
                continue;
            }

            $filas[] = [
                'codigo' => (string) ($linea['sku'] ?? ''),
                'descripcion' => mb_substr((string) ($linea['descripcion'] ?? 'Item'), 0, 250),
                'cantidad' => $cantidad,
                'codigo_unidad_medida' => (int) ($linea['codigounidadmedida'] ?? 7),
                'precio_lista' => $precioLista,
                'bonificacion' => max(0.0, round(round($cantidad * $precioLista, 2) - $neto, 2)),
                'codigo_condicion_iva' => $codigoCondicionIva,
                'alicuota' => $alicuota,
                'neto' => $neto,
                'iva' => $this->importeIvaDesdeNeto($linea, $neto, $alicuota, $codigoCondicionIva) ?? 0.0,
                'codigo_mtx' => $this->codigoMtxDeLinea($linea, $articulos),
                'unidades_mtx' => $this->unidadesMtxDeLinea($linea, $articulos),
            ];
        }

        return $filas;
    }

    /**
     * ARCA valida cada ítem sobre `base = cantidad × precioUnitario − importeBonificacion`:
     * clase A la toma sin IVA (importeIVA = base × alícuota, importeItem = base × (1 + alícuota))
     * y clase B con IVA incluido (importeItem = base, sin informar importeIVA).
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function itemSoapDesdeFila(array $fila, bool $claseB): array
    {
        $cantidad = (float) $fila['cantidad'];
        $neto = round((float) $fila['neto'], 2);
        $iva = round((float) $fila['iva'], 2);
        $alicuota = (float) $fila['alicuota'];
        $importeItem = round($neto + $iva, 2);

        $bonificacion = round((float) ($fila['bonificacion'] ?? 0), 2);
        if ($claseB && $alicuota > 0) {
            $bonificacion = round($bonificacion * (1 + $alicuota / 100), 2);
        }

        $base = $claseB ? $importeItem : $neto;
        $precioUnitario = $cantidad > 0 ? ($base + $bonificacion) / $cantidad : $base;
        if ($precioUnitario < 0 && $cantidad > 0) {
            $bonificacion = round($bonificacion + abs($precioUnitario) * $cantidad, 2);
            $precioUnitario = 0.0;
        }

        $item = [
            'codigo' => mb_substr((string) ($fila['codigo'] ?? ''), 0, 50),
            'descripcion' => (string) ($fila['descripcion'] ?? 'Item'),
            'cantidad' => $this->decimalCantidad($cantidad),
            'codigoUnidadMedida' => (int) ($fila['codigo_unidad_medida'] ?? 7),
            'precioUnitario' => $this->decimalCantidad($precioUnitario),
            'importeBonificacion' => $this->decimal($bonificacion),
            'codigoCondicionIVA' => (int) $fila['codigo_condicion_iva'],
            'importeItem' => $this->money($importeItem),
        ];

        $codigoMtx = trim((string) ($fila['codigo_mtx'] ?? ''));
        if ($codigoMtx !== '') {
            $item['codigoMtx'] = $codigoMtx;
            $item['unidadesMtx'] = max(1, (int) ($fila['unidades_mtx'] ?? 1));
        }

        if (! $claseB) {
            $item['importeIVA'] = $this->money(
                ArcaMtxcaComprobanteTotalesSupport::esCondicionGravada((int) $fila['codigo_condicion_iva'])
                    ? $iva
                    : 0.0
            );
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  \Illuminate\Support\Collection<int, Articulo>  $articulos
     */
    private function codigoMtxDeLinea(array $linea, $articulos): string
    {
        $codigoMtx = trim((string) ($linea['codigo_mtx'] ?? $linea['codigoMtx'] ?? ''));
        if ($codigoMtx !== '') {
            return $codigoMtx;
        }

        $articulo = isset($linea['articulo_id']) ? $articulos->get((int) $linea['articulo_id']) : null;
        if ($articulo === null) {
            return '';
        }

        $codigoMtx = trim((string) ($articulo->nomenclador ?? ''));

        return $codigoMtx !== '' ? $codigoMtx : trim((string) ($articulo->codigobarra ?? ''));
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  \Illuminate\Support\Collection<int, Articulo>  $articulos
     */
    private function unidadesMtxDeLinea(array $linea, $articulos): int
    {
        $articulo = isset($linea['articulo_id']) ? $articulos->get((int) $linea['articulo_id']) : null;

        $unidadesMtx = (int) ($linea['unidades_mtx'] ?? $linea['unidadesMtx'] ?? 0);
        if ($unidadesMtx <= 0 && $articulo !== null) {
            $unidadesMtx = (int) ($articulo->unidadreferenciacodigobarra ?? 0);
        }

        return max(1, $unidadesMtx > 0 ? $unidadesMtx : 1);
    }

    private function buildSubtotalesIva(array $lista): ?array
    {
        // Validación 402: no se admiten dos subtotales con el mismo código de alícuota.
        $porCodigo = [];
        foreach ($lista as $i) {
            if (! is_array($i) || (float) ($i['importe'] ?? 0) == 0.0) {
                continue;
            }
            $codigo = (int) ($i['id'] ?? 0);
            $porCodigo[$codigo] = ($porCodigo[$codigo] ?? 0) + (float) ($i['importe'] ?? 0);
        }

        $subs = [];
        foreach ($porCodigo as $codigo => $importe) {
            $subs[] = ['codigo' => $codigo, 'importe' => $this->money($importe)];
        }

        if ($subs === []) {
            return null;
        }

        return ['subtotalIVA' => count($subs) === 1 ? $subs[0] : $subs];
    }

    private function buildOtrosTributos(array $lista): ?array
    {
        $out = [];
        foreach ($lista as $t) {
            if (! is_array($t) || (float) ($t['importe'] ?? 0) == 0.0) {
                continue;
            }
            $out[] = [
                'codigo' => (int) ($t['id'] ?? 0),
                'descripcion' => (string) ($t['desc'] ?? ''),
                'baseImponible' => $this->money($t['base_imp'] ?? 0),
                'importe' => $this->money($t['importe'] ?? 0),
            ];
        }

        if ($out === []) {
            return null;
        }

        return ['otroTributo' => count($out) === 1 ? $out[0] : $out];
    }

    private function buildComprobantesAsociados(array $lista): ?array
    {
        $out = [];
        foreach ($lista as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'codigoTipoComprobante' => (int) ($row['tipo'] ?? 0),
                'numeroPuntoVenta' => (int) ($row['ptovta'] ?? 0),
                'numeroComprobante' => (int) ($row['nro'] ?? 0),
            ];
        }

        if ($out === []) {
            return null;
        }

        return ['comprobanteAsociado' => count($out) === 1 ? $out[0] : $out];
    }

    /**
     * @return array{fechaDesde: string, fechaHasta: string}|null
     */
    private function buildPeriodoAsocIfApplies(int $cbteTipo, array $datos): ?array
    {
        $desde = (int) ($datos['fechaasignaciondesde'] ?? 0);
        $asoc = $datos['comprobantesasociados'] ?? [];
        if ($desde <= 0 || count($asoc) > 0) {
            return null;
        }
        if (! in_array($cbteTipo, [2, 3, 7, 8, 202, 203, 207, 208, 53], true)) {
            return null;
        }

        return [
            'fechaDesde' => $this->formatFechaSalida($datos['fechaasignaciondesde']),
            'fechaHasta' => $this->formatFechaSalida($datos['fechaasignacionhasta']),
        ];
    }

    private function buildActividades(object $puntoventa, array $datos): ?array
    {
        $codigos = [];
        $actId = (int) ($datos['actividad_arca_id'] ?? $puntoventa->actividad_arca_id ?? 0);
        if ($actId > 0) {
            $act = Actividad_Arca::query()->find($actId);
            if ($act && trim((string) $act->codigoarca) !== '') {
                $codigos[] = (int) preg_replace('/\D+/', '', (string) $act->codigoarca);
            }
        }

        $codigos = array_values(array_filter(array_unique($codigos)));
        if ($codigos === []) {
            return null;
        }

        $acts = array_map(fn (int $c) => ['codigo' => $c], $codigos);

        return ['actividad' => count($acts) === 1 ? $acts[0] : $acts];
    }

    /**
     * @return array{cae: string, fechavencimientocae: string, resultado: string, observaciones: string}
     */
    private function parseAutorizacionResponse(
        ?object $result,
        string $operacion,
        int $empresaId,
        int $cuit,
        int $ptoVta,
        int $cbteTipo,
        int $cbteNro,
        float $impTotalEsperado,
        SoapClient $client,
        array $ctx,
        bool $verificarConsulta = true,
    ): array {
        if ($result === null) {
            throw new Exception("MTXCA: {$operacion} sin resultado.");
        }

        $res = (string) ($result->resultado ?? '');
        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — '.$operacion.': '.$this->formatCodigoDescripcionList($errs));
        }

        $obs = $this->normalizeCodigoDescripcionCollection($result->arrayObservaciones ?? null);
        $obsText = $obs !== [] ? $this->formatCodigoDescripcionList($obs) : '';

        if ($res !== 'A' && $res !== 'O') {
            $msg = 'Resultado: '.$res;
            if ($obsText !== '') {
                $msg .= ' | '.$obsText;
            }

            throw new Exception('MTXCA — comprobante no autorizado. '.$msg);
        }

        $comp = $result->comprobanteResponse ?? null;
        // informarComprobanteCAEA a veces responde solo resultado A/O (sin comprobanteResponse);
        // el CAEA ya se envió en el request y se confirma con consultarUltimoComprobanteAutorizado.
        if ($comp === null) {
            if (! $verificarConsulta) {
                return [
                    'cae' => '',
                    'fechavencimientocae' => '',
                    'resultado' => $res,
                    'observaciones' => $obsText,
                ];
            }

            throw new Exception("MTXCA — {$operacion} sin comprobanteResponse.");
        }

        $cae = trim((string) ($comp->CAE ?? $comp->codigoAutorizacion ?? ''));
        $caeVto = (string) ($comp->fechaVencimientoCAE ?? $comp->fechaVencimiento ?? '');

        if ($cae === '' || $caeVto === '') {
            throw new Exception('MTXCA — respuesta sin CAE o fecha de vencimiento (Resultado='.$res.').');
        }

        if ($verificarConsulta) {
            $this->verificarConConsulta($empresaId, $ptoVta, $cbteTipo, $cbteNro, $cae, $impTotalEsperado, $client, $ctx);
        }

        return [
            'cae' => $cae,
            'fechavencimientocae' => $this->fechaArcaToYmd($caeVto),
            'resultado' => $res,
            'observaciones' => $obsText,
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
    private function parseCaeaResult(?object $result, string $operacion): array
    {
        if ($result === null) {
            throw new Exception("MTXCA: {$operacion} sin resultado.");
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — '.$operacion.': '.$this->formatCodigoDescripcionList($errs));
        }

        $rg = $result->CAEAResponse ?? null;
        if ($rg === null) {
            throw new Exception("MTXCA: {$operacion} sin CAEAResponse.");
        }

        return $this->mapCaeaResponseObject($rg);
    }

    private function parseCaeaEntreFechasResult(?object $result, string $operacion, int $periodo, int $orden): array
    {
        if ($result === null) {
            throw new Exception("MTXCA: {$operacion} sin resultado.");
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception('MTXCA — '.$operacion.': '.$this->formatCodigoDescripcionList($errs));
        }

        $container = $result->arrayCAEAResponse ?? null;
        if ($container === null) {
            throw new Exception("MTXCA: {$operacion} sin arrayCAEAResponse.");
        }

        $rg = $this->resolveCaeaResponseForQuincena($container, $periodo, $orden);
        if ($rg === null) {
            throw new Exception("MTXCA — {$operacion} sin CAEA para periodo {$periodo} orden {$orden}.");
        }

        return $this->mapCaeaResponseObject($rg);
    }

    private function resolveCaeaResponseForQuincena(object $container, int $periodo, int $orden): ?object
    {
        $candidatos = $this->normalizeCaeaResponseCollection($container->CAEAResponse ?? null);
        if ($candidatos === []) {
            return null;
        }

        foreach ($candidatos as $rg) {
            if ((int) ($rg->periodo ?? 0) === $periodo && (int) ($rg->orden ?? 0) === $orden) {
                return $rg;
            }
        }

        return count($candidatos) === 1 ? $candidatos[0] : null;
    }

    /**
     * @return list<object>
     */
    private function normalizeCaeaResponseCollection(mixed $node): array
    {
        if ($node === null) {
            return [];
        }
        if (is_array($node)) {
            return array_values(array_filter($node, is_object(...)));
        }

        return is_object($node) ? [$node] : [];
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
    private function mapCaeaResponseObject(object $rg): array
    {
        $caea = trim((string) ($rg->CAEA ?? ''));
        if ($caea === '') {
            throw new Exception('MTXCA — respuesta CAEA sin número en la respuesta.');
        }

        $obs = $this->normalizeCodigoDescripcionCollection($rg->arrayObservaciones ?? null);

        return [
            'caea' => $caea,
            'periodo' => (int) ($rg->periodo ?? 0),
            'orden' => (int) ($rg->orden ?? 0),
            'fch_vig_desde' => (string) ($rg->fechaDesde ?? ''),
            'fch_vig_hasta' => (string) ($rg->fechaHasta ?? ''),
            'fch_tope_inf' => (string) ($rg->fechaTopeInforme ?? ''),
            'fch_proceso' => (string) ($rg->fechaProceso ?? ''),
            'observaciones' => $obs !== [] ? $this->formatCodigoDescripcionList($obs) : '',
            'tiene_observaciones' => $obs !== [],
        ];
    }

    private function verificarConConsulta(
        int $empresaId,
        int $ptoVta,
        int $cbteTipo,
        int $cbteNro,
        string $caeEsperado,
        float $impTotalEsperado,
        SoapClient $client,
        array $ctx,
    ): void {
        try {
            $result = $this->consultarComprobante($empresaId, $ptoVta, $cbteTipo, $cbteNro);
        } catch (Exception $e) {
            throw new Exception(
                'MTXCA — el CAE fue otorgado pero consultarComprobante falló: '.$e->getMessage().
                ' No se debe persistir el comprobante hasta resolver la inconsistencia.'
            );
        }

        $errs = $this->normalizeCodigoDescripcionCollection($result->arrayErrores ?? null);
        if ($errs !== []) {
            throw new Exception(
                'MTXCA — tras autorizar, consultarComprobante devolvió errores: '.$this->formatCodigoDescripcionList($errs).
                ' No persistir el comprobante.'
            );
        }

        $comp = $result->comprobante ?? null;
        if ($comp === null) {
            throw new Exception('MTXCA — consultarComprobante sin comprobante. No persistir.');
        }

        $caeOk = trim((string) ($comp->codigoAutorizacion ?? ''));
        if ($caeOk !== $caeEsperado) {
            throw new Exception(
                "MTXCA — CAE divergente tras consulta (emitido {$caeEsperado}, consulta {$caeOk}). No persistir."
            );
        }

        $impCons = (float) ($comp->importeTotal ?? 0);
        if (abs($impCons - $impTotalEsperado) > 0.02) {
            throw new Exception(
                'MTXCA — importe total divergente en consulta (enviado '.
                number_format($impTotalEsperado, 2, '.', '').
                ', ARCA '.number_format($impCons, 2, '.', '').
                '). No persistir.'
            );
        }
    }

    /**
     * @param  array{token: string, sign: string}  $ts
     * @return array{token: string, sign: string, cuitRepresentada: int}
     */
    private function authRequest(array $ts, int $cuit): array
    {
        return [
            'token' => $ts['token'],
            'sign' => $ts['sign'],
            'cuitRepresentada' => $cuit,
        ];
    }

    private function assertTransporteSoap(): void
    {
        if ((string) config('arca_mtxca.transporte', 'afip_php') !== 'soap') {
            throw new Exception(
                'ARCA MTXCA: el transporte SOAP solo está activo con arca_mtxca.transporte=soap (env ARCA_MTXCA_TRANSPORTE).'
            );
        }
    }

    private function soapClient(?int $timeoutOverrideSeconds = null): SoapClient
    {
        $env = (string) config('arca.env', 'homo');
        $cfg = config("arca_mtxca.mtxca.{$env}");
        if (! is_array($cfg)) {
            throw new Exception("ARCA MTXCA: configuración no encontrada para env={$env}.");
        }
        $wsdl = $this->resolveMtxcaWsdl($env, $cfg);
        $url = (string) ($cfg['url'] ?? '');
        if ($url === '') {
            throw new Exception("ARCA MTXCA: URL no configurada para env={$env}.");
        }
        $timeout = $timeoutOverrideSeconds !== null && $timeoutOverrideSeconds > 0
            ? max(5, $timeoutOverrideSeconds)
            : max(10, (int) config('arca_mtxca.soap_timeout', 60));

        return new SoapClient($wsdl, [
            'soap_version' => SOAP_1_2,
            'location' => $url,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => $this->soapStreamContext($timeout),
        ]);
    }

    private function resolveMtxcaWsdl(string $env, array $cfg): string
    {
        $override = env('ARCA_MTXCA_WSDL_LOCAL');
        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $configured = $cfg['wsdl_local'] ?? '';
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $remote = (string) ($cfg['wsdl'] ?? '');
        if ($remote === '') {
            throw new Exception("ARCA MTXCA: WSDL no configurado para env={$env}.");
        }

        $base = rtrim((string) config('arca_mtxca.base_storage'), '/');
        $localDefault = $base.'/wsdl/'.$env.'/MTXCAService.wsdl';
        if ($this->tryCacheWsdlFromRemote($remote, $localDefault)) {
            return $localDefault;
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
                'user_agent' => 'anitaERP-ARCA-MTXCA',
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

            $timeout = max(10, (int) config('arca_mtxca.soap_timeout', 60));
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($remoteUrl, false, $ctx);
            if (is_string($body) && $body !== '' && @file_put_contents($localPath, $body) !== false) {
                return is_readable($localPath);
            }

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
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function resolveWsaaContext(int $empresaId): array
    {
        $base = rtrim((string) config('arca_mtxca.base_storage'), '/');
        $emp = config("arca_mtxca.empresas.{$empresaId}");
        if (! is_array($emp) || empty($emp['carpeta_cert'])) {
            $entorno = (string) config('app.empresa', '');
            throw new Exception(
                "ARCA MTXCA: la empresa {$empresaId} no está configurada para el entorno «{$entorno}». ".
                'Revise EMPRESA en .env y config/arca_mtxca.php (empresas_por_entorno), '.
                'o defina ARCA_MTXCA_CARPETA_CERT / ARCA_MTXCA_EMPRESAS_JSON. '.
                'Copie cert.crt y privada.key en storage/app/arca/mtxca/certs/{carpeta}/'
            );
        }
        $cdir = $this->resolveDirectorioCertificados($empresaId, $base, (string) $emp['carpeta_cert']);

        return [
            'cert_path' => $cdir.'/cert.crt',
            'private_key_path' => $cdir.'/privada.key',
            'private_key_passphrase' => (string) ($emp['private_key_passphrase'] ?? ''),
            'ta_storage_dir' => $base.'/ta',
            'cache_key' => 'mtxca_emp'.$empresaId,
            'tmp_dir' => $base.'/tmp',
        ];
    }

    /**
     * Certificados bajo mtxca/certs/; si no existen, reutiliza wsfe/certs/ con la misma carpeta (mismo CUIT, distinto service WSAA).
     */
    private function resolveDirectorioCertificados(int $empresaId, string $baseMtxca, string $carpetaCert): string
    {
        $cdir = $baseMtxca.'/certs/'.$carpetaCert;
        if (is_readable($cdir.'/cert.crt') && is_readable($cdir.'/privada.key')) {
            return $cdir;
        }

        $wsfeEmp = config("arca_wsfe.empresas.{$empresaId}");
        $wsfeBase = rtrim((string) config('arca_wsfe.base_storage'), '/');
        if (
            is_array($wsfeEmp)
            && ($wsfeEmp['carpeta_cert'] ?? '') === $carpetaCert
            && $wsfeBase !== ''
        ) {
            $fallback = $wsfeBase.'/certs/'.$carpetaCert;
            if (is_readable($fallback.'/cert.crt') && is_readable($fallback.'/privada.key')) {
                return $fallback;
            }
        }

        return $cdir;
    }

    private function cuitEmisor(int $empresaId): int
    {
        $row = \App\Models\Configuracion\Empresa::query()->find($empresaId);
        if ($row === null || $row->nroinscripcion === null || trim((string) $row->nroinscripcion) === '') {
            throw new Exception(
                "ARCA MTXCA: la empresa {$empresaId} no tiene CUIT en empresa.nroinscripcion."
            );
        }
        $d = preg_replace('/\D+/', '', (string) $row->nroinscripcion) ?? '';
        if ($d === '' || strlen($d) !== 11) {
            throw new Exception("ARCA MTXCA: CUIT inválido en empresa.nroinscripcion para empresa {$empresaId}.");
        }

        return (int) $d;
    }

    private function formatFechaSalida(mixed $fecha): string
    {
        $s = preg_replace('/\D+/', '', (string) $fecha) ?? '';
        if (strlen($s) === 8) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }
        if (str_contains((string) $fecha, '-')) {
            return substr((string) $fecha, 0, 10);
        }

        return Carbon::parse($fecha)->format('Y-m-d');
    }

    /**
     * MTXCA dateTime AAAA-MM-DDTHH:MM:SS para CAEA por contingencia.
     *
     * @param  array<string, mixed>  $datos
     */
    private function formatFechaHoraGen(array $datos): ?string
    {
        $raw = trim((string) ($datos['fecha_hora_gen'] ?? $datos['cbte_fch_hs_gen'] ?? ''));
        if ($raw === '') {
            $fch = preg_replace('/\D+/', '', (string) ($datos['fechacomprobante'] ?? '')) ?? '';
            if (strlen($fch) !== 8) {
                return null;
            }

            return substr($fch, 0, 4).'-'.substr($fch, 4, 2).'-'.substr($fch, 6, 2).'T12:00:00';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) >= 14) {
            return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2)
                .'T'.substr($digits, 8, 2).':'.substr($digits, 10, 2).':'.substr($digits, 12, 2);
        }
        if (strlen($digits) === 8) {
            return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2).'T12:00:00';
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function fechaArcaToYmd(string $fecha): string
    {
        if (preg_match('/^\d{8}$/', $fecha)) {
            return $fecha;
        }

        return Carbon::parse($fecha)->format('Ymd');
    }

    /**
     * @return list<array{numero: int, bloqueado: mixed, fecha_baja: mixed}>
     */
    private function normalizePuntosVentaMtxca(mixed $arrayPuntosVenta): array
    {
        if ($arrayPuntosVenta === null) {
            return [];
        }

        $nodes = $arrayPuntosVenta->puntoVenta ?? null;
        if ($nodes === null && isset($arrayPuntosVenta->numeroPuntoVenta)) {
            $nodes = $arrayPuntosVenta;
        }
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
                'numero' => (int) ($node->numeroPuntoVenta ?? $node->NumeroPuntoVenta ?? 0),
                'bloqueado' => $node->bloqueado ?? $node->Bloqueado ?? null,
                'fecha_baja' => $node->fechaBaja ?? $node->FechaBaja ?? null,
            ];
        }

        return $out;
    }

    private function valorBloqueadoMtxca(mixed $bloqueado): bool
    {
        return strtoupper(trim((string) $bloqueado)) === 'S';
    }

    private function normalizarFechaBajaMtxca(mixed $fecha): ?string
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

    private function etiquetaPuntoVentaMtxca(int $nro, string $emision, bool $bloqueado, ?string $fechaBaja): string
    {
        $partes = [str_pad((string) $nro, 5, '0', STR_PAD_LEFT), $emision];
        if ($bloqueado) {
            $partes[] = 'bloqueado';
        }
        if ($fechaBaja !== null) {
            $partes[] = 'baja '.$fechaBaja;
        }

        return implode(' — ', $partes);
    }

    /**
     * @return list<array{code: string, msg: string}>
     */
    private function normalizeCodigoDescripcionCollection(mixed $container): array
    {
        if ($container === null) {
            return [];
        }
        $nodes = $container->codigoDescripcion ?? null;
        if ($nodes === null) {
            return [];
        }

        return $this->normalizeCodigoDescripcionLike($nodes);
    }

    /**
     * @return list<array{code: string, msg: string}>
     */
    private function normalizeCodigoDescripcionLike(mixed $node): array
    {
        $out = [];
        if (is_array($node)) {
            foreach ($node as $item) {
                foreach ($this->normalizeCodigoDescripcionLike($item) as $e) {
                    $out[] = $e;
                }
            }

            return $out;
        }
        if (! is_object($node)) {
            return [];
        }
        if (isset($node->codigo) || isset($node->descripcion)) {
            $out[] = [
                'code' => (string) ($node->codigo ?? ''),
                'msg' => (string) ($node->descripcion ?? ''),
            ];

            return $out;
        }

        foreach (get_object_vars($node) as $child) {
            foreach ($this->normalizeCodigoDescripcionLike($child) as $e) {
                $out[] = $e;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, msg: string}>  $list
     */
    private function formatCodigoDescripcionList(array $list): string
    {
        $parts = [];
        foreach ($list as $e) {
            $parts[] = '['.$e['code'].'] '.$e['msg'];
        }

        return implode(' | ', $parts);
    }

    /**
     * PHP SoapClient a veces expone el cuerpo en la raíz (arrayTiposComprobante, …)
     * y otras veces dentro de {operacion}Response.
     */
    private function unwrapSoapResponse(object $raw, string $responseProperty): ?object
    {
        if (isset($raw->{$responseProperty}) && is_object($raw->{$responseProperty})) {
            return $raw->{$responseProperty};
        }

        return $raw;
    }

    private function formatSoapFault(string $op, SoapFault $e, SoapClient $client): string
    {
        $base = $op.': '.$e->getMessage();
        if ($client->__getLastResponse()) {
            $base .= ' | Última respuesta (recorte): '.mb_substr($client->__getLastResponse(), 0, 500);
        }

        return $base;
    }

    /**
     * Clase B: precio con IVA; no informar importeIVA en el ítem.
     */
    private function esComprobanteMtxcaClaseB(int $cbteTipo): bool
    {
        return in_array($cbteTipo, [6, 7, 8, 206, 207, 208], true);
    }

    private function precioIncluyeIvaLinea(array $linea): bool
    {
        $incluye = (string) ($linea['incluyeimpuesto'] ?? '');

        return $incluye !== '' && $incluye !== 'N' && $incluye !== '2';
    }

    private function precioNetoUnitarioItem(array $linea, float $precioIngresado, float $tasa): float
    {
        $precio = round($precioIngresado, 2);
        if ($this->precioIncluyeIvaLinea($linea) && $tasa > 0) {
            return round($precio / (1 + $tasa / 100), 2);
        }

        return $precio;
    }

    private function importeNetoItem(array $linea, float $cantidad, float $precioNeto): float
    {
        if (isset($linea['totalcondescuento']) && $linea['totalcondescuento'] !== '' && $linea['totalcondescuento'] !== null) {
            return round((float) $linea['totalcondescuento'], 2);
        }

        return round($cantidad * $precioNeto, 2);
    }

    /**
     * WSMTXCA: importeIVA es obligatorio en cada ítem gravado al pedir CAE (validación 514).
     * `incluyeimpuesto` solo dice si el precio trae el IVA adentro; no decide si el campo viaja.
     */
    private function importeIvaDesdeNeto(array $linea, float $importeNeto, float $tasa, int $codigoCondicionIva): ?float
    {
        if (! ArcaMtxcaComprobanteTotalesSupport::esCondicionGravada($codigoCondicionIva)) {
            return null;
        }

        if (array_key_exists('importe_iva', $linea) && $linea['importe_iva'] !== null && $linea['importe_iva'] !== '') {
            return round((float) $linea['importe_iva'], 2);
        }

        if ($tasa > 0) {
            return round($importeNeto * $tasa / 100, 2);
        }

        return 0.0;
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
     * Con moneda PES la cotización AFIP debe ser 1 (equivalente WSFE [726]).
     *
     * @param  array<string, mixed>  $datos
     */
    private function cotizacionParaMonedaAfip(array $datos): float
    {
        $codigo = LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) ($datos['moneda'] ?? 'PES'));
        if ($codigo === 'PES') {
            return 1.0;
        }

        $cotizacion = (float) ($datos['cotizacion'] ?? 1);
        if ($cotizacion <= 0) {
            return 1.0;
        }

        return $cotizacion;
    }

    private function decimal(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /**
     * Cantidad y precio unitario admiten 6 decimales (DecimalSimpleType 18.6). Redondearlos
     * a 2 desplaza la base del ítem cuando la cantidad viene en kilos.
     */
    private function decimalCantidad(mixed $v): string
    {
        return number_format((float) $v, 6, '.', '');
    }
}
