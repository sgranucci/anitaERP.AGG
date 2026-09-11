<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Compras\PagoproveedorAplicacionLadoSupport;

/**
 * Arma bases de retención desde conceptos del comprobante aplicado.
 *
 * - Ganancias / IIBB: líneas con retieneganancia=S / retieneIIBB=S
 * - IIBB ARBA: solo facturas con destino Buenos Aires (provincia_destino_id)
 * - SUSS / IVA-sobre-neto: tipoconcepto G (gravado); IVA discriminado = tipoconcepto I
 * - Exento / no gravado: tipoconcepto E / N (para documental y SUSS si se amplía)
 *
 * Prorratea por monto aplicado / total del comprobante y convierte a moneda de pago
 * con la cotización de la aplicación (misma lógica que el desembolso).
 * NC (CC < 0) restan; OPA no entran (ya se retuvo al generarlas).
 */
final class RetencionesPagoBasesDesdeConceptosSupport
{
    /**
     * @param  list<array{
     *   proveedor_cuentacorriente_id?:int,
     *   montoaplicado?:float|int|string,
     *   cotizacion?:float|int|string,
     *   cotizacion_aplicada?:float|int|string|null,
     *   moneda_id?:int|null
     * }>  $aplicaciones
     * @param  int  $monedaPagoId  Moneda del pago (1 = local)
     */
    public function desdeAplicaciones(
        array $aplicaciones,
        int $monedaPagoId = 1,
        ?float $cotizacionPagoHeader = null,
    ): RetencionesPagoBasesResultado {
        $netoGan = 0.0;
        $netoIibb = 0.0;
        $netoGrav = 0.0;
        $netoExe = 0.0;
        $netoNg = 0.0;
        $iva = 0.0;
        $bruto = 0.0;
        $brutoIibbBa = 0.0;
        $detalle = [];
        $tuvoConceptos = false;

        foreach ($aplicaciones as $apl) {
            $ccId = (int) ($apl['proveedor_cuentacorriente_id'] ?? 0);
            $montoApl = round(abs((float) ($apl['montoaplicado'] ?? 0)), 4);
            if ($ccId <= 0 || $montoApl <= 0) {
                continue;
            }

            $cc = Proveedor_Cuentacorriente::query()
                ->with([
                    'comprobante_proveedores.comprobante_proveedor_conceptos.concepto_ivacompras',
                    'comprobante_proveedores.provinciaDestino',
                    'monedas',
                ])
                ->find($ccId);

            if ($cc === null) {
                continue;
            }

            // OPA: restan del desembolso en la UI, no de Ganancias/IIBB/IVA/SUSS.
            if (! PagoproveedorAplicacionLadoSupport::afectaRetenciones($cc)) {
                $detalle[] = [
                    'cc_id' => $ccId,
                    'omitido_retencion' => 'opa',
                    'monto_aplicado' => $montoApl,
                ];
                continue;
            }

            $signo = PagoproveedorAplicacionLadoSupport::signo($cc);
            $cotApl = $this->resolverCotizacionAplicada($apl, $cc, $cotizacionPagoHeader);
            $monedaDeudaId = (int) ($apl['moneda_id'] ?? $cc->moneda_id ?? 1);
            $equivPago = round($signo * $this->aMonedaPago($montoApl, $monedaDeudaId, $monedaPagoId, $cotApl), 2);
            $bruto = round($bruto + $equivPago, 2);

            $cp = $cc->comprobante_proveedores;
            $destinoBa = ComprobanteProveedorProvinciaDestinoSupport::esDestinoBuenosAires($cp);
            if ($destinoBa) {
                $brutoIibbBa = round($brutoIibbBa + $equivPago, 2);
            }
            $lineas = $cp?->comprobante_proveedor_conceptos ?? collect();
            if ($cp === null || $lineas->isEmpty()) {
                $detalle[] = [
                    'cc_id' => $ccId,
                    'sin_conceptos' => true,
                    'signo' => $signo,
                    'destino_buenos_aires' => $destinoBa,
                    'monto_aplicado' => $montoApl,
                    'equivalente_pago' => $equivPago,
                ];
                continue;
            }

            $totalDoc = round((float) abs((float) ($cp->total ?? 0)), 4);
            if ($totalDoc <= 0) {
                $totalDoc = round($lineas->sum(fn ($l) => abs((float) $l->monto)), 4);
            }
            if ($totalDoc <= 0) {
                continue;
            }

            $ratio = min(1.0, $montoApl / $totalDoc);
            $tuvoConceptos = true;

            foreach ($lineas as $linea) {
                $concepto = $linea->concepto_ivacompras;
                if (! $concepto instanceof Concepto_Ivacompra) {
                    continue;
                }

                $montoLinea = abs((float) $linea->monto);
                if ($montoLinea <= 0) {
                    continue;
                }

                $porcionDeuda = round($montoLinea * $ratio, 4);
                $porcionPago = round($signo * $this->aMonedaPago($porcionDeuda, $monedaDeudaId, $monedaPagoId, $cotApl), 2);
                $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
                $retGan = strtoupper(trim((string) ($concepto->retieneganancia ?? 'N'))) === 'S';
                $retIibb = strtoupper(trim((string) ($concepto->retieneIIBB ?? 'N'))) === 'S';

                if ($retGan) {
                    $netoGan = round($netoGan + $porcionPago, 2);
                }
                if ($retIibb && $destinoBa) {
                    $netoIibb = round($netoIibb + $porcionPago, 2);
                }

                if ($tipo === 'G') {
                    $netoGrav = round($netoGrav + $porcionPago, 2);
                } elseif ($tipo === 'E') {
                    $netoExe = round($netoExe + $porcionPago, 2);
                } elseif ($tipo === 'N') {
                    $netoNg = round($netoNg + $porcionPago, 2);
                } elseif ($tipo === 'I') {
                    $iva = round($iva + $porcionPago, 2);
                }

                $detalle[] = [
                    'cc_id' => $ccId,
                    'comprobante_id' => (int) $cp->id,
                    'concepto_id' => (int) $concepto->id,
                    'concepto' => (string) $concepto->nombre,
                    'tipoconcepto' => $tipo,
                    'retieneganancia' => $retGan ? 'S' : 'N',
                    'retieneIIBB' => $retIibb ? 'S' : 'N',
                    'destino_buenos_aires' => $destinoBa,
                    'signo' => $signo,
                    'monto_linea' => $montoLinea,
                    'porcion_pago' => $porcionPago,
                    'ratio' => $ratio,
                    'cotizacion' => $cotApl,
                ];
            }
        }

        if (! $tuvoConceptos) {
            $soloOpa = $detalle !== []
                && count($detalle) === count(array_filter(
                    $detalle,
                    static fn (array $d) => ($d['omitido_retencion'] ?? '') === 'opa'
                ));

            return new RetencionesPagoBasesResultado(
                netoGanancias: $bruto,
                netoIibb: $brutoIibbBa,
                netoGravado: $bruto,
                netoExento: 0.0,
                netoNogravado: 0.0,
                importeIva: 0.0,
                brutoAplicado: $bruto,
                origen: $soloOpa ? 'opa_omitida' : 'fallback_bruto',
                detalle: $detalle,
            );
        }

        return new RetencionesPagoBasesResultado(
            netoGanancias: $netoGan,
            netoIibb: $netoIibb,
            netoGravado: $netoGrav,
            netoExento: $netoExe,
            netoNogravado: $netoNg,
            importeIva: $iva,
            brutoAplicado: $bruto,
            origen: 'conceptos',
            detalle: $detalle,
        );
    }

