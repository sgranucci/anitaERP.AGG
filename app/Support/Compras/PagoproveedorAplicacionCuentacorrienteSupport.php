<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use RuntimeException;

/**
 * Aplica montos de OP a deuda de proveedor (espejo de Cobranza_ComprobanteRepository).
 */
final class PagoproveedorAplicacionCuentacorrienteSupport
{
    /**
     * @param  list<array{
     *   proveedor_cuentacorriente_id:int,
     *   montoaplicado:float,
     *   moneda_id:int,
     *   cotizacion:float,
     *   cotizacion_aplicada?:float|null,
     *   diferencia_cambio?:float|null
     * }>  $aplicaciones
     */
    public static function reemplazarAplicaciones(Pagoproveedor $pago, array $aplicaciones): void
    {
        self::revertirAplicacionesExistentes($pago);

        if ($aplicaciones === []) {
            return;
        }

        $comprobantes = [];
        foreach ($aplicaciones as $apl) {
            $ccId = (int) ($apl['proveedor_cuentacorriente_id'] ?? 0);
            $monto = round(abs((float) ($apl['montoaplicado'] ?? 0)), 4);
            if ($ccId <= 0 || $monto <= 0) {
                continue;
            }

            $deuda = Proveedor_Cuentacorriente::query()
                ->with(['comprobante_proveedores.ordencompras.ordencompra_articulos', 'comprobante_proveedores.tipotransaccion_compras'])
                ->find($ccId);
            if ($deuda === null) {
                throw new RuntimeException("Cuenta corriente proveedor #{$ccId} no encontrada.");
            }
            if ((int) $deuda->proveedor_id !== (int) $pago->proveedor_id) {
                throw new RuntimeException('La deuda no pertenece al proveedor de la OP.');
            }
            if ((int) $deuda->empresa_id !== (int) $pago->empresa_id) {
                throw new RuntimeException('La deuda no pertenece a la empresa de la OP.');
            }

            if ($deuda->comprobante_proveedores instanceof Comprobante_Proveedor) {
                $comprobantes[] = $deuda->comprobante_proveedores;
            }
        }

        if ($comprobantes !== []) {
            ProveedorCuentaContableMonedaSupport::assertMismaMonedaOcEnPago($comprobantes);
        }

        $etiquetaOp = $pago->etiquetaComprobante();
        $fecha = $pago->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');

        foreach ($aplicaciones as $apl) {
            $ccId = (int) ($apl['proveedor_cuentacorriente_id'] ?? 0);
            $monto = round(abs((float) ($apl['montoaplicado'] ?? 0)), 4);
            if ($ccId <= 0 || $monto <= 0) {
                continue;
            }

            $deuda = Proveedor_Cuentacorriente::query()->with('comprobante_proveedores')->findOrFail($ccId);
            $monedaId = (int) ($deuda->moneda_id ?? $apl['moneda_id'] ?? 1);
            $cotizacion = (float) ($deuda->cotizacion ?? $apl['cotizacion'] ?? 1);
            $cotAplicada = isset($apl['cotizacion_aplicada']) && (float) $apl['cotizacion_aplicada'] > 0
                ? (float) $apl['cotizacion_aplicada']
                : $cotizacion;
            $dc = isset($apl['diferencia_cambio']) ? round((float) $apl['diferencia_cambio'], 4) : null;
            if ($dc === null) {
                $liq = PagoproveedorLiquidacionSupport::calcular(
                    $monto,
                    $monedaId,
                    $cotizacion,
                    (int) ($pago->moneda_id ?? 1),
                    $cotAplicada
                );
                $dc = $liq['dc'];
                $cotAplicada = $liq['cotizacion_aplicada'];
            }

            $codigoComp = self::codigoComprobante($deuda);

            $ccPago = Proveedor_Cuentacorriente::query()->create([
                'fecha' => $fecha,
                'fechavencimiento' => $fecha,
                'proveedor_id' => $pago->proveedor_id,
                'total' => -$monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'empresa_id' => $pago->empresa_id,
                'comprobante_proveedor_id' => $deuda->comprobante_proveedor_id,
                'comprobante_proveedor_cuota_id' => null,
                'pagoproveedor_id' => $pago->id,
            ]);

            Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'proveedor_cuentacorriente_id' => $deuda->id,
                'total' => -$monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'comprobanteaplicado' => $etiquetaOp,
                'empresa_id' => $pago->empresa_id,
                'proveedor_cuentacorriente_aplicado_id' => $ccPago->id,
                'pagoproveedor_id' => $pago->id,
            ]);

            Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'proveedor_cuentacorriente_id' => $ccPago->id,
                'total' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'comprobanteaplicado' => $codigoComp,
                'comprobante_proveedor_aplicado_id' => $deuda->comprobante_proveedor_id,
                'empresa_id' => $pago->empresa_id,
                'proveedor_cuentacorriente_aplicado_id' => $deuda->id,
                'pagoproveedor_id' => $pago->id,
            ]);

            Pagoproveedor_Comprobante::query()->create([
                'pagoproveedor_id' => $pago->id,
                'proveedor_cuentacorriente_id' => $deuda->id,
                'montoaplicado' => $monto,
                'cotizacion' => $cotizacion,
                'moneda_id' => $monedaId,
                'cotizacion_aplicada' => $cotAplicada,
                'diferencia_cambio' => $dc ?? 0,
                'proveedor_cuentacorriente_dc_id' => null,
            ]);
        }
    }

    public static function revertirAplicacionesExistentes(Pagoproveedor $pago): void
    {
        $pagoId = (int) $pago->id;

        Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('pagoproveedor_id', $pagoId)
            ->delete();

        Proveedor_Cuentacorriente::query()
            ->where('pagoproveedor_id', $pagoId)
            ->delete();

        Pagoproveedor_Comprobante::query()
            ->where('pagoproveedor_id', $pagoId)
            ->delete();
    }

    /**
     * Anticipo a proveedor (sobrepago sin aplicar a deuda).
     */
    public static function crearAnticipo(Pagoproveedor $pago, float $monto, int $monedaId, float $cotizacion): void
    {
        $monto = round(abs($monto), 4);
        if ($monto <= 0) {
            return;
        }

        $fecha = $pago->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');

        Proveedor_Cuentacorriente::query()->create([
            'fecha' => $fecha,
            'fechavencimiento' => $fecha,
            'proveedor_id' => $pago->proveedor_id,
            'total' => -$monto,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'empresa_id' => $pago->empresa_id,
            'pagoproveedor_id' => $pago->id,
        ]);
    }

    private static function codigoComprobante(Proveedor_Cuentacorriente $deuda): string
    {
        $c = $deuda->comprobante_proveedores;
        if ($c === null) {
            return 'CC#'.$deuda->id;
        }

        $tipo = (string) ($c->tipotransaccion_compras?->abreviatura ?? 'FAC');

        return sprintf('%s %s-%04d-%s', $tipo, $c->letra, (int) $c->sucursal, $c->numerocomprobante);
    }
}
