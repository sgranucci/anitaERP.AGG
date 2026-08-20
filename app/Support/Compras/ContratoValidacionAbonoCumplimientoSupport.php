<?php

namespace App\Support\Compras;

/**
 * Evalúa si la validación de abono permite confirmar COM, contabilizar FAC
 * o enviar el legajo a Cuentas a pagar.
 *
 * @phpstan-import-type Politica from ContratoValidacionAbonoPoliticaSupport
 *
 * @phpstan-type Validacion array{
 *     estado?: string|null,
 *     ingresos_informados?: int|null
 * }
 *
 * @phpstan-type Resultado array{
 *     aplica: bool,
 *     completa: bool,
 *     ingresos_ok: bool,
 *     puede_confirmar_com: bool,
 *     puede_contabilizar_fac: bool,
 *     puede_enviar_cxp: bool,
 *     errores: list<string>
 * }
 */
final class ContratoValidacionAbonoCumplimientoSupport
{
    /**
     * @param  Politica  $politica
     * @param  Validacion|null  $validacion
     * @return Resultado
     */
    public static function evaluar(array $politica, ?array $validacion): array
    {
        $aplica = (bool) ($politica['aplica'] ?? false);
        $completa = strtoupper((string) ($validacion['estado'] ?? '')) === ContratoValidacionAbonoEstados::COMPLETA;
        $ingresos = (int) ($validacion['ingresos_informados'] ?? 0);
        $minimo = max(1, (int) ($politica['minimo_ingresos'] ?? 1));
        $exigeIngresos = (bool) ($politica['exige_ingresos'] ?? false);
        $ingresosOk = ! $exigeIngresos || ($completa && $ingresos >= $minimo);

        $errores = [];
        if ($aplica && ! $completa) {
            $errores[] = 'Falta completar la validación de abono / servicio del período.';
        }
        if ($aplica && $completa && $exigeIngresos && ! $ingresosOk) {
            $errores[] = 'El contrato exige al menos '.$minimo
                .' ingreso(s) de planta en el período y hay '.$ingresos.'.';
        }

        $ok = $errores === [];
        $cortaCom = ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica);
        $cortaFac = ContratoValidacionAbonoPoliticaSupport::cortaFactura($politica);

        return [
            'aplica' => $aplica,
            'completa' => $completa,
            'ingresos_ok' => $ingresosOk,
            'puede_confirmar_com' => ! $aplica || ($cortaCom ? $ok : true),
            'puede_contabilizar_fac' => ! $aplica || ($cortaFac ? $ok : true),
            'puede_enviar_cxp' => $ok,
            'errores' => $errores,
        ];
    }

    /** @param Resultado $resultado */
    public static function mensajeBloqueo(array $resultado, string $accion): string
    {
        $detalle = implode(' ', $resultado['errores']);
        if ($detalle === '') {
            return 'No se puede '.$accion.' por incumplimiento de contrato.';
        }

        return 'No se puede '.$accion.': '.$detalle;
    }
}
