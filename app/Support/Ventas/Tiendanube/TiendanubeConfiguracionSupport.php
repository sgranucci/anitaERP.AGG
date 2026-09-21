<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubeConfiguracion;
use App\Models\Ventas\TiendanubeGatewayCuentacaja;
use App\Models\Ventas\TiendanubePuntoventaDeposito;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura/escritura de la configuración operativa Tiendanube (BD).
 * Prioridad: filas en BD → fallback config/tiendanube.php (.env).
 */
final class TiendanubeConfiguracionSupport
{
    public static function tablasListas(): bool
    {
        return Schema::hasTable('tiendanube_configuracion')
            && Schema::hasTable('tiendanube_gateway_cuentacaja')
            && Schema::hasTable('tiendanube_puntoventa_deposito');
    }

    public static function cabecera(): ?TiendanubeConfiguracion
    {
        if (! self::tablasListas()) {
            return null;
        }

        return TiendanubeConfiguracion::query()->orderBy('id')->first();
    }

    public static function asegurarCabecera(): TiendanubeConfiguracion
    {
        $cfg = self::cabecera();
        if ($cfg) {
            return $cfg;
        }

        return TiendanubeConfiguracion::query()->create([
            'empresa_id' => (int) config('tiendanube.empresa_id', 1) ?: null,
            'puntoventa_id' => TiendanubePedidoMaestrosSupport::puntoventaPorCodigo(
                (string) config('tiendanube.puntoventa_codigo_default')
            )?->id,
            'deposito_id' => TiendanubePedidoMaestrosSupport::depositoPorCodigo(
                (string) config('tiendanube.deposito_codigo_default')
            )?->id,
            'listaprecio_id' => TiendanubePedidoMaestrosSupport::listaprecioIdDefault() ?: null,
            'articulo_envio_sku' => (string) config('tiendanube.articulo_envio_sku', 'FL') ?: null,
            'articulo_descuento_sku' => (string) config('tiendanube.articulo_descuento_sku', '') ?: null,
            'usocuentacaja_nombre' => (string) config('tiendanube.usocuentacaja_nombre', 'TIENDA NUBE'),
        ]);
    }

    /**
     * Mapa gateway_key (lowercase) → código o id de cuentacaja (string), para sugerirCuentacajaId.
     *
     * @return array<string, string>
     */
    public static function mapaGatewayCuentacaja(): array
    {
        if (self::tablasListas() && TiendanubeGatewayCuentacaja::query()->exists()) {
            $out = [];
            $filas = TiendanubeGatewayCuentacaja::query()
                ->with('cuentacaja:id,codigo')
                ->orderBy('orden')
                ->orderBy('id')
                ->get();
            foreach ($filas as $fila) {
                $key = strtolower(trim((string) $fila->gateway_key));
                if ($key === '') {
                    continue;
                }
                $codigo = trim((string) ($fila->cuentacaja->codigo ?? ''));
                $out[$key] = $codigo !== '' ? $codigo : (string) $fila->cuentacaja_id;
            }
            if ($out !== []) {
                return $out;
            }
        }

        return (array) config('tiendanube.gateway_cuentacaja', []);
    }

