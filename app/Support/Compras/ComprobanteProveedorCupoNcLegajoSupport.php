<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;

/**
 * Cupo de notas de crédito del legajo para desbloquear FC vs COM fuera de tolerancia.
 *
 * Si en el mismo legajo hay NC (precarga o CP) cuyo neto comparable cubre el exceso
 * FC − provisión COM, se permite asignar/cargar/asentar: el excedente se imputa a
 * cuentas de la OC (como el camino ≤5%) y la NC se contabiliza después.
 *
 * Las NC ya vinculadas por pivot a una COM no suman cupo: ya bajan el «ya facturado».
 */
final class ComprobanteProveedorCupoNcLegajoSupport
{
    public static function excesoSobreProvision(float $asignado, float $provision): float
    {
        $exceso = round($asignado - $provision, 2);

        return $exceso > 0.00001 ? $exceso : 0.0;
    }

    /**
     * @return array{aplicado: float, residual: float, cupo_restante: float}
     */
    public static function aplicarCupo(float $exceso, float $cupoDisponible): array
    {
        $exceso = max(0.0, round($exceso, 2));
        $cupo = max(0.0, round($cupoDisponible, 2));
        $aplicado = min($exceso, $cupo);

        return [
            'aplicado' => round($aplicado, 2),
            'residual' => round($exceso - $aplicado, 2),
            'cupo_restante' => round($cupo - $aplicado, 2),
        ];
    }

    public static function asignadoEfectivoTrasCupo(float $asignado, float $cupoAplicado): float
    {
        return round(max(0.0, $asignado - max(0.0, $cupoAplicado)), 2);
    }

    /**
     * True si, tras aplicar cupo NC al exceso, el comparable efectivo queda dentro de tolerancia %.
     */
    public static function dentroDeToleranciaTrasCupoNc(
        float $asignado,
        float $provision,
        float $cupoNcDisponible,
        float $toleranciaPct,
    ): bool {
        $exceso = self::excesoSobreProvision($asignado, $provision);
        if ($exceso <= 0.00001) {
            return true;
        }

        $aplicado = self::aplicarCupo($exceso, $cupoNcDisponible)['aplicado'];
        $efectivo = self::asignadoEfectivoTrasCupo($asignado, $aplicado);

        return ! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(
            $efectivo,
            $provision,
            $toleranciaPct
        );
    }

    /**
     * Asiento: permite prorratear far_diferencia aunque el % supere TOLERANCIA_PCT
     * si el cupo NC cubre al menos la parte fuera de la banda %.
     */
    public static function diferenciaAsientoPermitidaConCupoNc(
        float $diferenciaNeto,
        float $provision,
        float $cupoNcDisponible,
        float $porcentajeMax = ComprobanteProveedorAsientoCuadreSupport::TOLERANCIA_PCT,
    ): bool {
        if (! ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar($diferenciaNeto)) {
            return true;
        }

        if (ComprobanteProveedorAsientoCuadreSupport::diferenciaDentroDePorcentaje(
            $diferenciaNeto,
            $provision,
            $porcentajeMax
        )) {
            return true;
        }

        // Solo sobrefacturación se cubre con NC; defecto (FC < COM) no.
        if ($diferenciaNeto <= 0) {
            return false;
        }

        $exceso = round($diferenciaNeto, 2);
        $bandaPermitida = round(abs($provision) * max(0.0, $porcentajeMax) / 100.0, 2);
        $fueraDeBanda = max(0.0, round($exceso - $bandaPermitida, 2));
        if ($fueraDeBanda <= ComprobanteProveedorImporteComparacionComSupport::tolerancia()) {
            return true;
        }

        return round($cupoNcDisponible, 2) + 0.0001 >= $fueraDeBanda;
    }

    /**
     * Consume cupo NC contra varios excesos en orden estable de clave (greedy).
     *
     * @param  array<int|string, float>  $excesosPorClave
     * @return array{efectivos_reduccion: array<int|string, float>, cupo_restante: float}
     */
    public static function consumirCupoContraExcesos(float $cupoNc, array $excesosPorClave): array
    {
        $restante = max(0.0, round($cupoNc, 2));
        $aplicado = [];
        $claves = array_keys($excesosPorClave);
        usort($claves, static fn ($a, $b) => strcmp((string) $a, (string) $b));

        foreach ($claves as $clave) {
            $exceso = max(0.0, round((float) $excesosPorClave[$clave], 2));
            $r = self::aplicarCupo($exceso, $restante);
            $aplicado[$clave] = $r['aplicado'];
            $restante = $r['cupo_restante'];
        }

        return [
            'efectivos_reduccion' => $aplicado,
            'cupo_restante' => $restante,
        ];
    }

