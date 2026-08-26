<?php

namespace App\Support\Ventas;

use App\Services\Ventas\PedidoFacturaAnitaDeferEjecucionService;
use App\Services\Ventas\PedidoFacturaAnitaRegrabacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Diferimiento de Anita en factura de pedido (El Bierzo).
 * ARCA (número + CAE) sigue síncrono; Anita corre post-respuesta como gastronomía AGG.
 */
final class PedidoFacturaAnitaDeferSupport
{
    public static function debeDiferir(): bool
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return false;
        }

        return filter_var(config('facturacion.ANITA_TRAS_RESPUESTA_PEDIDO', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Programa Anita/vencae y saca esos payloads de la respuesta al browser.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function tomarYProgramar(array $item): array
    {
        $lista = self::programarDesdeResultados([$item]);

        return $lista[0];
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     * @return list<array<string, mixed>>
     */
    public static function programarDesdeResultados(array $resultados): array
    {
        if (! self::debeDiferir()) {
            return $resultados;
        }

        foreach ($resultados as $i => $item) {
            if (! is_array($item) || ! empty($item['error'])) {
                continue;
            }

            $anita = $item['anita_pendiente'] ?? null;
            $vencae = $item['vencae_pendiente'] ?? null;
            $ventaId = (int) ($item['venta_id'] ?? 0);
            if (! is_array($anita) && ! is_array($vencae)) {
                continue;
            }

            self::programar(
                $ventaId,
                is_array($anita) ? $anita : null,
                is_array($vencae) ? $vencae : null,
            );

            unset(
                $resultados[$i]['anita_pendiente'],
                $resultados[$i]['vencae_pendiente'],
                $resultados[$i]['venta_id'],
            );
        }

        return $resultados;
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public static function programar(int $ventaId, ?array $anitaPendiente, ?array $vencaePendiente): void
    {
        if ($ventaId <= 0 && $anitaPendiente === null && $vencaePendiente === null) {
            return;
        }

        app(PedidoFacturaAnitaRegrabacionService::class)->registrarPendiente(
            $ventaId,
            $anitaPendiente,
            $vencaePendiente,
        );

        app()->terminating(function () use ($ventaId, $anitaPendiente, $vencaePendiente): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            app(PedidoFacturaAnitaDeferEjecucionService::class)->ejecutar(
                $ventaId,
                $anitaPendiente,
                $vencaePendiente,
            );
        });
    }
}
