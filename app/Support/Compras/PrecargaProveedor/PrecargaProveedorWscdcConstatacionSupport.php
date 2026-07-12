<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Services\Arca\WscdcConstatacionService;
use App\Support\Arca\WscdcImporteMargenSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Constatación WSCDC para precarga PDF+IA: arma CmpReq, compara con ARCA y enriquece el payload.
 */
final class PrecargaProveedorWscdcConstatacionSupport
{
    public function __construct(
        private WscdcConstatacionService $wscdcService,
    ) {}

    /**
     * @param  array<string, mixed>  $extraido
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>
     */
    public function constatarYEnriquecer(array $extraido, array $resuelto): array
    {
        if (! $this->habilitadoParaPrecarga()) {
            $resuelto['constatacion_arca'] = ['ejecutada' => false, 'motivo' => 'deshabilitado'];

            return $resuelto;
        }

        $cmpReq = $this->armarCmpReq($extraido, $resuelto);
        if ($cmpReq === null) {
            $resuelto['constatacion_arca'] = [
                'ejecutada' => false,
                'motivo' => 'datos_insuficientes',
                'mensaje' => 'Faltan CAE, CUIT emisor/receptor o datos del comprobante para constatar en ARCA.',
            ];
            $resuelto['advertencias'] = $this->agregarAdvertencia(
                $resuelto['advertencias'] ?? [],
                'No se pudo constatar en ARCA: faltan CAE o datos del comprobante.'
            );
            $resuelto['pararevisar'] = true;

            return $resuelto;
        }

        try {
            $respuesta = $this->wscdcService->comprobanteConstatar($cmpReq);
        } catch (Throwable $e) {
            Log::channel(config('comprobante_proveedor_pdf_ia.log_channel', 'stack'))
                ->warning('WSCDC precarga: fallo de constatación', [
                    'error' => $e->getMessage(),
                    'cmp_req' => $cmpReq,
                ]);

            $resuelto['constatacion_arca'] = [
                'ejecutada' => true,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
            $resuelto['advertencias'] = $this->agregarAdvertencia(
                $resuelto['advertencias'] ?? [],
                'No se pudo constatar en ARCA: '.$e->getMessage()
            );
            $resuelto['pararevisar'] = true;

            return $resuelto;
        }

        return $this->aplicarRespuesta($extraido, $resuelto, $respuesta, $cmpReq);
    }

    public function tieneDiscrepancias(array $resuelto): bool
    {
        if (! empty($resuelto['pararevisar'])) {
            return true;
        }

        $const = $resuelto['constatacion_arca'] ?? null;
        if (! is_array($const)) {
            return false;
        }

        return ! empty($const['discrepancias'])
            || ($const['resultado'] ?? '') === 'R'
            || ($const['ok'] ?? true) === false;
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>|null
     */
    private function armarCmpReq(array $extraido, array $resuelto): ?array
    {
        $cae = preg_replace('/\D/', '', (string) ($extraido['numerocae'] ?? $resuelto['numerocae'] ?? '')) ?? '';
        if (strlen($cae) !== 14) {
            return null;
        }

        $cuitEmisor = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
            (string) ($extraido['cuit_proveedor'] ?? '')
        );
        if ($cuitEmisor === '' && ! empty($resuelto['proveedor_id'])) {
            $cuitEmisor = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $resuelto['proveedor_id'], null);
        }
        if (strlen($cuitEmisor) !== 11) {
            return null;
        }

        $empresaId = (int) ($resuelto['empresa_id'] ?? 0);
        $cuitReceptor = '';
        if ($empresaId > 0) {
            $nro = Empresa::query()->whereKey($empresaId)->value('nroinscripcion');
            $cuitReceptor = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(is_string($nro) ? $nro : null);
        }
        if ($cuitReceptor === '') {
            $cuitReceptor = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
                (string) ($extraido['cuit_destinatario'] ?? '')
            );
        }
        if (strlen($cuitReceptor) !== 11) {
            return null;
        }

