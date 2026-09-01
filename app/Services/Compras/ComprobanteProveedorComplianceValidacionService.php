<?php

namespace App\Services\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Services\Arca\WscdcConstatacionService;
use App\Services\Configuracion\IIBBService;
use App\Support\Arca\WscdcImporteMargenSupport;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorIibbPadronFechaSupport;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validaciones premium al grabar factura de proveedor:
 * WSCDC (comprobantes recibidos), WSAPOC (apócrifas), gravados↔alícuotas IVA, IIBB vs padrones.
 *
 * @phpstan-type ResultadoCompliance array{
 *     ok: bool,
 *     avisos: list<string>,
 *     errores: list<string>,
 *     wscdc: array<string, mixed>|null,
 *     apoc: array<string, mixed>|null
 * }
 */
class ComprobanteProveedorComplianceValidacionService
{
    private const TOLERANCIA_IIBB_PP = 0.15;

    public function __construct(
        private WscdcConstatacionService $wscdcService,
        private ProveedorFacturasApocrifasSupport $apocrifasSupport,
        private IIBBService $iibbService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Cabecera del comprobante
     * @param  list<array{concepto_ivacompra_id: int, monto: float}>  $lineasConcepto
     * @return ResultadoCompliance
     */
    public function validarAlGrabar(array $payload, array $lineasConcepto): array
    {
        $resultado = [
            'ok' => true,
            'avisos' => [],
            'errores' => [],
            'wscdc' => null,
            'apoc' => null,
        ];

        $this->validarApoc($payload, $resultado);
        $this->validarWscdc($payload, $resultado);
        $this->validarGravadosVsTasas($lineasConcepto, $resultado);
        $this->validarIibbPadrones($payload, $lineasConcepto, $resultado);

        $resultado['ok'] = $resultado['errores'] === [];

        return $resultado;
    }

    /** @param ResultadoCompliance $resultado */
    private function validarApoc(array $payload, array &$resultado): void
    {
        if (! $this->apocrifasSupport->habilitadoParaComprobante()) {
            return;
        }

        $proveedorId = (int) ($payload['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            return;
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            $resultado['errores'][] = 'Proveedor inexistente para validación de facturas apócrifas.';

            return;
        }

        $eval = $this->apocrifasSupport->evaluarProveedor($proveedor, suspenderSiApocrifo: false);
        $resultado['apoc'] = $eval;

        if (! empty($eval['error_servicio'])) {
            $resultado['avisos'][] = (string) ($eval['mensaje'] ?? 'WSAPOC no disponible; se continúa sin bloqueo.');

            return;
        }

        if (! empty($eval['es_apocrifo'])) {
            $resultado['errores'][] = (string) ($eval['mensaje']
                ?? 'El proveedor figura en la base de facturas apócrifas de ARCA (WSAPOC).');
        }
    }

    /** @param ResultadoCompliance $resultado */
    private function validarWscdc(array $payload, array &$resultado): void
    {
        if (! $this->wscdcHabilitadoParaComprobante()) {
            return;
        }

        $tipoAut = strtoupper(trim((string) ($payload['tipo_autorizacion'] ?? '')));
        $cae = preg_replace('/\D/', '', (string) ($payload['numerocae'] ?? '')) ?? '';

        if ($cae === '' && $tipoAut === '') {
            return;
        }

        if ($tipoAut === ComprobanteProveedorTipoAutorizacion::CAEA) {
            $resultado['avisos'][] = 'CAEA informado: la constatación WSCDC de comprobantes recibidos no aplica del mismo modo que CAE; revise manualmente si corresponde.';

            return;
        }

        if (strlen($cae) !== 14) {
            $this->agregarHallazgoWscdc(
                $resultado,
                'El CAE debe tener 14 dígitos para constatar el comprobante recibido en ARCA (WSCDC).'
            );

            return;
        }

        $cmpReq = $this->armarCmpReqDesdePayload($payload, $cae);
        if ($cmpReq === null) {
            $this->agregarHallazgoWscdc(
                $resultado,
                'Faltan datos (CUIT emisor/receptor, tipo AFIP, fecha, punto de venta o número) para constatar el comprobante en ARCA (WSCDC).'
            );

            return;
        }

        try {
            $respuesta = $this->wscdcService->comprobanteConstatar($cmpReq);
        } catch (Throwable $e) {
            Log::warning('WSCDC comprobante proveedor: fallo de constatación', [
                'error' => $e->getMessage(),
                'cmp_req' => $cmpReq,
            ]);
            // WS caído / timeout / cert: nunca bloquea el grabado.
            $resultado['avisos'][] = 'No se pudo constatar en ARCA (WSCDC); se grabó igual. Revise luego. Detalle: '.$e->getMessage();
            $resultado['wscdc'] = ['ejecutada' => true, 'ok' => false, 'error' => $e->getMessage()];

            return;
        }

        $resultadoWscdc = $this->interpretarRespuestaWscdc($payload, $respuesta, $cmpReq);
        $resultado['wscdc'] = $resultadoWscdc;

        foreach ($resultadoWscdc['errores'] ?? [] as $err) {
            $this->agregarHallazgoWscdc($resultado, (string) $err);
        }
        foreach ($resultadoWscdc['avisos'] ?? [] as $aviso) {
            $resultado['avisos'][] = $aviso;
        }
    }

    /**
     * WSCDC: por defecto solo avisa (permite grabar). Con bloquear_al_fallar=true endurece.
     *
     * @param  ResultadoCompliance  $resultado
     */
    private function agregarHallazgoWscdc(array &$resultado, string $mensaje): void
    {
        if ($this->wscdcBloqueaAlFallar()) {
            $resultado['errores'][] = $mensaje;

            return;
        }

        $resultado['avisos'][] = $mensaje;
    }

    /**
     * @param  list<array{concepto_ivacompra_id: int, monto: float}>  $lineas
     * @param  ResultadoCompliance  $resultado
     */
    private function validarGravadosVsTasas(array $lineas, array &$resultado): void
    {
        if ($lineas === []) {
            return;
        }

        try {
            ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas);
        } catch (Throwable $e) {
            $resultado['errores'][] = $e->getMessage();

            return;
        }

        $conceptos = Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', collect($lineas)->pluck('concepto_ivacompra_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $tasasSistema = Impuesto::query()
            ->where('valor', '>', 0)
            ->pluck('valor')
            ->map(static fn ($v): float => round((float) $v, 3))
            ->unique()
            ->values()
            ->all();

        $ivas = [];
        $netosPorTasa = [];

        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) $linea['concepto_ivacompra_id']);
            if (! $concepto) {
                continue;
            }
            $tipo = (string) ($concepto->tipoconcepto ?? '');
            $monto = abs((float) ($linea['monto'] ?? 0));
            if ($monto < 0.0001) {
                continue;
            }

