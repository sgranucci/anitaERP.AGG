<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use Illuminate\Support\Facades\DB;

/**
 * Une una venta de un punto de venta de local (Facturante, Tiendanube, Mercado Libre)
 * con Facturas Local. Sin turno: no entra al cierre de turno.
 */
final class FacturacionLocalEmisionVinculoSupport
{
    /**
     * @return array<int, int> puntoventa_id => local_venta_id
     */
    public static function mapaLocalPorPuntoventa(): array
    {
        $map = [];
        $pivot = DB::table('local_venta_puntoventa')
            ->orderBy('es_default')
            ->get(['puntoventa_id', 'local_venta_id', 'es_default']);
        foreach ($pivot as $row) {
            $pvId = (int) $row->puntoventa_id;
            if ($pvId > 0) {
                $map[$pvId] = (int) $row->local_venta_id;
            }
        }

        $locales = LocalVenta::query()->get(['id', 'puntoventa_id']);
        foreach ($locales as $local) {
            $pvId = (int) ($local->puntoventa_id ?: 0);
            if ($pvId > 0) {
                $map[$pvId] = (int) $local->id;
            }
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    public static function puntoventaIds(?int $localId): array
    {
        $map = self::mapaLocalPorPuntoventa();
        if ($localId === null || $localId <= 0) {
            return array_values(array_map('intval', array_keys($map)));
        }

        $ids = [];
        foreach ($map as $pvId => $lid) {
            if ((int) $lid === $localId) {
                $ids[] = (int) $pvId;
            }
        }

        return $ids;
    }

    /**
     * Punto de venta de un local: la factura sale del depósito y la nota de crédito entra.
     * El FAC de fábrica sigue en sin operación (el stock sale en el picking).
     */
    public static function operacionStockForzada(int $puntoventaId, object $tipotransaccion): ?string
    {
        if ($puntoventaId <= 0 || ! isset(self::mapaLocalPorPuntoventa()[$puntoventaId])) {
            return null;
        }

        $esNc = method_exists($tipotransaccion, 'esNotaCredito')
            ? $tipotransaccion->esNotaCredito()
            : (($tipotransaccion->operacion ?? '') === 'C');

        return $esNc
            ? TipotransaccionOperacionStockSupport::ENTRADA
            : TipotransaccionOperacionStockSupport::SALIDA;
    }

    public static function depositoIdPorPuntoventa(int $puntoventaId): int
    {
        $localId = self::mapaLocalPorPuntoventa()[$puntoventaId] ?? 0;
        if ($localId <= 0) {
            return 0;
        }

        return (int) (LocalVenta::query()->whereKey($localId)->value('deposito_id') ?: 0);
    }

    public static function vincularVenta(int $ventaId, string $origen): ?FacturacionLocalEmision
    {
        if ($ventaId <= 0) {
            return null;
        }

        $existente = FacturacionLocalEmision::query()
            ->where('venta_id', $ventaId)
            ->orWhere('venta_nc_id', $ventaId)
            ->orderByRaw('CASE WHEN venta_id = ? THEN 0 ELSE 1 END', [$ventaId])
            ->first();
        if ($existente) {
            return $existente;
        }

        $facturaId = FacturacionLocalNotasCreditoFacturaSupport::facturaIdDeNota($ventaId);
        if ($facturaId) {
            $deNota = FacturacionLocalEmision::query()->where('venta_id', $facturaId)->first();
            if ($deNota) {
                return $deNota;
            }
        }

        $puntoventaId = (int) (Venta::query()->whereKey($ventaId)->value('puntoventa_id') ?: 0);
        $localId = self::mapaLocalPorPuntoventa()[$puntoventaId] ?? 0;
        if ($localId <= 0) {
            return null;
        }

        return FacturacionLocalEmision::query()->firstOrCreate(
            ['venta_id' => $ventaId],
            [
                'local_venta_id' => $localId,
                'turno_operativo_local_id' => null,
                'venta_nc_id' => null,
                'vale_cliente_local_id' => null,
                'es_ticket_regalo' => false,
                'payload_resumen_json' => [
                    'origen' => $origen,
                    'vinculo_consulta' => true,
                ],
            ]
        );
    }

    /**
     * Fila en memoria para ver/imprimir un comprobante del punto de venta del local
     * que todavía no tiene emisión (importaciones anteriores al vínculo).
     */
    public static function emisionVirtual(int $ventaId): ?FacturacionLocalEmision
    {
        $venta = Venta::query()->find($ventaId);
        if (! $venta) {
            return null;
        }

        $localId = self::mapaLocalPorPuntoventa()[(int) $venta->puntoventa_id] ?? 0;
        if ($localId <= 0) {
            return null;
        }

        $local = LocalVenta::query()->find($localId);
        $emision = new FacturacionLocalEmision([
            'local_venta_id' => $localId,
            'turno_operativo_local_id' => null,
            'venta_id' => (int) $venta->id,
            'venta_nc_id' => null,
            'vale_cliente_local_id' => null,
            'es_ticket_regalo' => false,
        ]);
        $emision->setRelation('venta', $venta);
        $emision->setRelation('localVenta', $local);
        $emision->setRelation('turno', null);
        $emision->setRelation('ventaNc', null);

        return $emision;
    }
}
