<?php

namespace App\Support\Ventas\Gastronomia;

/**
 * Clasificación atómica de comandas Waitry sin facturar (grupo sin_facturar_qr).
 *
 * Regla: si el plan post-redistribución tiene QR o MP → factura con total completo;
 * si quedó 100 % efectivo → ajuste de stock (comanda entera).
 */
final class CierreJornadaProcesoFacturaComandasSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosGrupoSinFacturar(array $movimientos): array
    {
        return array_values(array_filter(
            $movimientos,
            static fn (array $mov) => ($mov['grupo'] ?? '') === CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosFacturacion(array $movimientos): array
    {
        return array_values(array_filter(
            self::movimientosGrupoSinFacturar($movimientos),
            static fn (array $mov) => self::comandaVaAFacturacion($mov),
        ));
    }

    /**
     * Comandas 100 % efectivo en el plan (ajuste de insumos / compensación contable).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosAjusteInsumos(array $movimientos): array
    {
        return array_values(array_filter(
            self::movimientosGrupoSinFacturar($movimientos),
            static fn (array $mov) => self::comandaVaAAjusteStock($mov),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{facturar:list<array<string,mixed>>,ajuste:list<array<string,mixed>>}
     */
    public static function clasificar(array $movimientos): array
    {
        $grupo = self::movimientosGrupoSinFacturar($movimientos);
        $facturar = [];
        $ajuste = [];

        foreach ($grupo as $mov) {
            if (self::comandaVaAFacturacion($mov)) {
                $facturar[] = $mov;
            } elseif (self::comandaVaAAjusteStock($mov)) {
                $ajuste[] = $mov;
            }
        }

        return ['facturar' => $facturar, 'ajuste' => $ajuste];
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function comandaVaAFacturacion(array $mov): bool
    {
        if (($mov['grupo'] ?? '') !== CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
            return false;
        }

        $clavesFacturables = CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar();
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            foreach ($plan as $parte) {
                if (in_array($parte['clave'] ?? '', $clavesFacturables, true)
                    && (float) ($parte['monto'] ?? 0) > 0.0001) {
                    return true;
                }
            }

            return false;
        }

        $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_waitry_clave'] ?? '');

        return in_array($clave, $clavesFacturables, true)
            || $clave === ''
            || $clave === CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function comandaVaAAjusteStock(array $mov): bool
    {
        if (($mov['grupo'] ?? '') !== CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
            return false;
        }

        if (self::comandaVaAFacturacion($mov)) {
            return false;
        }

        return self::esComandaSoloEfectivoTrasPlan($mov);
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function esComandaSoloEfectivoTrasPlan(array $mov): bool
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (! is_array($plan) || $plan === []) {
            $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_waitry_clave'] ?? '');

            return $clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
        }

        $clavesFacturables = CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar();
        $tieneEfectivo = false;

        foreach ($plan as $parte) {
            $clave = (string) ($parte['clave'] ?? '');
            $monto = round((float) ($parte['monto'] ?? 0), 2);
            if ($monto <= 0.0001 || $clave === CierreJornadaProcesoMedioSupport::CLAVE_TOTEM) {
                continue;
            }
            if (in_array($clave, $clavesFacturables, true)) {
                return false;
            }
            if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                $tieneEfectivo = true;

                continue;
            }

            return false;
        }

        return $tieneEfectivo;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function montoComandaCompleto(array $mov): float
    {
        return round((float) ($mov['total'] ?? 0), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     */
    public static function totalComandas(array $comandas): float
    {
        return round(array_sum(array_map(
            static fn (array $mov) => self::montoComandaCompleto($mov),
            $comandas,
        )), 2);
    }

    /**
     * Referencias auditables de comandas Waitry incluidas en una factura del proceso (puede ser > 1).
     *
     * @param  list<array<string, mixed>>  $comandas
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return list<array{
     *   waitry_order_id:int,
     *   display_id:?string,
     *   referencia_waitry:?string,
     *   total:float,
     *   medio_waitry_clave:?string,
     *   placed_at:?string
     * }>
     */
    public static function referenciasComandasParaPersistencia(array $comandas, array $ordenesPorId = []): array
    {
        $out = [];

        foreach ($comandas as $mov) {
            $orderId = (int) ($mov['waitry_order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $orden = $ordenesPorId[$orderId] ?? null;
            $displayId = trim((string) ($mov['display_id'] ?? ''));
            if ($displayId === '' && is_array($orden)) {
                $displayId = trim((string) ($orden['displayId'] ?? $orden['display_id'] ?? ''));
            }

            $referencia = trim((string) ($mov['referencia_waitry'] ?? ''));
            if ($referencia === '' && is_array($orden)) {
                $referencia = trim((string) ($orden['reference'] ?? $orden['referencia'] ?? ''));
            }

            $placedAt = $mov['placed_at'] ?? $mov['fecha_hora_raw'] ?? null;
            if (($placedAt === null || $placedAt === '') && is_array($orden)) {
                $placedAt = $orden['placedAt'] ?? $orden['placed_at'] ?? null;
            }

            $out[] = [
                'waitry_order_id' => $orderId,
                'display_id' => $displayId !== '' ? $displayId : null,
                'referencia_waitry' => $referencia !== '' ? $referencia : null,
                'total' => self::montoComandaCompleto($mov),
                'medio_waitry_clave' => isset($mov['medio_waitry_clave'])
                    ? (string) $mov['medio_waitry_clave']
                    : null,
                'placed_at' => is_string($placedAt) && $placedAt !== '' ? $placedAt : null,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['waitry_order_id'] <=> $b['waitry_order_id']);

        return $out;
    }
}