            if (ComprobanteProveedorConceptoIvaTipos::esNeto($tipo) && $tipo !== 'E') {
                $tasa = round((float) ($concepto->impuestos?->valor ?? 0), 3);
                $clave = $tasa > 0 ? (string) $tasa : 'sin_tasa';
                $netosPorTasa[$clave] = ($netosPorTasa[$clave] ?? 0) + $monto;
            }

            if ($tipo === 'I') {
                $tasa = round((float) ($concepto->impuestos?->valor ?? 0), 3);
                $ivas[] = ['monto' => $monto, 'tasa' => $tasa, 'nombre' => (string) $concepto->nombre];
            }
        }

        foreach ($ivas as $iva) {
            $tasa = (float) $iva['tasa'];
            if ($tasa <= 0) {
                $resultado['avisos'][] = 'Concepto IVA «'.$iva['nombre'].'» sin alícuota en el maestro; no se pudo validar contra tasas del sistema.';
                continue;
            }

            if ($tasasSistema !== [] && ! in_array($tasa, $tasasSistema, true)) {
                $resultado['errores'][] = sprintf(
                    'La alícuota %s%% del IVA «%s» no está cargada en impuestos del sistema.',
                    number_format($tasa, 2, ',', '.'),
                    $iva['nombre']
                );
                continue;
            }

            $netoTeorico = round($iva['monto'] / ($tasa / 100.0), 2);
            $netoReal = (float) ($netosPorTasa[(string) $tasa] ?? 0);
            if ($netoReal <= 0) {
                // Intentar neto sin tasa o total de gravados
                $netoReal = (float) array_sum($netosPorTasa);
            }

            if ($netoReal > 0 && abs($netoReal - $netoTeorico) > ComprobanteProveedorConceptosIvaCoherenciaSupport::TOLERANCIA) {
                $resultado['errores'][] = sprintf(
                    'IVA «%s» (%s%%) $%s implica gravado ≈ $%s, pero el neto informado es $%s (tol. $%s).',
                    $iva['nombre'],
                    number_format($tasa, 2, ',', '.'),
                    number_format($iva['monto'], 2, ',', '.'),
                    number_format($netoTeorico, 2, ',', '.'),
                    number_format($netoReal, 2, ',', '.'),
                    number_format(ComprobanteProveedorConceptosIvaCoherenciaSupport::TOLERANCIA, 2, ',', '.')
                );
            }
        }
    }

    /**
     * @param  list<array{concepto_ivacompra_id: int, monto: float}>  $lineas
     * @param  ResultadoCompliance  $resultado
     */
    private function validarIibbPadrones(array $payload, array $lineas, array &$resultado): void
    {
        if ($lineas === []) {
            return;
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $cuitEmpresa = '';
        if ($empresaId > 0) {
            $nro = Empresa::query()->whereKey($empresaId)->value('nroinscripcion');
            $cuitEmpresa = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(is_string($nro) ? $nro : null);
        }
        if (strlen($cuitEmpresa) !== 11) {
            return;
        }

        $fecha = (string) ($payload['fechacomprobante'] ?? '');
        $conceptos = Concepto_Ivacompra::query()
            ->with('provincias')
            ->whereIn('id', collect($lineas)->pluck('concepto_ivacompra_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $netoGravado = 0.0;
        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) $linea['concepto_ivacompra_id']);
            if ($concepto && ComprobanteProveedorConceptoIvaTipos::esNeto((string) $concepto->tipoconcepto)
                && (string) $concepto->tipoconcepto !== 'E') {
                $netoGravado += abs((float) ($linea['monto'] ?? 0));
            }
        }
        if ($netoGravado <= 0) {
            $netoGravado = (float) ($payload['subtotal'] ?? 0);
        }
        if ($netoGravado <= 0) {
            return;
        }

        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) $linea['concepto_ivacompra_id']);
            if (! $concepto) {
                continue;
            }

            // Solo tipoconcepto B (Importe percepciones IIBB). P = Perc. IVA; S = SIRCREB.
            if (! ComprobanteProveedorConceptoIvaTipos::esPercepcionIibb((string) ($concepto->tipoconcepto ?? ''))) {
                continue;
            }

            $importe = abs((float) ($linea['monto'] ?? 0));
            if ($importe < 0.0001) {
                continue;
            }

            $tasaImplicita = round(($importe / $netoGravado) * 100.0, 4);
            $jurisdiccion = (int) ($concepto->provincias?->jurisdiccion ?? 0);

            if ($jurisdiccion <= 0) {
                // Intentar ARBA/CABA por tasa.
                $match = $this->resolverJurisdiccionPorTasa($cuitEmpresa, $tasaImplicita, $fecha);
                if ($match === null) {
                    $resultado['errores'][] = sprintf(
                        'Percepción IIBB «%s» $%s (≈%s%%) no coincide con padrones ARBA/CABA y el concepto no tiene provincia/jurisdicción.',
                        $concepto->nombre,
                        number_format($importe, 2, ',', '.'),
                        number_format($tasaImplicita, 2, ',', '.')
                    );
                } else {
                    $resultado['avisos'][] = sprintf(
                        'Percepción IIBB «%s» ≈ %s%%: coincide con padrón jurisdicción %s (%s%%).',
                        $concepto->nombre,
                        number_format($tasaImplicita, 2, ',', '.'),
                        $match['jurisdiccion'],
                        number_format($match['tasa'], 2, ',', '.')
                    );
                }
                continue;
            }

            $padron = $this->iibbService->leeTasaPercepcion($cuitEmpresa, $jurisdiccion, $fecha ?: null);
            $tasaPadron = $this->iibbService->tasaPercepcionDesdePadron($padron, $jurisdiccion);

            if ($tasaPadron === null) {
                if (ComprobanteProveedorIibbPadronFechaSupport::omitirPorFacturaAnterior(
                    $fecha ?: null,
                    $this->iibbService->minDesdefechaPercepcion($cuitEmpresa, $jurisdiccion)
                )) {
                    continue;
                }
                $resultado['errores'][] = sprintf(
                    'Percepción IIBB «%s» (jur. %s): el CUIT %s no figura en el padrón descargado o no hay alícuota vigente.',
                    $concepto->nombre,
                    $jurisdiccion,
                    $this->formatearCuit($cuitEmpresa)
                );
                continue;
            }

            if (abs($tasaImplicita - $tasaPadron) > self::TOLERANCIA_IIBB_PP) {
                $resultado['errores'][] = sprintf(
                    'Percepción IIBB «%s» ≈ %s%% vs padrón jurisdicción %s %s%% (tol. %s pp) para %s.',
                    $concepto->nombre,
                    number_format($tasaImplicita, 2, ',', '.'),
                    $jurisdiccion,
                    number_format($tasaPadron, 2, ',', '.'),
                    number_format(self::TOLERANCIA_IIBB_PP, 2, ',', '.'),
                    $this->formatearCuit($cuitEmpresa)
                );
            } else {
                $resultado['avisos'][] = sprintf(
                    'IIBB «%s» OK vs padrón jur. %s (%s%%).',
                    $concepto->nombre,
                    $jurisdiccion,
                    number_format($tasaPadron, 2, ',', '.')
                );
            }
        }
    }

    /**
     * @return array{jurisdiccion: int, tasa: float}|null
     */
    private function resolverJurisdiccionPorTasa(string $cuit, float $tasaImplicita, string $fecha): ?array
    {
        foreach ([902, 901, 921, 904, 908, 914, 924] as $jur) {
            $padron = $this->iibbService->leeTasaPercepcion($cuit, $jur, $fecha ?: null);
            $tasa = $this->iibbService->tasaPercepcionDesdePadron($padron, $jur);
            if ($tasa === null) {
                continue;
            }
            if (abs($tasaImplicita - $tasa) <= self::TOLERANCIA_IIBB_PP) {
                return ['jurisdiccion' => $jur, 'tasa' => $tasa];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $respuesta
     * @param  array<string, mixed>  $cmpReq
     * @return array<string, mixed>
     */
    private function interpretarRespuestaWscdc(array $payload, array $respuesta, array $cmpReq): array
    {
        $resultadoAfip = strtoupper((string) ($respuesta['resultado'] ?? ''));
        $cmpResp = is_array($respuesta['cmp_resp'] ?? null) ? $respuesta['cmp_resp'] : [];
        $errores = [];
        $avisos = [];

        foreach (($respuesta['errores'] ?? []) as $err) {
            $errores[] = '[WSCDC '.($err['code'] ?? '?').'] '.$err['msg'];
        }

        foreach (($respuesta['observaciones'] ?? []) as $obs) {
            $avisos[] = '[WSCDC obs '.($obs['code'] ?? '?').'] '.$obs['msg'];
        }

        if ($resultadoAfip === 'R') {
            $errores[] = 'ARCA rechazó la constatación del comprobante recibido (WSCDC).';
        }

        $totalLocal = round((float) ($payload['total'] ?? 0), 2);
        $totalArca = isset($cmpResp['imp_total']) ? round((float) $cmpResp['imp_total'], 2) : null;
        if ($totalArca !== null && ! WscdcImporteMargenSupport::coinciden($totalLocal, $totalArca)) {
            $errores[] = 'Total factura ('.$totalLocal.') difiere del registrado en ARCA ('.$totalArca.').';
        }

        if ($resultadoAfip === 'A' && $errores === []) {
            $avisos[] = 'Constatación ARCA (WSCDC) OK: comprobante recibido validado.';
        }

        return [
            'ejecutada' => true,
            'ok' => $resultadoAfip === 'A' && $errores === [],
            'resultado' => $resultadoAfip,
            'cmp_req' => $cmpReq,
            'cmp_resp' => $cmpResp,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function armarCmpReqDesdePayload(array $payload, string $cae): ?array
    {
        $proveedorId = (int) ($payload['proveedor_id'] ?? 0);
        $cuitEmisor = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        if (strlen($cuitEmisor) !== 11) {
            return null;
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $cuitReceptor = '';
        if ($empresaId > 0) {
            $nro = Empresa::query()->whereKey($empresaId)->value('nroinscripcion');
            $cuitReceptor = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(is_string($nro) ? $nro : null);
        }
        if (strlen($cuitReceptor) !== 11) {
            return null;
        }

        $letra = strtoupper((string) ($payload['letra'] ?? 'A'));
        $tipoId = (int) ($payload['tipotransaccion_compra_id'] ?? 0);
        $tipo = $tipoId > 0 ? Tipotransaccion_Compra::query()->find($tipoId) : null;
        $codigoBase = (string) ($tipo?->codigoafip ?? '');
        if ($codigoBase === '') {
            return null;
        }
        $cbteTipo = (int) LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoBase, $letra);
        if ($cbteTipo <= 0) {
            return null;
        }

        $ptoVta = (int) ($payload['sucursal'] ?? 0);
        $cbteNro = (int) ($payload['numerocomprobante'] ?? 0);
        if ($ptoVta <= 0 || $cbteNro <= 0) {
            return null;
        }

        $fecha = (string) ($payload['fechacomprobante'] ?? '');
        $cbteFch = preg_replace('/\D/', '', $fecha) ?? '';
        if (strlen($cbteFch) === 8) {
            // ok Ymd already digits
        } else {
            $ts = strtotime($fecha);
            $cbteFch = $ts !== false ? date('Ymd', $ts) : '';
        }
        if (strlen($cbteFch) !== 8) {
            return null;
        }

        $modo = strtoupper(trim((string) ($payload['tipo_autorizacion'] ?? 'CAE')));
        if ($modo === '' || $modo === ComprobanteProveedorTipoAutorizacion::CAI) {
            $modo = (string) config('arca_wscdc.precarga.cbte_modo_default', 'CAE');
        }

        return [
            'cbte_modo' => $modo,
            'cuit_emisor' => $cuitEmisor,
            'pto_vta' => $ptoVta,
            'cbte_tipo' => $cbteTipo,
            'cbte_nro' => $cbteNro,
            'cbte_fch' => $cbteFch,
            'imp_total' => (float) ($payload['total'] ?? 0),
            'cod_autorizacion' => $cae,
            'doc_tipo_receptor' => '80',
            'doc_nro_receptor' => $cuitReceptor,
        ];
    }

    private function wscdcHabilitadoParaComprobante(): bool
    {
        return filter_var(config('arca_wscdc.habilitado', true), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('arca_wscdc.comprobante.habilitado', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function wscdcBloqueaAlFallar(): bool
    {
        return filter_var(config('arca_wscdc.comprobante.bloquear_al_fallar', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function formatearCuit(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) !== 11) {
            return $cuit;
        }

        return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
    }
}
