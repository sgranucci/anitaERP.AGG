<?php

namespace App\Support\Ventas;

use App\Jobs\Ventas\ReplicarAnitaPedidoJob;
use App\Services\Ventas\PedidoFacturaAnitaRegrabacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Diferimiento de Anita en factura de pedido, remito y mostrador (El Bierzo).
 * ARCA (número + CAE) y el numerador de remito siguen síncronos.
 * Venta / vencae / ctamov van a cola Laravel (no Apache / terminating).
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

    public static function enColaHabilitado(): bool
    {
        if (! self::debeDiferir()) {
            return false;
        }

        if (! filter_var(config('facturacion.ANITA_PEDIDO_EN_COLA', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (config('queue.default') === 'sync') {
            return false;
        }

        return true;
    }

    /**
     * Programa Anita/vencae y saca esos payloads de la respuesta al browser.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function tomarYProgramar(array $item, string $contexto = 'pedido'): array
    {
        $lista = self::programarDesdeResultados([$item], $contexto);

        return $lista[0];
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     * @return list<array<string, mixed>>
     */
    public static function programarDesdeResultados(array $resultados, string $contexto = 'pedido'): array
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

            $contextoItem = $contexto;
            if ($contextoItem === 'pedido' && (int) ($item['remito_id'] ?? 0) > 0 && (int) ($item['pedido_id'] ?? 0) <= 0) {
                $contextoItem = 'remito';
            }

            self::programar(
                $ventaId,
                is_array($anita) ? $anita : null,
                is_array($vencae) ? $vencae : null,
                $contextoItem,
            );

            unset(
                $resultados[$i]['anita_pendiente'],
                $resultados[$i]['vencae_pendiente'],
            );
        }

        return $resultados;
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public static function programar(int $ventaId, ?array $anitaPendiente, ?array $vencaePendiente, string $contexto = 'factura'): void
    {
        if ($ventaId <= 0 && $anitaPendiente === null && $vencaePendiente === null) {
            return;
        }

        app(PedidoFacturaAnitaRegrabacionService::class)->registrarPendiente(
            $ventaId,
            $anitaPendiente,
            $vencaePendiente,
        );

        if (! self::enColaHabilitado()) {
            Log::warning('pedido.anita.cola.no_disponible', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
                'queue' => config('queue.default'),
                'en_cola' => filter_var(config('facturacion.ANITA_PEDIDO_EN_COLA', true), FILTER_VALIDATE_BOOLEAN),
            ]);

            return;
        }

        try {
            ReplicarAnitaPedidoJob::dispatch(
                $ventaId,
                $anitaPendiente,
                $vencaePendiente,
                $contexto,
            )->afterCommit();
        } catch (Throwable $e) {
            Log::warning('pedido.anita.cola.despacho_fallo', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
                'msg' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('pedido.anita.cola.despachado', [
            'venta_id' => $ventaId,
            'contexto' => $contexto,
            'cola' => config('facturacion.ANITA_PEDIDO_COLA', 'default'),
        ]);
    }
}