    /**
     * Suma neto comparable de NC del legajo (precargas + CP, sin doble conteo).
     * Excluye CP NC ya vinculadas a COM (entran por ya-facturado).
     */
    public static function cupoNcComparableDelLegajo(Ordencompra $oc): float
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $ocId = (int) ($oc->id ?? 0);
        if ($numero === '' || $empresaId <= 0) {
            return 0.0;
        }

        $porClave = [];

        $precargas = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->with([
                'tipotransaccion_compras:id,abreviatura,codigoafip,signo,nombre',
                'proveedores:id,condicioniva_id',
                'precarga_comprobante_proveedor_conceptos.concepto_ivacompras',
            ])
            ->get([
                'id', 'letra', 'subtotal', 'total', 'proveedor_id', 'tipotransaccion_compra_id',
            ]);

        foreach ($precargas as $pre) {
            if (OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre) !== 'NC') {
                continue;
            }
            $porClave[(int) $pre->id] = self::comparableDeDocumento(
                (string) ($pre->letra ?? ''),
                isset($pre->proveedores) ? (int) ($pre->proveedores->condicioniva_id ?? 0) : null,
                (float) ($pre->total ?? 0),
                (float) ($pre->subtotal ?? 0),
                $pre->precarga_comprobante_proveedor_conceptos ?? [],
            );
        }

        $precargaIds = array_keys($porClave);
        $cps = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($ocId, $precargaIds) {
                if ($ocId > 0) {
                    $q->where('ordencompra_id', $ocId);
                }
                if ($precargaIds !== []) {
                    $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
                }
            })
            ->with([
                'tipotransaccion_compras:id,abreviatura,codigoafip,signo,nombre',
                'proveedores:id,condicioniva_id',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'comprobante_proveedor_recepciones:id,comprobante_proveedor_id,recepcion_proveedor_id',
            ])
            ->get([
                'id', 'letra', 'subtotal', 'total', 'proveedor_id',
                'tipotransaccion_compra_id', 'precarga_comprobante_proveedor_id', 'estado',
            ]);

        foreach ($cps as $cp) {
            if (OrdencompraLegajoDocumentoTipoSupport::desdeComprobante($cp) !== 'NC') {
                continue;
            }
            // Ya vinculada a COM: el comparable negativo entra por ya-facturado.
            $vinculos = $cp->comprobante_proveedor_recepciones ?? collect();
            if ($vinculos->contains(static fn ($v) => (int) ($v->recepcion_proveedor_id ?? 0) > 0)) {
                $preId = (int) ($cp->precarga_comprobante_proveedor_id ?? 0);
                if ($preId > 0) {
                    unset($porClave[$preId]);
                }
                continue;
            }

            $comparable = self::comparableDeDocumento(
                (string) ($cp->letra ?? ''),
                isset($cp->proveedores) ? (int) ($cp->proveedores->condicioniva_id ?? 0) : null,
                (float) ($cp->total ?? 0),
                (float) ($cp->subtotal ?? 0),
                $cp->comprobante_proveedor_conceptos ?? [],
            );
            $preId = (int) ($cp->precarga_comprobante_proveedor_id ?? 0);
            if ($preId > 0) {
                $porClave[$preId] = $comparable;
            } else {
                $porClave['cp-'.(int) $cp->id] = $comparable;
            }
        }

        $suma = 0.0;
        foreach ($porClave as $monto) {
            $suma += abs((float) $monto);
        }

        return round($suma, 2);
    }

    /**
     * @param  iterable<object>  $conceptos
     */
    private static function comparableDeDocumento(
        string $letra,
        ?int $condicionIvaId,
        float $total,
        float $subtotal,
        iterable $conceptos,
    ): float {
        if ($condicionIvaId !== null && $condicionIvaId <= 0) {
            $condicionIvaId = null;
        }
        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            $letra,
            $condicionIvaId,
            $total,
            $subtotal,
            $conceptos,
            false,
        );

        return abs(round((float) $meta['importe'], 2));
    }
}
