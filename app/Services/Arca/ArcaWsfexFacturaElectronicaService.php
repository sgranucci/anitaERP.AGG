<?php

namespace App\Services\Arca;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Ventas\ArcaPuntoventaWebserviceSupport;
use App\Support\Ventas\ArcaUnidadMedidaAfipSupport;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * WSFEX v1 (RG 2758 / manual ARCA V3.1.1) vía SOAP.
 *
 * Certificados y TA bajo {arca_wsfex.base_storage}/certs|ta|wsdl|tmp
 * (independiente de WSFE y del padrón A5).
 *
 * CUIT emisor: empresa.nroinscripcion. WSAA service id: "wsfex".
 *
 * @see https://arca.gob.ar/ws/documentacion/manuales/WSFEX-Manualparaeldesarrollador_V3.1.1_ARCA.pdf
 */
class ArcaWsfexFacturaElectronicaService
{
    public function __construct(
        private WsaaService $wsaa,
    ) {}

    /**
     * Último comprobante autorizado (FEXGetLast_CMP).
     */
    public function fexGetLastCmp(int $empresaId, int $ptoVta, int $cbteTipo, ?int $soapTimeoutSeconds = null): int
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        try {
            // El cliente SOAP de WSFEX (y el módulo legacy) envían Pto_venta/Cbte_Tipo
            // dentro de Auth, no como ClsFEX_LastCMP separado.
            $raw = $client->FEXGetLast_CMP([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                    'Pto_venta' => $ptoVta,
                    'Cbte_Tipo' => $cbteTipo,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEXGetLast_CMP', $e, $client));
        }

        $result = $raw->FEXGetLast_CMPResult ?? null;
        if ($result === null) {
            throw new Exception('WSFEX: FEXGetLast_CMP sin resultado.');
        }

        $this->assertFexErr($result, 'FEXGetLast_CMP');

        return (int) ($result->FEXResult_LastCMP->Cbte_nro ?? $result->Cbte_nro ?? 0);
    }

    /**
     * Último Id de requerimiento recibido por ARCA (FEXGetLast_ID).
     * El próximo Id a enviar en FEXAuthorize es este valor + 1.
     */
    public function fexGetLastId(int $empresaId, ?int $soapTimeoutSeconds = null): int
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        try {
            $raw = $client->FEXGetLast_ID([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEXGetLast_ID', $e, $client));
        }

        $result = $raw->FEXGetLast_IDResult ?? null;
        if ($result === null) {
            throw new Exception('WSFEX: FEXGetLast_ID sin resultado.');
        }

        $this->assertFexErr($result, 'FEXGetLast_ID');

        return (int) ($result->FEXResultGet->Id ?? 0);
    }

    /**
     * Autorización CAE de exportación (FEXAuthorize) + verificación FEXGetCMP.
     *
     * @param  array<string, mixed>  $datos  mismo shape que arma FacturaelectronicaService / generaFacturaPorItemOt
     * @return array{cae: string, fechavencimientocae: string, resultado: string, observaciones: string, id_requerimiento: int}
     */
    public function solicitaCaeExportacion(
        int $empresaId,
        object $puntoventa,
        int $cbteTipo,
        array $datos,
        ?int $soapTimeoutSeconds = null,
    ): array {
        $this->assertTransporteSoap();
        if (! ArcaPuntoventaWebserviceSupport::esWsfex((string) ($puntoventa->webservice ?? ''))) {
            throw new Exception('ARCA WSFEX: solo aplica a webservice wsfex_v1 (comprobantes de exportación).');
        }

        $puntoventa = ArcaPuntoventaWebserviceSupport::puntoventaParaSoap($puntoventa);

        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        $idReq = $this->fexGetLastId($empresaId, $soapTimeoutSeconds) + 1;
        $cmp = $this->buildCmpRequest($cbteTipo, (int) $puntoventa->codigo, $datos, $idReq);

        try {
            $raw = $client->FEXAuthorize([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'Cmp' => $cmp,
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEXAuthorize', $e, $client));
        }

        $result = $raw->FEXAuthorizeResult ?? null;
        if ($result === null) {
            throw new Exception('WSFEX: FEXAuthorize sin resultado.');
        }

        $this->assertFexErr($result, 'FEXAuthorize');

        $auth = $result->FEXResultAuth ?? null;
        if ($auth === null) {
            throw new Exception('WSFEX — FEXAuthorize sin FEXResultAuth.');
        }

        $resultado = strtoupper(trim((string) ($auth->Resultado ?? '')));
        $cae = trim((string) ($auth->Cae ?? ''));
        $caeVto = (string) ($auth->Fch_venc_Cae ?? '');
        $obs = trim((string) ($auth->Motivos_Obs ?? ''));

        if ($resultado !== 'A' && $resultado !== 'P') {
            $msg = 'Resultado: '.$resultado;
            if ($obs !== '') {
                $msg .= ' | '.$obs;
            }
            throw new Exception('WSFEX — comprobante no autorizado. '.$msg);
        }

        if ($cae === '' || $caeVto === '') {
            throw new Exception('WSFEX — respuesta sin CAE o fecha de vencimiento (Resultado='.$resultado.').');
        }

        $cbteNro = (int) $datos['numerocomprobante'];
        $this->verificarConConsulta(
            $empresaId,
            $cuit,
            (int) $puntoventa->codigo,
            $cbteTipo,
            $cbteNro,
            $cae,
            (float) $datos['total'],
            $client,
            $ctx,
        );

        return [
            'cae' => $cae,
            'fechavencimientocae' => $this->normalizarFechaCae($caeVto),
            'resultado' => $resultado,
            'observaciones' => $obs,
            'id_requerimiento' => $idReq,
        ];
    }

    /**
     * Consulta comprobante emitido (FEXGetCMP).
     */
    public function fexGetCmp(int $empresaId, int $ptoVta, int $cbteTipo, int $cbteNro, ?int $soapTimeoutSeconds = null): object
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient($soapTimeoutSeconds);

        try {
            $raw = $client->FEXGetCMP([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
                'Cmp' => [
                    'Cbte_tipo' => $cbteTipo,
                    'Punto_vta' => $ptoVta,
                    'Cbte_nro' => $cbteNro,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEXGetCMP', $e, $client));
        }

        $result = $raw->FEXGetCMPResult ?? null;
        if ($result === null) {
            throw new Exception('WSFEX: FEXGetCMP sin resultado.');
        }

        $this->assertFexErr($result, 'FEXGetCMP');

        return $result;
    }

    /**
     * @return array{cae: string, fechavencimientocae: string}|int -1 si no hay match autorizado
     */
    public function consultaComprobanteEmitido(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): array|int
    {
        $this->assertTransporteSoap();
        try {
            $result = $this->fexGetCmp($empresaId, $ptoVta, $cbteTipo, $numero);
        } catch (Exception) {
            return -1;
        }

        $auth = $result->FEXResultGet ?? null;
        if ($auth === null) {
            return -1;
        }

        $res = strtoupper(trim((string) ($auth->Resultado ?? '')));
        if (! in_array($res, ['A', 'P'], true)) {
            return -1;
        }

        $cae = trim((string) ($auth->Cae ?? ''));
        $vto = (string) ($auth->Fch_venc_Cae ?? '');
        if ($cae === '' || $vto === '') {
            return -1;
        }

        return [
            'cae' => $cae,
            'fechavencimientocae' => $this->normalizarFechaCae($vto),
        ];
    }

    /**
     * @return array{appserver: string, dbserver: string, authserver: string}
     */
    public function fexDummy(int $empresaId): array
    {
        $this->assertTransporteSoap();
        $ctx = $this->resolveWsaaContext($empresaId);
        $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->FEXDummy();
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault('FEXDummy', $e, $client));
        }

        $r = $raw->FEXDummyResult ?? $raw ?? null;
        if ($r === null) {
            throw new Exception('WSFEX: FEXDummy sin resultado.');
        }

        return [
            'appserver' => (string) ($r->AppServer ?? ''),
            'dbserver' => (string) ($r->DbServer ?? ''),
            'authserver' => (string) ($r->AuthServer ?? ''),
        ];
    }

    /**
     * Catálogo de Incoterms (FEXGetPARAM_Incoterms).
     *
     * @return list<array{id: string, descripcion: string}>
     */
    public function fexParamIncoterms(int $empresaId): array
    {
        $result = $this->callParam($empresaId, 'FEXGetPARAM_Incoterms');
        $nodes = $result->FEXResultGet->ClsFEXResponse_Inc ?? $result->FEXResultGet ?? null;

        return $this->normalizeParamPairs($nodes, 'Id_incoterms', 'Id', 'Ds_incoterms', 'Desc');
    }

    private function assertTransporteSoap(): void
    {
        if ((string) config('arca_wsfex.transporte', 'afip_php') !== 'soap') {
            throw new Exception(
                'ARCA WSFEX: el transporte SOAP solo está activo con arca_wsfex.transporte=soap (env ARCA_WSFEX_TRANSPORTE).'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function buildCmpRequest(int $cbteTipo, int $ptoVta, array $datos, int $idReq): array
    {
        $tipoExpo = (int) ($datos['tipo_expo'] ?? 1);
        if ($tipoExpo < 1) {
            $tipoExpo = 1;
        }

        $monedaId = LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
            (string) ($datos['moneda'] ?? 'PES'),
            (string) ($datos['moneda_nombre'] ?? $datos['nombremoneda'] ?? '')
        );
        $cotizacion = $this->moneyCotiz((float) ($datos['cotizacion'] ?? 1));
        // ARCA WSFEX [1601]: con Moneda_Id PES la cotización debe ser exactamente 1.
        if (strtoupper(trim($monedaId)) === 'PES') {
            $cotizacion = 1.0;
        }

        $permisoExistente = (string) ($datos['permiso_existente'] ?? '');
        if ($permisoExistente === '' && $cbteTipo === 19 && $tipoExpo === 1) {
            $permisoExistente = 'N';
        }
        if (in_array($cbteTipo, [20, 21], true)) {
            $permisoExistente = '';
        }
        if (in_array($tipoExpo, [2, 4], true)) {
            $permisoExistente = '';
        }

        $incoterms = trim((string) ($datos['incoterms'] ?? ''));
        $incotermsDs = trim((string) ($datos['incoterms_ds'] ?? ''));
        if ($incoterms !== '' && $incotermsDs === '') {
            $incotermsDs = ' ';
        }

        $cuitPais = preg_replace('/\D+/', '', (string) (
            $datos['nroinscripcion']
            ?? $datos['cuit_pais_cliente']
            ?? $datos['numerodocumento']
            ?? ''
        )) ?? '';

        $cmp = [
            'Id' => $idReq,
            'Fecha_cbte' => (string) $datos['fechacomprobante'],
            'Cbte_Tipo' => $cbteTipo,
            'Punto_vta' => $ptoVta,
            'Cbte_nro' => (int) $datos['numerocomprobante'],
            'Tipo_expo' => $tipoExpo,
            'Permiso_existente' => $permisoExistente,
            'Dst_cmp' => (int) ($datos['pais'] ?? $datos['dst_cmp'] ?? 0),
            'Cliente' => $this->sanearTexto((string) ($datos['nombrecliente'] ?? ''), 200),
            'Cuit_pais_cliente' => (float) $cuitPais,
            'Domicilio_cliente' => $this->sanearTexto((string) ($datos['domicilio'] ?? ''), 300),
            'Id_impositivo' => trim((string) ($datos['id_impositivo'] ?? '')) !== ''
                ? (string) $datos['id_impositivo']
                : ' ',
            'Moneda_Id' => $monedaId,
            'Moneda_ctz' => $cotizacion,
            'Obs_comerciales' => (string) ($datos['obs_comerciales'] ?? ' '),
            'Imp_total' => $this->money($datos['total'] ?? 0),
            'Obs' => (string) ($datos['obs'] ?? ' '),
            'Forma_pago' => (string) ($datos['formapagoexportacion'] ?? $datos['forma_pago'] ?? ''),
            'Incoterms' => $incoterms,
            'Incoterms_Ds' => $incotermsDs,
            'Idioma_cbte' => (int) ($datos['idioma_cbte'] ?? 2),
            'Items' => [
                'Item' => $this->buildItems($datos),
            ],
        ];

        // CanMisMonExt: solo FAC 19 en ME (manual 1605: no informar en PES ni en NC/ND 20/21).
        if ($cbteTipo === 19 && $monedaId !== 'PES') {
            $cmp['CanMisMonExt'] = strtoupper((string) ($datos['can_mis_mon_ext'] ?? $datos['CanMisMonExt'] ?? 'N')) === 'S'
                ? 'S'
                : 'N';
        }

        $permisos = $this->buildPermisos($datos);
        if ($permisos !== null) {
            $cmp['Permisos'] = ['Permiso' => $permisos];
        }

        $asoc = $this->buildCmpsAsoc($datos);
        if ($asoc !== null) {
            $cmp['Cmps_asoc'] = ['Cmp_asoc' => $asoc];
        }

        // Fecha_pago obligatoria para FAC 19 + Tipo_expo 2/4 (manual 1672).
        $fechaPago = trim((string) ($datos['fecha_pago'] ?? ''));
        if ($fechaPago !== '') {
            $cmp['Fecha_pago'] = $fechaPago;
        } elseif ($cbteTipo === 19 && in_array($tipoExpo, [2, 4], true)) {
            $cmp['Fecha_pago'] = (string) $datos['fechacomprobante'];
        }

        return $cmp;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    private function buildItems(array $datos): array
    {
        $items = [];
        foreach (($datos['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (float) ($item['cantidad'] ?? $item['pro_qty'] ?? 0);
            $precio = (float) ($item['precio'] ?? $item['pro_precio_uni'] ?? 0);
            $bonif = (float) ($item['pro_bonificacion'] ?? 0);
            $total = (float) ($item['pro_total_item'] ?? ($qty * $precio));
            $umed = ArcaUnidadMedidaAfipSupport::normalizarCodigoPayload(
                $item['codigounidadmedida'] ?? $item['pro_umed'] ?? null,
                (string) ($item['unidadmedida_abreviatura'] ?? $item['unidadmedida'] ?? ''),
                (string) ($item['unidadmedida_nombre'] ?? '')
            );
            // WSFEX [1775]: umed 0/97/99 exige qty, precio y bonificación en 0.
            if (ArcaUnidadMedidaAfipSupport::esSinCantidad($umed)) {
                if (abs($qty) > 0.00001 || abs($precio) > 0.00001 || abs($bonif) > 0.00001) {
                    $umed = 7;
                } else {
                    $qty = 0.0;
                    $precio = 0.0;
                    $bonif = 0.0;
                }
            }
            $items[] = [
                'Pro_codigo' => (string) ($item['sku'] ?? $item['pro_codigo'] ?? ' '),
                'Pro_ds' => $this->sanearTexto((string) ($item['descripcion'] ?? $item['pro_ds'] ?? ''), 4000),
                'Pro_qty' => $this->moneyQty($qty),
                'Pro_umed' => $umed,
                'Pro_precio_uni' => $this->money($precio),
                'Pro_bonificacion' => $this->money($bonif),
                'Pro_total_item' => $this->money($total),
            ];
        }

        // Tributos (p.ej. IIBB) como ítem adicional — mismo criterio que el path legacy.
        $totalTributos = 0.0;
        foreach (($datos['tributos'] ?? []) as $trib) {
            if (! is_array($trib)) {
                continue;
            }
            $totalTributos += (float) ($trib['importe'] ?? 0);
        }
        if (abs($totalTributos) > 0.00001) {
            $items[] = [
                'Pro_codigo' => ' ',
                'Pro_ds' => 'Ingresos Brutos',
                'Pro_qty' => 1.0,
                'Pro_umed' => 7,
                'Pro_precio_uni' => $this->money($totalTributos),
                'Pro_bonificacion' => 0.0,
                'Pro_total_item' => $this->money($totalTributos),
            ];
        }

        if ($items === []) {
            throw new Exception('WSFEX — el comprobante no tiene ítems para FEXAuthorize.');
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{Id_permiso: string, Dst_merc: int}>|null
     */
    private function buildPermisos(array $datos): ?array
    {
        $raw = $datos['permisos'] ?? null;
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $out = [];
        foreach ($raw as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = trim((string) ($p['id_permiso'] ?? $p['Id_permiso'] ?? ''));
            if ($id === '') {
                continue;
            }
            $out[] = [
                'Id_permiso' => $id,
                'Dst_merc' => (int) ($p['dst_merc'] ?? $p['Dst_merc'] ?? 0),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{Cbte_tipo: int, Cbte_punto_vta: int, Cbte_nro: int, Cbte_cuit?: float}>|null
     */
    private function buildCmpsAsoc(array $datos): ?array
    {
        $raw = $datos['comprobantesasociados'] ?? $datos['cmps_asoc'] ?? null;
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $out = [];
        foreach ($raw as $a) {
            if (! is_array($a)) {
                continue;
            }
            $row = [
                'Cbte_tipo' => (int) ($a['tipo'] ?? $a['cbte_tipo'] ?? $a['Cbte_tipo'] ?? 0),
                'Cbte_punto_vta' => (int) ($a['ptovta'] ?? $a['cbte_punto_vta'] ?? $a['Cbte_punto_vta'] ?? 0),
                'Cbte_nro' => (int) ($a['nro'] ?? $a['cbte_nro'] ?? $a['Cbte_nro'] ?? 0),
            ];
            $cuitAsoc = preg_replace('/\D+/', '', (string) ($a['cuit'] ?? $a['cbte_cuit'] ?? '')) ?? '';
            if ($cuitAsoc !== '') {
                $row['Cbte_cuit'] = (float) $cuitAsoc;
            }
            $out[] = $row;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, string>  $ctx
     */
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
        $inicio = microtime(true);
        $ok = false;
        try {
            $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
            try {
                $raw = $client->FEXGetCMP([
                    'Auth' => [
                        'Token' => $ts['token'],
                        'Sign' => $ts['sign'],
                        'Cuit' => $cuit,
                    ],
                    'Cmp' => [
                        'Cbte_tipo' => $cbteTipo,
                        'Punto_vta' => $ptoVta,
                        'Cbte_nro' => $cbteNro,
                    ],
                ]);
            } catch (SoapFault $e) {
                throw new Exception($this->formatSoapFault('FEXGetCMP(post)', $e, $client));
            }

            $result = $raw->FEXGetCMPResult ?? null;
            if ($result === null) {
                throw new Exception('WSFEX — FEXGetCMP post-emisión sin resultado. No persistir el comprobante.');
            }
            $this->assertFexErr($result, 'FEXGetCMP(post)');

            $auth = $result->FEXResultGet ?? null;
            if ($auth === null) {
                throw new Exception('WSFEX — consulta post-emisión sin FEXResultGet. No persistir el comprobante.');
            }

            $res = strtoupper(trim((string) ($auth->Resultado ?? '')));
            if ($res !== 'A' && $res !== 'P') {
                throw new Exception('WSFEX — consulta post-emisión con Resultado='.$res.'. No persistir el comprobante.');
            }

            $caeOk = trim((string) ($auth->Cae ?? ''));
            if ($caeOk !== $caeEsperado) {
                throw new Exception(
                    "WSFEX — CAE divergente tras consulta (emitido {$caeEsperado}, consulta {$caeOk}). No persistir el comprobante."
                );
            }

            $impCons = (float) ($auth->Imp_total ?? 0);
            if (abs($impCons - $impTotalEsperado) > 0.02) {
                throw new Exception(
                    'WSFEX — importe total divergente en consulta (enviado '.
                    number_format($impTotalEsperado, 2, '.', '').
                    ', ARCA '.number_format($impCons, 2, '.', '').
                    '). No persistir el comprobante.'
                );
            }
            $ok = true;
        } finally {
            try {
                Log::info('arca.verificar_post_cae.timing', [
                    'webservice' => 'wsfex_v1',
                    'empresa_id' => $empresaId,
                    'pto_vta' => $ptoVta,
                    'cbte_tipo' => $cbteTipo,
                    'cbte_nro' => $cbteNro,
                    'ok' => $ok,
                    'ms' => (int) round((microtime(true) - $inicio) * 1000),
                ]);
            } catch (\Throwable) {
            }
        }
    }

    private function assertFexErr(object $result, string $metodo): void
    {
        $err = $result->FEXErr ?? null;
        if ($err === null) {
            return;
        }
        $code = (int) ($err->ErrCode ?? 0);
        if ($code === 0) {
            return;
        }
        $msg = trim((string) ($err->ErrMsg ?? ''));
        throw new Exception("WSFEX — {$metodo}: [{$code}] {$msg}");
    }

    /**
     * @return array{appserver?: string, dbserver?: string, authserver?: string}|object
     */
    private function callParam(int $empresaId, string $method): object
    {
        $this->assertTransporteSoap();
        $cuit = $this->cuitEmisor($empresaId);
        $ctx = $this->resolveWsaaContext($empresaId);
        $ts = $this->wsaa->getTokenSign((string) config('arca_wsfex.wsaa_service_id'), $ctx);
        $client = $this->soapClient();

        try {
            $raw = $client->{$method}([
                'Auth' => [
                    'Token' => $ts['token'],
                    'Sign' => $ts['sign'],
                    'Cuit' => $cuit,
                ],
            ]);
        } catch (SoapFault $e) {
            throw new Exception($this->formatSoapFault($method, $e, $client));
        }

        $resultKey = $method.'Result';
        $result = $raw->{$resultKey} ?? null;
        if ($result === null) {
            throw new Exception("WSFEX: {$method} sin resultado.");
        }
        $this->assertFexErr($result, $method);

        return $result;
    }

    /**
     * @return list<array{id: string, descripcion: string}>
     */
    private function normalizeParamPairs(mixed $nodes, string $idA, string $idB, string $dsA, string $dsB): array
    {
        if ($nodes === null) {
            return [];
        }
        $list = is_array($nodes) ? $nodes : [$nodes];
        $out = [];
        foreach ($list as $node) {
            if (! is_object($node)) {
                continue;
            }
            $id = trim((string) ($node->{$idA} ?? $node->{$idB} ?? ''));
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'descripcion' => trim((string) ($node->{$dsA} ?? $node->{$dsB} ?? '')),
            ];
        }

        return $out;
    }

    private function soapClient(?int $timeoutOverrideSeconds = null): SoapClient
    {
        $env = (string) config('arca.env', 'homo');
        $wsdl = $this->resolveWsfexWsdl($env);
        $timeout = $timeoutOverrideSeconds !== null && $timeoutOverrideSeconds > 0
            ? max(5, $timeoutOverrideSeconds)
            : max(10, (int) config('arca_wsfex.soap_timeout', 60));

        ini_set('default_socket_timeout', (string) $timeout);

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
     * @return resource
     */
    private function soapStreamContext(int $timeout)
    {
        return stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'anitaERP-ARCA-WSFEX',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'ciphers' => 'DEFAULT@SECLEVEL=1',
            ],
        ]);
    }

    private function resolveWsfexWsdl(string $env): string
    {
        $override = env('ARCA_WSFEX_WSDL_LOCAL');
        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $configured = config("arca_wsfex.wsfex.{$env}.wsdl_local");
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $base = rtrim((string) config('arca_wsfex.base_storage'), '/');
        $localDefault = $base.'/wsdl/'.$env.'/service.wsdl';
        if (is_readable($localDefault)) {
            return $localDefault;
        }

        $remote = config("arca_wsfex.wsfex.{$env}.wsdl");
        if (! is_string($remote) || $remote === '') {
            throw new Exception("ARCA WSFEX: WSDL no configurado para env={$env}.");
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

            $timeout = max(10, (int) config('arca_wsfex.soap_timeout', 60));
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
        $base = rtrim((string) config('arca_wsfex.base_storage'), '/');
        $emp = config("arca_wsfex.empresas.{$empresaId}");
        if (! is_array($emp) || empty($emp['carpeta_cert'])) {
            $entorno = (string) config('app.empresa', '');
            throw new Exception(
                "ARCA WSFEX: la empresa {$empresaId} no está configurada para el entorno «{$entorno}». ".
                'Revise EMPRESA en .env y config/arca_wsfex.php (empresas_por_entorno), '.
                'o defina ARCA_WSFEX_CARPETA_CERT / ARCA_WSFEX_EMPRESAS_JSON. '.
                'Copie cert.crt y privada.key en {ARCA_WSFEX_BASE}/certs/{carpeta}/'
            );
        }
        $cdir = $base.'/certs/'.$emp['carpeta_cert'];

        return [
            'cert_path' => $cdir.'/cert.crt',
            'private_key_path' => $cdir.'/privada.key',
            'private_key_passphrase' => (string) ($emp['private_key_passphrase'] ?? ''),
            'ta_storage_dir' => $base.'/ta',
            'cache_key' => 'fex_emp'.$empresaId,
            'tmp_dir' => $base.'/tmp',
        ];
    }

    private function cuitEmisor(int $empresaId): int
    {
        $row = \App\Models\Configuracion\Empresa::query()->find($empresaId);
        if ($row === null || $row->nroinscripcion === null || trim((string) $row->nroinscripcion) === '') {
            throw new Exception(
                "ARCA WSFEX: la empresa {$empresaId} no tiene CUIT en empresa.nroinscripcion ".
                '(no se usa ARCA_CUIT_REPRESENTADA; esa clave es solo para consulta de padrón).'
            );
        }
        $d = preg_replace('/\D+/', '', (string) $row->nroinscripcion) ?? '';
        if ($d === '' || strlen($d) !== 11) {
            throw new Exception("ARCA WSFEX: CUIT inválido en empresa.nroinscripcion para empresa {$empresaId}.");
        }

        return (int) $d;
    }

    private function formatSoapFault(string $metodo, SoapFault $e, ?SoapClient $client = null): string
    {
        $detail = trim((string) ($e->faultstring ?? $e->getMessage()));
        $code = trim((string) ($e->faultcode ?? ''));
        $msg = "WSFEX SOAP {$metodo}";
        if ($code !== '') {
            $msg .= " [{$code}]";
        }
        $msg .= ': '.$detail;
        if ($client !== null) {
            try {
                $last = (string) $client->__getLastResponse();
                if ($last !== '' && strlen($last) < 2000) {
                    $msg .= ' | response='.preg_replace('/\s+/', ' ', $last);
                }
            } catch (\Throwable) {
            }
        }

        return $msg;
    }

    private function money(mixed $v): float
    {
        return round((float) $v, 2);
    }

    private function moneyCotiz(mixed $v): float
    {
        return round((float) $v, 6);
    }

    private function moneyQty(mixed $v): float
    {
        return round((float) $v, 6);
    }

    private function sanearTexto(string $texto, int $maxLen): string
    {
        $t = str_replace(['&', '<', '>'], [' ', ' ', ' '], $texto);
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        if (mb_strlen($t) > $maxLen) {
            return mb_substr($t, 0, $maxLen);
        }

        return $t;
    }

    private function normalizarFechaCae(string $ymd): string
    {
        $d = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($d) === 8) {
            return substr($d, 0, 4).'-'.substr($d, 4, 2).'-'.substr($d, 6, 2);
        }

        return $ymd;
    }
}