        $letra = strtoupper((string) ($resuelto['letra'] ?? $extraido['letra'] ?? 'A'));
        $tipoId = (int) ($resuelto['tipotransaccion_compra_id'] ?? 0);
        $tipo = $tipoId > 0 ? Tipotransaccion_Compra::query()->find($tipoId) : null;
        $codigoBase = (string) ($tipo?->codigoafip ?? '001');
        $cbteTipo = (int) LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoBase, $letra);
        if ($cbteTipo <= 0) {
            return null;
        }

        $ptoVta = (int) ($resuelto['sucursal'] ?? $extraido['sucursal'] ?? 0);
        $cbteNro = (int) ($resuelto['numero_factura'] ?? $extraido['numero_factura'] ?? 0);
        if ($ptoVta <= 0 || $cbteNro <= 0) {
            return null;
        }

        $cbteFch = $this->fechaAyyyymmdd($resuelto['fecha_factura'] ?? $extraido['fecha_factura'] ?? null);
        if ($cbteFch === null) {
            return null;
        }

        $impTotal = (float) ($extraido['total'] ?? $resuelto['total'] ?? 0);

        return [
            'cbte_modo' => (string) config('arca_wscdc.precarga.cbte_modo_default', 'CAE'),
            'cuit_emisor' => $cuitEmisor,
            'pto_vta' => $ptoVta,
            'cbte_tipo' => $cbteTipo,
            'cbte_nro' => $cbteNro,
            'cbte_fch' => $cbteFch,
            'imp_total' => $impTotal,
            'cod_autorizacion' => $cae,
            'doc_tipo_receptor' => '80',
            'doc_nro_receptor' => $cuitReceptor,
        ];
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @param  array<string, mixed>  $resuelto
     * @param  array<string, mixed>  $respuesta
     * @param  array<string, mixed>  $cmpReq
     * @return array<string, mixed>
     */
    private function aplicarRespuesta(array $extraido, array $resuelto, array $respuesta, array $cmpReq): array
    {
        $resultado = strtoupper((string) ($respuesta['resultado'] ?? ''));
        $cmpResp = is_array($respuesta['cmp_resp'] ?? null) ? $respuesta['cmp_resp'] : [];
        $discrepancias = [];
        $advertencias = $resuelto['advertencias'] ?? [];

        foreach ($respuesta['errores'] ?? [] as $err) {
            $advertencias = $this->agregarAdvertencia($advertencias, '[ARCA error '.($err['code'] ?? '?').'] '.$err['msg']);
        }

        foreach ($respuesta['observaciones'] ?? [] as $obs) {
            $advertencias = $this->agregarAdvertencia($advertencias, '[ARCA obs '.($obs['code'] ?? '?').'] '.$obs['msg']);
        }

        if ($resultado === 'R') {
            $discrepancias[] = 'ARCA rechazó la constatación del comprobante.';
        }

        $totalIa = round((float) ($extraido['total'] ?? $resuelto['total'] ?? 0), 2);
        $totalArca = isset($cmpResp['imp_total']) ? round((float) $cmpResp['imp_total'], 2) : null;

        if ($totalArca !== null) {
            if (! WscdcImporteMargenSupport::coinciden($totalIa, $totalArca)) {
                $discrepancias[] = 'Total IA ('.$totalIa.') difiere del registrado en ARCA ('.$totalArca.').';
            } else {
                $resuelto['total'] = $totalArca;
            }
        }

        if (isset($cmpResp['cbte_fch'])) {
            $fechaArcaYmd = (string) $cmpResp['cbte_fch'];
            $fechaArca = $this->yyyymmddAiso($fechaArcaYmd);
            $fechaIaYmd = $this->fechaAyyyymmdd($resuelto['fecha_factura'] ?? $extraido['fecha_factura'] ?? null);
            if ($fechaIaYmd !== null && $fechaArcaYmd !== $fechaIaYmd) {
                $discrepancias[] = 'Fecha comprobante IA ('.$this->yyyymmddAiso($fechaIaYmd).') difiere de ARCA ('.($fechaArca ?? $fechaArcaYmd).').';
            }
            if ($fechaArca !== null) {
                $resuelto['fecha_factura'] = $fechaArca;
            }
        }

        if (isset($cmpResp['pto_vta']) && (int) $cmpResp['pto_vta'] !== (int) ($resuelto['sucursal'] ?? 0)) {
            $discrepancias[] = 'Sucursal IA ('.($resuelto['sucursal'] ?? '—').') difiere de ARCA ('.$cmpResp['pto_vta'].').';
            $resuelto['sucursal'] = (int) $cmpResp['pto_vta'];
        }

        if (isset($cmpResp['cbte_nro']) && (int) $cmpResp['cbte_nro'] !== (int) ($resuelto['numero_factura'] ?? 0)) {
            $discrepancias[] = 'Número comprobante IA ('.($resuelto['numero_factura'] ?? '—').') difiere de ARCA ('.$cmpResp['cbte_nro'].').';
            $resuelto['numero_factura'] = (int) $cmpResp['cbte_nro'];
        }

        if (isset($cmpResp['cod_autorizacion'])) {
            $caeArca = preg_replace('/\D/', '', (string) $cmpResp['cod_autorizacion']) ?? '';
            $caeIa = preg_replace('/\D/', '', (string) ($extraido['numerocae'] ?? '')) ?? '';
            if ($caeArca !== '' && $caeIa !== '' && $caeArca !== $caeIa) {
                $discrepancias[] = 'CAE informado por IA difiere del registrado en ARCA.';
            }
            if ($caeArca !== '') {
                $resuelto['numerocae'] = $caeArca;
            }
        }

        foreach ($discrepancias as $d) {
            $advertencias = $this->agregarAdvertencia($advertencias, $d);
        }

        $pararevisar = ! empty($resuelto['pararevisar'])
            || $resultado === 'R'
            || $discrepancias !== [];

        $resuelto['advertencias'] = $advertencias;
        $resuelto['pararevisar'] = $pararevisar;
        $resuelto['constatacion_arca'] = [
            'ejecutada' => true,
            'ok' => $resultado === 'A' && $discrepancias === [],
            'resultado' => $resultado,
            'fch_proceso' => $respuesta['fch_proceso'] ?? null,
            'cmp_resp' => $cmpResp,
            'cmp_req' => $cmpReq,
            'discrepancias' => $discrepancias,
            'total_ia' => $totalIa,
            'total_arca' => $totalArca,
            'observaciones' => $respuesta['observaciones'] ?? [],
            'errores' => $respuesta['errores'] ?? [],
        ];

        if ($pararevisar) {
            $advertencias = $this->agregarAdvertencia(
                $advertencias,
                'La precarga quedará marcada con errores (para revisar) por discrepancias con ARCA.'
            );
            $resuelto['advertencias'] = $advertencias;
        } elseif ($resultado === 'A') {
            $advertencias = $this->agregarAdvertencia(
                $advertencias,
                'Constatación ARCA OK: total y datos del comprobante coinciden.'
            );
            $resuelto['advertencias'] = $advertencias;
        }

        return $resuelto;
    }

    private function habilitadoParaPrecarga(): bool
    {
        return filter_var(config('arca_wscdc.habilitado', true), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('arca_wscdc.precarga.habilitado', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  list<string>  $advertencias
     * @return list<string>
     */
    private function agregarAdvertencia(array $advertencias, string $mensaje): array
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return $advertencias;
        }

        if (! in_array($mensaje, $advertencias, true)) {
            $advertencias[] = $mensaje;
        }

        return $advertencias;
    }

    private function fechaAyyyymmdd(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = trim((string) $valor);
        if (preg_match('/^\d{8}$/', $texto)) {
            return $texto;
        }

        $ts = strtotime($texto);

        return $ts !== false ? date('Ymd', $ts) : null;
    }

    private function yyyymmddAiso(string $yyyymmdd): ?string
    {
        if (! preg_match('/^\d{8}$/', $yyyymmdd)) {
            return null;
        }

        $y = substr($yyyymmdd, 0, 4);
        $m = substr($yyyymmdd, 4, 2);
        $d = substr($yyyymmdd, 6, 2);

        return checkdate((int) $m, (int) $d, (int) $y) ? "{$y}-{$m}-{$d}" : null;
    }
}
