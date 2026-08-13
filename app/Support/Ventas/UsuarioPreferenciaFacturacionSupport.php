<?php

namespace App\Support\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de comprobante y puntos de venta recordados por usuario.
 * Se configuran al facturar/remitir y quedan hasta que el usuario los cambie.
 */
final class UsuarioPreferenciaFacturacionSupport
{
    public const COL_TIPO = 'tipotransaccion_venta_id';

    public const COL_PV = 'puntoventa_id';

    public const COL_PV_REMITO = 'puntoventaremito_id';

    /**
     * @return array{tipotransaccion_id: int|null, puntoventa_id: int|null, puntoventaremito_id: int|null}
     */
    public static function leer(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?: (int) (auth()->id() ?: 0);
        $desdeUsuario = [
            'tipotransaccion_id' => null,
            'puntoventa_id' => null,
            'puntoventaremito_id' => null,
        ];

        if ($usuarioId > 0 && Schema::hasColumn('usuario', self::COL_TIPO)) {
            $usuario = Usuario::query()->whereKey($usuarioId)->first([
                'id',
                self::COL_TIPO,
                self::COL_PV,
                self::COL_PV_REMITO,
            ]);
            if ($usuario) {
                $desdeUsuario['tipotransaccion_id'] = self::idPositivo($usuario->{self::COL_TIPO} ?? null);
                $desdeUsuario['puntoventa_id'] = self::idPositivo($usuario->{self::COL_PV} ?? null);
                $desdeUsuario['puntoventaremito_id'] = self::idPositivo($usuario->{self::COL_PV_REMITO} ?? null);
            }
        }

        $tipoCache = self::idPositivo(Cache::get(generaKey('tipotransaccion')));
        $pvCache = self::idPositivo(Cache::get(generaKey('puntoventa')));
        $pvRemitoCache = self::idPositivo(Cache::get(generaKey('puntoventaremito')));

        $desdeCache = [];
        if (! $desdeUsuario['tipotransaccion_id'] && $tipoCache) {
            $desdeCache['tipotransaccion_id'] = $tipoCache;
        }
        if (! $desdeUsuario['puntoventa_id'] && $pvCache) {
            $desdeCache['puntoventa_id'] = $pvCache;
        }
        if (! $desdeUsuario['puntoventaremito_id'] && $pvRemitoCache) {
            $desdeCache['puntoventaremito_id'] = $pvRemitoCache;
        }
        if ($usuarioId > 0 && $desdeCache !== []) {
            self::guardar($desdeCache, $usuarioId);
        }

        return [
            'tipotransaccion_id' => $desdeUsuario['tipotransaccion_id'] ?? $tipoCache,
            'puntoventa_id' => $desdeUsuario['puntoventa_id'] ?? $pvCache ?? self::defaultPuntoventaFacturacion(),
            'puntoventaremito_id' => $desdeUsuario['puntoventaremito_id'] ?? $pvRemitoCache ?? self::defaultPuntoventaRemito(),
        ];
    }

    /**
     * @param  array{tipotransaccion_id?: mixed, puntoventa_id?: mixed, puntoventaremito_id?: mixed}  $data
     */
    public static function guardar(array $data, ?int $usuarioId = null): void
    {
        $usuarioId = $usuarioId ?: (int) (auth()->id() ?: 0);
        if ($usuarioId <= 0) {
            return;
        }

        $tipo = self::idPositivo($data['tipotransaccion_id'] ?? null);
        $pv = self::idPositivo($data['puntoventa_id'] ?? null);
        $pvRemito = self::idPositivo($data['puntoventaremito_id'] ?? null);

        if ($tipo && ! Tipotransaccion::query()->whereKey($tipo)->exists()) {
            $tipo = null;
        }
        if ($pv && ! Puntoventa::query()->whereKey($pv)->exists()) {
            $pv = null;
        }
        if ($pvRemito && ! Puntoventa::query()->whereKey($pvRemito)->exists()) {
            $pvRemito = null;
        }

        $update = [];
        if ($tipo) {
            $update[self::COL_TIPO] = $tipo;
            Cache::forever(generaKey('tipotransaccion'), $tipo);
        }
        if ($pv) {
            $update[self::COL_PV] = $pv;
            Cache::forever(generaKey('puntoventa'), $pv);
        }
        if ($pvRemito) {
            $update[self::COL_PV_REMITO] = $pvRemito;
            Cache::forever(generaKey('puntoventaremito'), $pvRemito);
        }

        if ($update === [] || ! Schema::hasColumn('usuario', self::COL_TIPO)) {
            return;
        }

        Usuario::query()->whereKey($usuarioId)->update($update);
    }

    private static function idPositivo(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private static function defaultPuntoventaFacturacion(): ?int
    {
        $v = config('facturacion.PUNTOVENTA_FACTURACION');
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    private static function defaultPuntoventaRemito(): ?int
    {
        $v = config('facturacion.PUNTOVENTA_REMITO');
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }
}
