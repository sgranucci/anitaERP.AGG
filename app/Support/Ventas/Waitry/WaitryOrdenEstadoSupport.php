<?php

namespace App\Support\Ventas\Waitry;

/**
 * Estado operativo de órdenes Waitry (getordersdetails / getOrdersPOS).
 */
final class WaitryOrdenEstadoSupport
{
    private const TOLERANCIA_NETO = 0.0001;

    /** @var list<string> */
    private const ESTADOS_TEXTO_CANCELADOS = [
        'cancelled',
        'canceled',
        'cancelada',
        'cancelado',
        'rejected',
        'rechazada',
        'rechazado',
        'void',
        'voided',
        'anulada',
        'anulado',
        'deleted',
        'eliminada',
        'aborted',
    ];

    /** @var list<string> */
    private const FRAGMENTOS_ESTADO_CANCELADO = [
        'cancel',
        'anul',
        'void',
        'rechaz',
        'reject',
        'abort',
        'elimin',
        'delet',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{
     *   activas: array<int, array<string, mixed>>,
     *   cantidad_excluidas: int,
     *   cantidad_anuladas_descuento_excluidas: int,
     *   waitry_anuladas_descuento: array{cantidad:int,total:float}
     * }
     */
    public static function filtrarOrdenesActivas(array $ordenesPorId): array
    {
        $activas = [];
        $excluidasCanceladas = 0;
        $anuladasDescuento = [];

        foreach ($ordenesPorId as $orderId => $orden) {
            if (self::esCancelada($orden)) {
                $excluidasCanceladas++;

                continue;
            }
            if (self::esAnuladaPorDescuentoTotal($orden)) {
                $anuladasDescuento[] = [
                    'waitry_order_id' => (int) $orderId,
                    'total' => self::montoBrutoWaitry($orden),
                ];

                continue;
            }
            $activas[(int) $orderId] = $orden;
        }

        return [
            'activas' => $activas,
            'cantidad_excluidas' => $excluidasCanceladas,
            'cantidad_anuladas_descuento_excluidas' => count($anuladasDescuento),
            'waitry_anuladas_descuento' => self::resumenLineas($anuladasDescuento),
        ];
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function esCancelada(array $orden): bool
    {
        foreach (['canceled', 'cancelada', 'cancelled', 'isCanceled', 'is_cancelled'] as $clave) {
            if (! array_key_exists($clave, $orden)) {
                continue;
            }
            if (in_array($orden[$clave], [true, 1, '1'], true)) {
                return true;
            }
        }

        foreach (['cancelledAt', 'canceledAt', 'cancelled_at', 'canceled_at', 'annulled_at', 'voided_at'] as $clave) {
            if (! empty($orden[$clave])) {
                return true;
            }
        }

        foreach (['current_state', 'state', 'status', 'orderStatus', 'order_status', 'currentState'] as $campo) {
            if (! isset($orden[$campo]) || ! is_scalar($orden[$campo])) {
                continue;
            }
            if (self::textoEstadoIndicaCancelacion((string) $orden[$campo])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Orden cerrada en kiosco con descuento total (neto $0) sin cobro: no es cancelada explícita
     * pero no debe entrar a impagos ni totales operativos.
     *
     * @param  array<string, mixed>  $orden
     */
    public static function esAnuladaPorDescuentoTotal(array $orden): bool
    {
        if (self::esCancelada($orden)) {
            return false;
        }

        if (WaitryOrdenCobroSupport::cobradaEnTotem($orden)) {
            return false;
        }

        $bruto = self::montoBrutoWaitry($orden);
        if ($bruto <= self::TOLERANCIA_NETO) {
            return false;
        }

        return self::montoNetoOperativo($orden) <= self::TOLERANCIA_NETO;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function esCanceladaLinea(array $linea): bool
    {
        if (! empty($linea['waitry_cancelada'])) {
            return true;
        }

        return self::esCancelada($linea);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function esAnuladaPorDescuentoTotalLinea(array $linea): bool
    {
        if (! empty($linea['waitry_anulada_descuento'])) {
            return true;
        }

        if (self::esCanceladaLinea($linea)) {
            return false;
        }

        if (WaitryOrdenCobroSupport::cobradaEnTotem($linea)) {
            return false;
        }

        $bruto = round((float) ($linea['total'] ?? self::montoBrutoWaitry($linea)), 2);
        if ($bruto <= self::TOLERANCIA_NETO) {
            return false;
        }

        if (array_key_exists('total_neto_waitry', $linea)) {
            return round((float) $linea['total_neto_waitry'], 2) <= self::TOLERANCIA_NETO;
        }

        return self::esAnuladaPorDescuentoTotal($linea);
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function montoBrutoWaitry(array $orden): float
    {
        if (isset($orden['totalAmount']) && is_numeric($orden['totalAmount'])) {
            return round((float) $orden['totalAmount'], 2);
        }

        if (isset($orden['total_amount_waitry']) && is_numeric($orden['total_amount_waitry'])) {
            return round((float) $orden['total_amount_waitry'], 2);
        }

        if (isset($orden['total']) && is_numeric($orden['total']) && ! isset($orden['total_neto_waitry'])) {
            return round((float) $orden['total'], 2);
        }

        $cart = $orden['cart'] ?? null;
        if (is_array($cart)) {
            $items = $cart['items'] ?? [];
            if (is_array($items) && $items !== []) {
                $suma = 0.0;
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $price = $item['price'] ?? [];
                    if (! is_array($price)) {
                        continue;
                    }
                    $totalPrice = $price['total_price']['amount'] ?? $price['unit_price']['amount'] ?? null;
                    if (! is_numeric($totalPrice)) {
                        continue;
                    }
                    $qty = max(1, (int) ($item['quantity'] ?? $item['count'] ?? 1));
                    $suma += (float) $totalPrice * $qty;
                }
                if ($suma > self::TOLERANCIA_NETO) {
                    return round($suma, 2);
                }
            }
        }

        $charges = $orden['charges'] ?? null;
        if (is_array($charges)) {
            foreach ($charges as $charge) {
                if (is_array($charge) && isset($charge['amount']) && is_numeric($charge['amount'])) {
                    return round((float) $charge['amount'], 2);
                }
            }
        }

        return round((float) ($orden['total'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function montoDescuentoWaitry(array $orden): float
    {
        if (isset($orden['totalDiscount']) && is_numeric($orden['totalDiscount'])) {
            return round((float) $orden['totalDiscount'], 2);
        }

        if (isset($orden['total_discount_waitry']) && is_numeric($orden['total_discount_waitry'])) {
            return round((float) $orden['total_discount_waitry'], 2);
        }

        if (isset($orden['total_discount']) && is_numeric($orden['total_discount'])) {
            return round((float) $orden['total_discount'], 2);
        }

        $descuentoCart = self::descuentoDesdeCart($orden);
        if ($descuentoCart !== null) {
            return $descuentoCart;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function montoNetoOperativo(array $orden): float
    {
        $bruto = self::montoBrutoWaitry($orden);
        $descuento = self::montoDescuentoWaitry($orden);

        return round(max(0.0, $bruto - $descuento), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   activas: list<array<string, mixed>>,
     *   canceladas: list<array<string, mixed>>,
     *   anuladas_descuento: list<array<string, mixed>>,
     *   resumen: array{cantidad:int,total:float},
     *   resumen_anuladas_descuento: array{cantidad:int,total:float}
     * }
     */
    public static function separarCanceladas(array $lineas): array
    {
        $activas = [];
        $canceladas = [];
        $anuladasDescuento = [];

        foreach ($lineas as $linea) {
            if (self::esCanceladaLinea($linea)) {
                $canceladas[] = $linea;
            } elseif (self::esAnuladaPorDescuentoTotalLinea($linea)) {
                $anuladasDescuento[] = $linea;
            } else {
                $activas[] = $linea;
            }
        }

        return [
            'activas' => $activas,
            'canceladas' => $canceladas,
            'anuladas_descuento' => $anuladasDescuento,
            'resumen' => self::resumenLineas($canceladas),
            'resumen_anuladas_descuento' => self::resumenLineas($anuladasDescuento),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $canceladas
     * @return array{cantidad:int,total:float}
     */
    public static function resumenCanceladas(array $canceladas): array
    {
        return self::resumenLineas($canceladas);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{cantidad:int,total:float}
     */
    public static function resumenLineas(array $lineas): array
    {
        $total = 0.0;
        foreach ($lineas as $linea) {
            $total = round($total + (float) ($linea['total'] ?? self::montoBrutoWaitry($linea)), 2);
        }

        return [
            'cantidad' => count($lineas),
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public static function lineasRequierenEnriquecimientoDescuento(array $lineas): bool
    {
        foreach ($lineas as $linea) {
            if (self::lineaImpagoSinMetadatosDescuento($linea)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Línea impaga en snapshot legacy sin metadatos de descuento Waitry.
     *
     * @param  array<string, mixed>  $linea
     */
    public static function lineaImpagoSinMetadatosDescuento(array $linea): bool
    {
        if (! empty($linea['facturada_erp']) || ! empty($linea['discrepancia_gap'])) {
            return false;
        }

        if (WaitryOrdenCobroSupport::cobradaEnTotem($linea)) {
            return false;
        }

        if (round((float) ($linea['total'] ?? 0), 2) <= self::TOLERANCIA_NETO) {
            return false;
        }

        if (array_key_exists('waitry_anulada_descuento', $linea)
            && array_key_exists('total_discount_waitry', $linea)) {
            return false;
        }

        return ($linea['paid_waitry'] ?? null) === false
            || (float) ($linea['monto_cobro_waitry'] ?? 0) <= self::TOLERANCIA_NETO;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    public static function aplicarMetadatosOrdenEnLinea(array $linea, array $orden): array
    {
        $linea['total_amount_waitry'] = self::montoBrutoWaitry($orden);
        $linea['total_discount_waitry'] = self::montoDescuentoWaitry($orden);
        $linea['total_neto_waitry'] = self::montoNetoOperativo($orden);
        $linea['waitry_anulada_descuento'] = self::esAnuladaPorDescuentoTotal($orden);

        return $linea;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerLineasImpagasConOrdenes(array $lineas, array $ordenesPorId): array
    {
        if ($ordenesPorId === []) {
            return $lineas;
        }

        foreach ($lineas as $indice => $linea) {
            if (! self::lineaImpagoSinMetadatosDescuento($linea)) {
                continue;
            }

            $orderId = (int) ($linea['waitry_order_id'] ?? 0);
            if ($orderId <= 0 || ! isset($ordenesPorId[$orderId])) {
                continue;
            }

            $orden = $ordenesPorId[$orderId];
            if (! is_array($orden)) {
                continue;
            }

            $lineas[$indice] = self::aplicarMetadatosOrdenEnLinea($linea, $orden);
        }

        return $lineas;
    }

    private static function textoEstadoIndicaCancelacion(string $estado): bool
    {
        $texto = mb_strtolower(trim($estado));
        if ($texto === '') {
            return false;
        }

        if (in_array($texto, self::ESTADOS_TEXTO_CANCELADOS, true)) {
            return true;
        }

        foreach (self::FRAGMENTOS_ESTADO_CANCELADO as $fragmento) {
            if (str_contains($texto, $fragmento)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private static function descuentoDesdeCart(array $orden): ?float
    {
        $cart = $orden['cart'] ?? null;
        if (! is_array($cart)) {
            return null;
        }

        $items = $cart['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        $descuento = 0.0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $price = $item['price'] ?? [];
            if (! is_array($price)) {
                continue;
            }
            $totalDiscount = $price['total_discount']['amount'] ?? $price['discount']['amount'] ?? null;
            if (is_numeric($totalDiscount)) {
                $descuento += (float) $totalDiscount;
            }
        }

        return round($descuento, 2);
    }
}
