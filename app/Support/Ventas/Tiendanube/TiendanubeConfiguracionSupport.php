<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubeConfiguracion;
use App\Models\Ventas\TiendanubeGatewayCuentacaja;
use App\Models\Ventas\TiendanubePuntoventaDeposito;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura/escritura de la configuración operativa Tiendanube (BD), por tienda.
 * Prioridad: filas de esa tienda → filas de Ferli → config/tiendanube.php (.env).
 */
final class TiendanubeConfiguracionSupport
{
    public static function tablasListas(): bool
    {
        return Schema::hasTable('tiendanube_configuracion')
            && Schema::hasTable('tiendanube_gateway_cuentacaja')
            && Schema::hasTable('tiendanube_puntoventa_deposito')
            && Schema::hasColumn('tiendanube_configuracion', 'store_id');
    }

    public static function storeIdFerli(): string
    {
        $id = trim((string) config('tiendanube.store_id', '3796054'));

        return $id !== '' ? $id : '3796054';
    }

    public static function storeIdEfectivo(?string $storeId): string
    {
        $storeId = trim((string) $storeId);

        return $storeId !== '' ? $storeId : self::storeIdFerli();
    }

    public static function cabeceraPropia(?string $storeId): ?TiendanubeConfiguracion
    {
        if (! self::tablasListas()) {
            return null;
        }

        return TiendanubeConfiguracion::query()
            ->where('store_id', self::storeIdEfectivo($storeId))
            ->orderBy('id')
            ->first();
    }

    /**
     * Cabecera de la tienda, o la de Ferli si esa tienda todavía no tiene fila propia.
     */
    public static function cabecera(?string $storeId = null): ?TiendanubeConfiguracion
    {
        $storeId = self::storeIdEfectivo($storeId);
        $propia = self::cabeceraPropia($storeId);
        if ($propia) {
            return $propia;
        }
        if ($storeId !== self::storeIdFerli()) {
            return self::cabeceraPropia(self::storeIdFerli());
        }

        return null;
    }

    public static function asegurarCabecera(?string $storeId = null): TiendanubeConfiguracion
    {
        $storeId = self::storeIdEfectivo($storeId);
        $cfg = self::cabeceraPropia($storeId);
        if ($cfg) {
            return $cfg;
        }

        $esFerli = $storeId === self::storeIdFerli();
        $listaId = null;
        if ($esFerli) {
            $codigoLista = trim((string) config('tiendanube.listaprecio_codigo_default', ''));
            if ($codigoLista !== '') {
                $listaId = (int) (DB::table('listaprecio')->where('codigo', $codigoLista)->value('id') ?? 0) ?: null;
            }
        }

        return TiendanubeConfiguracion::query()->create([
            'store_id' => $storeId,
            'empresa_id' => $esFerli ? ((int) config('tiendanube.empresa_id', 1) ?: null) : null,
            'puntoventa_id' => $esFerli
                ? TiendanubePedidoMaestrosSupport::puntoventaPorCodigo(
                    (string) config('tiendanube.puntoventa_codigo_default')
                )?->id
                : null,
            'deposito_id' => $esFerli
                ? TiendanubePedidoMaestrosSupport::depositoPorCodigo(
                    (string) config('tiendanube.deposito_codigo_default')
                )?->id
                : null,
            'listaprecio_id' => $listaId,
            'articulo_envio_sku' => $esFerli
                ? ((string) config('tiendanube.articulo_envio_sku', 'FL') ?: null)
                : null,
            'articulo_descuento_sku' => $esFerli
                ? ((string) config('tiendanube.articulo_descuento_sku', '') ?: null)
                : null,
            'usocuentacaja_nombre' => (string) config('tiendanube.usocuentacaja_nombre', 'TIENDA NUBE'),
        ]);
    }

    public static function empresaId(?string $storeId = null): int
    {
        $cfg = self::cabecera($storeId);
        $id = (int) ($cfg?->empresa_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) config('tiendanube.empresa_id', 1);
    }

    /**
     * Mapa gateway_key (lowercase) → código o id de cuentacaja (string), para sugerirCuentacajaId.
     *
     * @return array<string, string>
     */
    public static function mapaGatewayCuentacaja(?string $storeId = null): array
    {
        $storeId = self::storeIdEfectivo($storeId);
        if (self::cabeceraPropia($storeId)) {
            return self::mapaGatewayDe($storeId);
        }

        if ($storeId !== self::storeIdFerli()) {
            $ferli = self::mapaGatewayDe(self::storeIdFerli());
            if ($ferli !== []) {
                return $ferli;
            }
        } else {
            $propio = self::mapaGatewayDe($storeId);
            if ($propio !== []) {
                return $propio;
            }
        }

        return (array) config('tiendanube.gateway_cuentacaja', []);
    }

