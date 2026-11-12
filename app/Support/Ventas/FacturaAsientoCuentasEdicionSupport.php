<?php

namespace App\Support\Ventas;

use App\Exceptions\Contable\PeriodoContableCerradoException;
use App\Models\Contable\Asiento;
use App\Models\Ventas\Venta;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\Auth;

/**
 * Edición de cuentas del asiento desde la pantalla de factura de ventas.
 * Solo con período contable abierto (o permiso / apertura de período cerrado).
 */
final class FacturaAsientoCuentasEdicionSupport
{
    /**
     * @return array{
     *   asiento:?Asiento,
     *   puede_editar:bool,
     *   periodo_cerrado:bool,
     *   mensaje:?string
     * }
     */
    public static function estadoParaVista(Venta $venta): array
    {
        $asiento = $venta->asientos;
        if (! $asiento instanceof Asiento || (int) $asiento->id <= 0) {
            return [
                'asiento' => null,
                'puede_editar' => false,
                'periodo_cerrado' => false,
                'mensaje' => null,
            ];
        }

        if (! can('actualizar-asiento', false)) {
            return [
                'asiento' => $asiento,
                'puede_editar' => false,
                'periodo_cerrado' => false,
                'mensaje' => 'Sin permiso para actualizar asientos contables.',
            ];
        }

        $empresaId = (int) ($asiento->empresa_id ?? 0);
        $fecha = self::fechaAsiento($asiento, $venta);
        $cerrado = $empresaId > 0 && $fecha !== ''
            && PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
                $empresaId,
                $fecha,
                PeriodoContableCierreSupport::ALCANCE_CONTABLE
            );

        try {
            if ($empresaId > 0 && $fecha !== '') {
                PeriodoContableCierreSupport::assertOperacionPermitida(
                    $empresaId,
                    $fecha,
                    PeriodoContableCierreSupport::ALCANCE_CONTABLE,
                    (int) (Auth::id() ?? 0) ?: null
                );
            }

            return [
                'asiento' => $asiento,
                'puede_editar' => true,
                'periodo_cerrado' => $cerrado,
                'mensaje' => $cerrado
                    ? 'El período contable está cerrado, pero su usuario puede operar en período cerrado.'
                    : null,
            ];
        } catch (PeriodoContableCerradoException $e) {
            return [
                'asiento' => $asiento,
                'puede_editar' => false,
                'periodo_cerrado' => true,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mapea la solapa formasientoexterno al payload del ABM de asientos.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function payloadDesdeFormularioExterno(Asiento $asiento, array $request): array
    {
        $cuentas = array_values((array) ($request['cuentacontable_ids'] ?? []));
        $debes = array_values((array) ($request['debeasientos'] ?? []));
        $haberes = array_values((array) ($request['haberasientos'] ?? []));
        $centros = array_values((array) ($request['centrocostoasiento_ids'] ?? []));
        $monedas = array_values((array) ($request['monedaasiento_ids'] ?? []));
        $cotizaciones = array_values((array) ($request['cotizacionasientos'] ?? []));
        $observaciones = array_values((array) ($request['observacionasientos'] ?? []));

        $payload = $asiento->toArray();
        $payload['empresa_id'] = (int) $asiento->empresa_id;
        $payload['tipoasiento_id'] = (int) ($request['tipoasiento_id'] ?? $asiento->tipoasiento_id);
        $payload['fecha'] = (string) ($request['fechaasiento'] ?? $asiento->fecha);
        $payload['observacion'] = (string) ($request['observacionasiento'] ?? $asiento->observacion ?? '');
        $payload['numeroasiento'] = (string) ($request['numeroasiento'] ?? $asiento->numeroasiento ?? '');
        $payload['venta_id'] = (int) $asiento->venta_id;
        $payload['cuentacontable_ids'] = $cuentas;
        $payload['debes'] = $debes;
        $payload['haberes'] = $haberes;
        $payload['centrocosto_ids'] = $centros;
        $payload['moneda_ids'] = $monedas;
        $payload['cotizaciones'] = $cotizaciones;
        $payload['observaciones'] = $observaciones;
        $payload['alcance_cierre_contable'] = PeriodoContableCierreSupport::ALCANCE_CONTABLE;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function assertBalance(array $payload): void
    {
        AsientoBalanceSupport::assertValidoParaCrudAsiento($payload);
    }

    private static function fechaAsiento(Asiento $asiento, Venta $venta): string
    {
        $fecha = trim((string) ($asiento->fecha ?? ''));
        if ($fecha !== '') {
            return substr($fecha, 0, 10);
        }

        $fechaVenta = $venta->fecha ?? null;
        if ($fechaVenta instanceof \DateTimeInterface) {
            return $fechaVenta->format('Y-m-d');
        }

        return substr(trim((string) $fechaVenta), 0, 10);
    }
}
