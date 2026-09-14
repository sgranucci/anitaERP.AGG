<?php

namespace App\Support\Ventas;

use App\Models\Contable\Asiento;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Remito_Articulo;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaAnitaReplica;
use App\Services\Ventas\FacturacionService;
use App\Support\Contable\AsientoEloquentDeleteSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;
use Illuminate\Support\Facades\DB;

/**
 * Borrado reutilizable de ventas de prueba: ERP (+ Anita opcional) y remitos opcionales.
 *
 * Orden entre ventas: primero hijas (venta_origen_id), después orígenes.
 */
final class VentaPruebaBorradoSupport
{
    /**
     * @param  list<int|string>  $ventaIds
     * @return list<int>
     */
    public static function normalizarIds(array $ventaIds): array
    {
        $ids = [];
        foreach ($ventaIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int|string>  $codigosPv  ej. [8, 15] o ['00008','00015']
     * @return list<int>
     */
    public static function resolverIds(?string $fecha, array $codigosPv = [], array $ventaIds = []): array
    {
        $idsExplicitos = self::normalizarIds($ventaIds);
        if ($idsExplicitos !== []) {
            return $idsExplicitos;
        }

        if ($fecha === null || trim($fecha) === '') {
            return [];
        }

        $q = DB::table('venta as v')
            ->whereDate('v.fecha', $fecha);

        $codigos = self::normalizarCodigosPuntoventa($codigosPv);
        if ($codigos !== []) {
            $q->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
                ->whereIn('pv.codigo', $codigos);
        }

        return $q->orderBy('v.id')->pluck('v.id')->map(static fn ($id) => (int) $id)->all();
    }

    /**
     * Hijas (con venta_origen_id dentro del set) primero, para no chocar FK.
     *
     * @param  list<int>  $ventaIds
     * @return list<int>
     */
    public static function ordenarParaBorrado(array $ventaIds): array
    {
        $ids = self::normalizarIds($ventaIds);
        if ($ids === []) {
            return [];
        }

        $origenPorId = DB::table('venta')
            ->whereIn('id', $ids)
            ->pluck('venta_origen_id', 'id');

        $set = array_fill_keys($ids, true);
        usort($ids, static function (int $a, int $b) use ($origenPorId, $set): int {
            $aOrigen = (int) ($origenPorId[$a] ?? 0);
            $bOrigen = (int) ($origenPorId[$b] ?? 0);
            $aHija = $aOrigen > 0 && isset($set[$aOrigen]);
            $bHija = $bOrigen > 0 && isset($set[$bOrigen]);
            if ($aHija !== $bHija) {
                return $aHija ? -1 : 1;
            }

            return $a <=> $b;
        });

        return $ids;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<string, int>
     */
    public static function contarHijas(array $ventaIds, bool $incluirRemitos = true): array
    {
        $ventaIds = self::normalizarIds($ventaIds);
        if ($ventaIds === []) {
            return [
                'venta' => 0,
                'venta_impuesto' => 0,
                'venta_emision' => 0,
                'cliente_cuentacorriente' => 0,
                'asiento' => 0,
                'asiento_movimiento' => 0,
                'articulo_movimiento' => 0,
                'venta_anita_replica' => 0,
                'remito' => 0,
                'remito_articulo' => 0,
            ];
        }

        $asientoIds = DB::table('asiento')->whereIn('venta_id', $ventaIds)->pluck('id');
        $remitoIds = $incluirRemitos ? self::remitoIdsDeVentas($ventaIds) : [];

        return [
            'venta' => count($ventaIds),
            'venta_impuesto' => (int) DB::table('venta_impuesto')->whereIn('venta_id', $ventaIds)->count(),
            'venta_emision' => (int) DB::table('venta_emision')->whereIn('venta_id', $ventaIds)->count(),
            'cliente_cuentacorriente' => (int) DB::table('cliente_cuentacorriente')->whereIn('venta_id', $ventaIds)->count(),
            'asiento' => $asientoIds->count(),
            'asiento_movimiento' => $asientoIds->isEmpty()
                ? 0
                : (int) DB::table('asiento_movimiento')->whereIn('asiento_id', $asientoIds)->count(),
            'articulo_movimiento' => (int) DB::table('articulo_movimiento')->whereIn('venta_id', $ventaIds)->count(),
            'venta_anita_replica' => (int) DB::table('venta_anita_replica')->whereIn('venta_id', $ventaIds)->count(),
            'remito' => count($remitoIds),
            'remito_articulo' => $remitoIds === []
                ? 0
                : (int) DB::table('remito_articulo')->whereIn('remito_id', $remitoIds)->count(),
        ];
    }

    /**
     * @param  list<int>  $ventaIds
     * @return list<array<string, mixed>>
     */
    public static function listarResumen(array $ventaIds): array
    {
        $ventaIds = self::normalizarIds($ventaIds);
        if ($ventaIds === []) {
            return [];
        }

        return DB::table('venta as v')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->whereIn('v.id', $ventaIds)
            ->orderBy('pv.codigo')
            ->orderBy('v.numerocomprobante')
            ->get([
                'v.id',
                'v.fecha',
                'v.codigo',
                'v.nombre as cliente',
                'v.total',
                'v.cae',
                'v.remito_id',
                'v.venta_origen_id',
                'pv.codigo as pv',
                'tt.abreviatura as tipo',
            ])
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function eliminarUna(int $ventaId, bool $tambienAnita = false, bool $tambienRemitos = false): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        if ($tambienAnita) {
            app(FacturacionService::class)->borraAnitaDesdeVenta($venta, false);
        }

        EloquentAuditDeleteSupport::each(
            VentaAnitaReplica::query()->where('venta_id', $ventaId)
        );

        ArticuloMovimientoEliminacionSupport::eliminarPorVentaId($ventaId);

        $asientoIds = Asiento::query()->where('venta_id', $ventaId)->pluck('id');
        foreach ($asientoIds as $asientoId) {
            AsientoEloquentDeleteSupport::eliminarPorId((int) $asientoId);
        }

        if ($tambienRemitos) {
            self::eliminarRemitosDeVenta($venta);
            $venta = Venta::query()->find($ventaId);
            if ($venta === null) {
                return;
            }
        }

        $venta->delete();
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array{ok: list<int>, fallidas: array<int, string>}
     */
    public static function eliminarVarias(array $ventaIds, bool $tambienAnita = false, bool $tambienRemitos = false): array
    {
        $ok = [];
        $fallidas = [];

        foreach (self::ordenarParaBorrado($ventaIds) as $id) {
            try {
                DB::transaction(static function () use ($id, $tambienAnita, $tambienRemitos): void {
                    self::eliminarUna($id, $tambienAnita, $tambienRemitos);
                });
                $ok[] = $id;
            } catch (\Throwable $e) {
                $fallidas[$id] = $e->getMessage();
            }
        }

        return ['ok' => $ok, 'fallidas' => $fallidas];
    }

    /**
     * @param  list<int|string>  $codigosPv
     * @return list<string>
     */
    private static function normalizarCodigosPuntoventa(array $codigosPv): array
    {
        $out = [];
        foreach ($codigosPv as $codigo) {
            $raw = trim((string) $codigo);
            if ($raw === '') {
                continue;
            }
            if (ctype_digit($raw)) {
                $out[] = str_pad($raw, 5, '0', STR_PAD_LEFT);
            } else {
                $out[] = $raw;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<int>  $ventaIds
     * @return list<int>
     */
    private static function remitoIdsDeVentas(array $ventaIds): array
    {
        $desdeVenta = DB::table('venta')
            ->whereIn('id', $ventaIds)
            ->whereNotNull('remito_id')
            ->pluck('remito_id');

        $desdeRemito = DB::table('remito')
            ->whereIn('venta_id', $ventaIds)
            ->pluck('id');

        return $desdeVenta->merge($desdeRemito)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private static function eliminarRemitosDeVenta(Venta $venta): void
    {
        $remitoIds = self::remitoIdsDeVentas([(int) $venta->id]);
        if ($remitoIds === []) {
            return;
        }

        if ($venta->remito_id) {
            $venta->remito_id = null;
            $venta->save();
        }

        foreach ($remitoIds as $remitoId) {
            EloquentAuditDeleteSupport::each(
                Remito_Articulo::query()->where('remito_id', $remitoId)
            );
            Remito::query()->find($remitoId)?->delete();
        }
    }
}