    /**
     * @return array<string, string>
     */
    private static function mapaGatewayDe(string $storeId): array
    {
        if (! self::tablasListas()) {
            return [];
        }

        $out = [];
        $filas = TiendanubeGatewayCuentacaja::query()
            ->where('store_id', $storeId)
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

        return $out;
    }

    /**
     * @return Collection<int, TiendanubeGatewayCuentacaja>
     */
    public static function gateways(?string $storeId = null, bool $soloPropia = false): Collection
    {
        if (! self::tablasListas()) {
            return collect();
        }

        $storeId = self::storeIdEfectivo($storeId);
        $filas = self::gatewaysDe($storeId);
        if ($filas->isNotEmpty() || $soloPropia || $storeId === self::storeIdFerli()) {
            return $filas;
        }

        return self::gatewaysDe(self::storeIdFerli());
    }

    /**
     * @return Collection<int, TiendanubeGatewayCuentacaja>
     */
    private static function gatewaysDe(string $storeId): Collection
    {
        return TiendanubeGatewayCuentacaja::query()
            ->where('store_id', $storeId)
            ->with('cuentacaja:id,codigo,nombre')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TiendanubePuntoventaDeposito>
     */
    public static function paresPuntoventaDeposito(?string $storeId = null, bool $soloPropia = false): Collection
    {
        if (! self::tablasListas()) {
            return collect();
        }

        $storeId = self::storeIdEfectivo($storeId);
        $filas = self::paresDe($storeId);
        if ($filas->isNotEmpty() || $soloPropia || $storeId === self::storeIdFerli()) {
            return $filas;
        }

        return self::paresDe(self::storeIdFerli());
    }

    /**
     * @return Collection<int, TiendanubePuntoventaDeposito>
     */
    private static function paresDe(string $storeId): Collection
    {
        return TiendanubePuntoventaDeposito::query()
            ->where('store_id', $storeId)
            ->with(['puntoventa:id,codigo,nombre', 'deposito:id,codigo,nombre'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public static function puntoventaIdDefault(?string $storeId = null): ?int
    {
        $cfg = self::cabecera($storeId);
        if ($cfg && (int) $cfg->puntoventa_id > 0) {
            return (int) $cfg->puntoventa_id;
        }
        $par = self::paresPuntoventaDeposito($storeId)->firstWhere('es_default', true)
            ?? self::paresPuntoventaDeposito($storeId)->first();
        if ($par && (int) $par->puntoventa_id > 0) {
            return (int) $par->puntoventa_id;
        }

        return null;
    }

    public static function depositoIdDefault(?int $puntoventaId = null, ?string $storeId = null): ?int
    {
        if ($puntoventaId && $puntoventaId > 0) {
            $par = self::paresPuntoventaDeposito($storeId)->firstWhere('puntoventa_id', $puntoventaId);
            if ($par && (int) $par->deposito_id > 0) {
                return (int) $par->deposito_id;
            }
        }
        $cfg = self::cabecera($storeId);
        if ($cfg && (int) $cfg->deposito_id > 0) {
            return (int) $cfg->deposito_id;
        }
        $par = self::paresPuntoventaDeposito($storeId)->firstWhere('es_default', true)
            ?? self::paresPuntoventaDeposito($storeId)->first();

        return $par && (int) $par->deposito_id > 0 ? (int) $par->deposito_id : null;
    }

    public static function usocuentacajaNombre(?string $storeId = null): string
    {
        $cfg = self::cabecera($storeId);
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
    public static function guardar(array $cabecera, array $gateways, array $pares, ?string $storeId = null): TiendanubeConfiguracion
    {
        $storeId = self::storeIdEfectivo($storeId);
        $cfg = self::asegurarCabecera($storeId);
        $cfg->fill([
            'store_id' => $storeId,
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
            $fila = TiendanubeGatewayCuentacaja::query()->firstOrNew([
                'store_id' => $storeId,
                'gateway_key' => $key,
            ]);
            $fila->cuentacaja_id = $cuentaId;
            $fila->orden = $orden;
            $fila->save();
            $gatewayIdsKeep[] = (int) $fila->id;
        }
        EloquentAuditDeleteSupport::each(
            TiendanubeGatewayCuentacaja::query()
                ->where('store_id', $storeId)
                ->when(
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
            $fila = TiendanubePuntoventaDeposito::query()->firstOrNew([
                'store_id' => $storeId,
                'puntoventa_id' => $pvId,
            ]);
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
            TiendanubePuntoventaDeposito::query()
                ->where('store_id', $storeId)
                ->when(
                    $parIdsKeep !== [],
                    fn ($q) => $q->whereNotIn('id', $parIdsKeep),
                    fn ($q) => $q
                )
        );

        $def = TiendanubePuntoventaDeposito::query()
            ->where('store_id', $storeId)
            ->where('es_default', true)
            ->first()
            ?? TiendanubePuntoventaDeposito::query()
                ->where('store_id', $storeId)
                ->orderBy('orden')
                ->first();
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