    /**
     * @return Collection<int, TiendanubeGatewayCuentacaja>
     */
    public static function gateways(): Collection
    {
        if (! self::tablasListas()) {
            return collect();
        }

        return TiendanubeGatewayCuentacaja::query()
            ->with('cuentacaja:id,codigo,nombre')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TiendanubePuntoventaDeposito>
     */
    public static function paresPuntoventaDeposito(): Collection
    {
        if (! self::tablasListas()) {
            return collect();
        }

        return TiendanubePuntoventaDeposito::query()
            ->with(['puntoventa:id,codigo,nombre', 'deposito:id,codigo,nombre'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public static function puntoventaIdDefault(): ?int
    {
        $cfg = self::cabecera();
        if ($cfg && (int) $cfg->puntoventa_id > 0) {
            return (int) $cfg->puntoventa_id;
        }
        $par = self::paresPuntoventaDeposito()->firstWhere('es_default', true)
            ?? self::paresPuntoventaDeposito()->first();
        if ($par && (int) $par->puntoventa_id > 0) {
            return (int) $par->puntoventa_id;
        }

        return null;
    }

    public static function depositoIdDefault(?int $puntoventaId = null): ?int
    {
        if ($puntoventaId && $puntoventaId > 0) {
            $par = self::paresPuntoventaDeposito()->firstWhere('puntoventa_id', $puntoventaId);
            if ($par && (int) $par->deposito_id > 0) {
                return (int) $par->deposito_id;
            }
        }
        $cfg = self::cabecera();
        if ($cfg && (int) $cfg->deposito_id > 0) {
            return (int) $cfg->deposito_id;
        }
        $par = self::paresPuntoventaDeposito()->firstWhere('es_default', true)
            ?? self::paresPuntoventaDeposito()->first();

        return $par && (int) $par->deposito_id > 0 ? (int) $par->deposito_id : null;
    }

    public static function usocuentacajaNombre(): string
    {
        $cfg = self::cabecera();
        $nombre = trim((string) ($cfg?->usocuentacaja_nombre ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        return (string) config('tiendanube.usocuentacaja_nombre', 'TIENDA NUBE');
    }

    /**
     * @param  array{
     *   empresa_id?:int|null,
     *   puntoventa_id?:int|null,
     *   deposito_id?:int|null,
     *   listaprecio_id?:int|null,
     *   articulo_envio_sku?:string|null,
     *   articulo_descuento_sku?:string|null,
     *   usocuentacaja_nombre?:string|null,
     * }  $cabecera
     * @param  list<array{gateway_key:string,cuentacaja_id:int}>  $gateways
     * @param  list<array{puntoventa_id:int,deposito_id:int,es_default:bool}>  $pares
     */
    public static function guardar(array $cabecera, array $gateways, array $pares): TiendanubeConfiguracion
    {
        $cfg = self::asegurarCabecera();
        $cfg->fill([
            'empresa_id' => self::intOrNull($cabecera['empresa_id'] ?? null),
            'puntoventa_id' => self::intOrNull($cabecera['puntoventa_id'] ?? null),
            'deposito_id' => self::intOrNull($cabecera['deposito_id'] ?? null),
            'listaprecio_id' => self::intOrNull($cabecera['listaprecio_id'] ?? null),
            'articulo_envio_sku' => self::strOrNull($cabecera['articulo_envio_sku'] ?? null),
            'articulo_descuento_sku' => self::strOrNull($cabecera['articulo_descuento_sku'] ?? null),
            'usocuentacaja_nombre' => self::strOrNull($cabecera['usocuentacaja_nombre'] ?? null)
                ?? 'TIENDA NUBE',
        ]);
        $cfg->save();

        $gatewayIdsKeep = [];
        $orden = 0;
        $seenKeys = [];
        foreach ($gateways as $row) {
            $key = strtolower(trim((string) ($row['gateway_key'] ?? '')));
            $cuentaId = (int) ($row['cuentacaja_id'] ?? 0);
            if ($key === '' || $cuentaId <= 0 || isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $orden++;
            $fila = TiendanubeGatewayCuentacaja::query()->firstOrNew(['gateway_key' => $key]);
            $fila->cuentacaja_id = $cuentaId;
            $fila->orden = $orden;
            $fila->save();
            $gatewayIdsKeep[] = (int) $fila->id;
        }
        EloquentAuditDeleteSupport::each(
            TiendanubeGatewayCuentacaja::query()->when(
                $gatewayIdsKeep !== [],
                fn ($q) => $q->whereNotIn('id', $gatewayIdsKeep),
                fn ($q) => $q
            )
        );

        $parIdsKeep = [];
        $ordenPv = 0;
        $seenPv = [];
        $hayDefault = false;
        foreach ($pares as $row) {
            $pvId = (int) ($row['puntoventa_id'] ?? 0);
            $depId = (int) ($row['deposito_id'] ?? 0);
            if ($pvId <= 0 || $depId <= 0 || isset($seenPv[$pvId])) {
                continue;
            }
            $seenPv[$pvId] = true;
            $ordenPv++;
            $esDefault = ! empty($row['es_default']) && ! $hayDefault;
            if ($esDefault) {
                $hayDefault = true;
            }
            $fila = TiendanubePuntoventaDeposito::query()->firstOrNew(['puntoventa_id' => $pvId]);
            $fila->deposito_id = $depId;
            $fila->es_default = $esDefault;
            $fila->orden = $ordenPv;
            $fila->save();
            $parIdsKeep[] = (int) $fila->id;
        }
        if (! $hayDefault && $parIdsKeep !== []) {
            $primero = TiendanubePuntoventaDeposito::query()->whereKey($parIdsKeep[0])->first();
            if ($primero) {
                $primero->es_default = true;
                $primero->save();
            }
        }
        EloquentAuditDeleteSupport::each(
            TiendanubePuntoventaDeposito::query()->when(
                $parIdsKeep !== [],
                fn ($q) => $q->whereNotIn('id', $parIdsKeep),
                fn ($q) => $q
            )
        );

        // Si hay default en pares, alinear cabecera
        $def = TiendanubePuntoventaDeposito::query()->where('es_default', true)->first()
            ?? TiendanubePuntoventaDeposito::query()->orderBy('orden')->first();
        if ($def) {
            $cfg->puntoventa_id = (int) $def->puntoventa_id;
            $cfg->deposito_id = (int) $def->deposito_id;
            $cfg->save();
        }

        return $cfg->fresh();
    }

    private static function intOrNull(mixed $v): ?int
    {
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private static function strOrNull(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }
}