    /**
     * @param  array<string, mixed>  $apl
     */
    private function resolverCotizacionAplicada(array $apl, Proveedor_Cuentacorriente $cc, ?float $cotHeader): float
    {
        foreach (['cotizacion_aplicada', 'cotizacion'] as $key) {
            $v = (float) ($apl[$key] ?? 0);
            if ($v > 0) {
                return $v;
            }
        }

        $ccCot = (float) ($cc->cotizacion ?? 0);
        if ($ccCot > 0) {
            return $ccCot;
        }

        if ($cotHeader !== null && $cotHeader > 0) {
            return $cotHeader;
        }

        return 1.0;
    }

    private function aMonedaPago(float $monto, int $monedaDeudaId, int $monedaPagoId, float $cot): float
    {
        $cot = $cot > 0 ? $cot : 1.0;
        $local = 1;

        if ($monedaDeudaId === $monedaPagoId) {
            return round($monto, 2);
        }

        // Deuda ME → pago local
        if ($monedaDeudaId !== $local && $monedaPagoId === $local) {
            return round($monto * $cot, 2);
        }

        // Deuda local → pago ME
        if ($monedaDeudaId === $local && $monedaPagoId !== $local) {
            return round($monto / $cot, 2);
        }

        // ME → otra ME: convertir vía local
        return round($monto * $cot, 2);
    }
}
