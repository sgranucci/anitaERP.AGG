<?php

namespace App\Support\Ventas\Waitry;

use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;

/**
 * getordersdetails no incluye payment.type; se completa desde getOrdersPOS cuando falta.
 * También completa table/layout (punto de acceso) para repartir ingresos por tótem físico.
 */
final class WaitryOrdenPaymentEnriquecimientoSupport
{
    /**
     * @param  array<string, mixed>  $orden
     */
    public static function ordenTieneTipoPago(array $orden): bool
    {
        return WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden) !== null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function ordenRequiereEnriquecimientoPayment(array $orden): bool
    {
        if (self::ordenTieneTipoPago($orden)) {
            return false;
        }

        return WaitryOrdenCobroSupport::cobradaEnTotem($orden);
    }

    /**
     * @param  array<string, mixed>  $ordenBase  Orden getordersdetails (o similar)
     * @param  array<string, mixed>  $ordenPos   Orden getOrdersPOS con bloque payment
     * @return array<string, mixed>
     */
    public static function fusionarAccesoDesdePos(array $ordenBase, array $ordenPos): array
    {
        $orden = $ordenBase;
        $accesoPos = WaitryTableAccesoSupport::extraerDesdeOrden($ordenPos);
        $accesoBase = WaitryTableAccesoSupport::extraerDesdeOrden($orden);

        if (($accesoBase['layout_id'] ?? null) === null && ($accesoPos['layout_id'] ?? null) !== null) {
            if (is_array($ordenPos['table'] ?? null)) {
                $orden['table'] = $ordenPos['table'];
            }
            if (! isset($orden['tableId']) && ($accesoPos['table_id'] ?? null) !== null) {
                $orden['tableId'] = $accesoPos['table_id'];
            }
        } elseif (($accesoBase['table_id'] ?? null) === null && ($accesoPos['table_id'] ?? null) !== null) {
            if (is_array($ordenPos['table'] ?? null)) {
                $orden['table'] = $ordenPos['table'];
            }
            $orden['tableId'] = $accesoPos['table_id'];
        }

        return $orden;
    }

    public static function fusionarPaymentDesdePos(array $ordenBase, array $ordenPos): array
    {
        $orden = self::fusionarAccesoDesdePos($ordenBase, $ordenPos);

        $paymentPos = $ordenPos['payment'] ?? null;
        if (is_array($paymentPos) && $paymentPos !== []) {
            $paymentBase = is_array($orden['payment'] ?? null) ? $orden['payment'] : [];
            $orden['payment'] = array_merge($paymentBase, $paymentPos);
        }

        if (! array_key_exists('paid', $orden) && array_key_exists('paid', $ordenPos)) {
            $orden['paid'] = $ordenPos['paid'];
        }

        if (WaitryOrdenCobroSupport::montoCobro($orden) <= 0.0001) {
            $montoPos = WaitryOrdenCobroSupport::montoCobro($ordenPos);
            if ($montoPos > 0.0001 && is_array($orden['payment'] ?? null)) {
                $orden['payment']['total_fee'] = $ordenPos['payment']['total_fee'] ?? $montoPos;
            }
        }

        $orden['payment_fuente'] = 'getOrdersPOS';

        return $orden;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @param  array<int, array<string, mixed>>  $mapPos
     * @return array<int, array<string, mixed>>
     */
    public static function enriquecerMapaOrdenes(array $ordenesPorId, array $mapPos): array
    {
        $out = [];
        foreach ($ordenesPorId as $id => $orden) {
            $id = (int) $id;
            if (isset($mapPos[$id])) {
                if (self::ordenRequiereEnriquecimientoPayment($orden)) {
                    $orden = self::fusionarPaymentDesdePos($orden, $mapPos[$id]);
                } else {
                    $orden = self::fusionarAccesoDesdePos($orden, $mapPos[$id]);
                }
            }
            $out[$id] = $orden;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{ordenes: array<int, array<string, mixed>>, map_pos: array<int, array<string, mixed>>}
     */
    public static function enriquecerDesdePos(
        WaitryOrdenesExternasService $ordenesExternasService,
        int $empresaId,
        array $ordenesPorId,
        string $fechaJornada,
        mixed $aperturaEn,
        mixed $cierreEn,
        ?array $mapPosPrecargado = null,
    ): array {
        if ($ordenesPorId === []) {
            return ['ordenes' => $ordenesPorId, 'map_pos' => $mapPosPrecargado ?? []];
        }

        $requieren = [];
        foreach ($ordenesPorId as $id => $orden) {
            if (self::ordenRequiereEnriquecimientoPayment($orden)) {
                $requieren[] = (int) $id;
            }
        }

        if ($requieren === []) {
            return ['ordenes' => $ordenesPorId, 'map_pos' => $mapPosPrecargado ?? []];
        }

        $mapPos = $mapPosPrecargado ?? $ordenesExternasService->mapOrdenesPosEnVentanaJornada(
            $empresaId,
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        );

        $enriquecidas = self::enriquecerMapaOrdenes($ordenesPorId, $mapPos);

        $limiteIndividual = max(0, (int) config('gastronomia.cierre_totem_enriquecer_payment_individual_max', 0));
        if ($limiteIndividual > 0) {
            $individuales = 0;
            foreach ($requieren as $orderId) {
                if (self::ordenTieneTipoPago($enriquecidas[$orderId] ?? [])) {
                    continue;
                }
                if ($individuales >= $limiteIndividual) {
                    break;
                }

                $ordenPos = $ordenesExternasService->obtenerOrdenPorIdConciliacion($empresaId, $orderId);
                if ($ordenPos === null) {
                    continue;
                }

                $enriquecidas[$orderId] = self::fusionarPaymentDesdePos(
                    $enriquecidas[$orderId] ?? $ordenesPorId[$orderId],
                    $ordenPos,
                );
                $individuales++;
            }
        }

        return ['ordenes' => $enriquecidas, 'map_pos' => $mapPos];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapPos
     * @param  list<int>  $orderIds
     * @return array<int, array<string, mixed>>
     */
    public static function filtrarMapPosPorIds(array $mapPos, array $orderIds): array
    {
        $out = [];
        foreach ($orderIds as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($mapPos[$id])) {
                $out[$id] = $mapPos[$id];
            }
        }

        return $out;
    }
}
