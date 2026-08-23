<?php

namespace App\Support\Compras;

/**
 * Flags de contrato que disparan validación de abono / control de ingresos.
 *
 * @phpstan-type Politica array{
 *     es_contrato: bool,
 *     aplica: bool,
 *     requiere_recepcion: bool,
 *     requiere_validacion: bool,
 *     exige_ingresos: bool,
 *     minimo_ingresos: int,
 *     plantilla_id: int,
 *     periodo: string,
 *     responsable_id: int
 * }
 */
final class ContratoValidacionAbonoPoliticaSupport
{
    /**
     * @param  object|null  $oc  Cabecera de orden de compra (array-like / Eloquent).
     * @return Politica
     */
    public static function desdeOc(?object $oc): array
    {
        $vacio = [
            'es_contrato' => false,
            'aplica' => false,
            'requiere_recepcion' => true,
            'requiere_validacion' => false,
            'exige_ingresos' => false,
            'minimo_ingresos' => 1,
            'plantilla_id' => 0,
            'periodo' => ContratoPeriodoServicioSupport::MES_VENCIDO,
            'responsable_id' => 0,
        ];

        if (! $oc || ! (bool) ($oc->es_contrato ?? false)) {
            return $vacio;
        }

        $exigeIngresos = (bool) ($oc->contrato_exige_ingresos ?? false);
        $requiereValidacion = (bool) ($oc->contrato_requiere_validacion_abono ?? false) || $exigeIngresos;
        $minimo = (int) ($oc->contrato_minimo_ingresos ?? 1);
        if ($minimo < 1) {
            $minimo = 1;
        }

        return [
            'es_contrato' => true,
            'aplica' => $requiereValidacion,
            'requiere_recepcion' => (bool) ($oc->contrato_requiere_recepcion ?? true),
            'requiere_validacion' => $requiereValidacion,
            'exige_ingresos' => $exigeIngresos,
            'minimo_ingresos' => $minimo,
            'plantilla_id' => (int) ($oc->contrato_validacion_plantilla_id ?? 0),
            'periodo' => ContratoPeriodoServicioSupport::normalizar($oc->contrato_periodo_servicio ?? null),
            'responsable_id' => (int) ($oc->contrato_responsable_id ?? 0),
        ];
    }

    /** @param Politica $politica */
    public static function cortaRecepcion(array $politica): bool
    {
        if ($politica['exige_ingresos'] ?? false) {
            return true;
        }

        return $politica['aplica'] && $politica['requiere_recepcion'];
    }

    /** @param Politica $politica */
    public static function cortaFactura(array $politica): bool
    {
        return $politica['aplica'] && ! $politica['requiere_recepcion'];
    }
}
