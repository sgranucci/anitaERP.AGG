<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Caja\Cuentacaja;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Puntoventa;
use App\Support\Stock\ArticuloSkuMatchSupport;
use Illuminate\Support\Collection;

/**
 * Resoluciones de maestros para el canal Tiendanube.
 */
final class TiendanubePedidoMaestrosSupport
{
    public static function puntoventaPorCodigo(?string $codigo): ?Puntoventa
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            $codigo,
            str_pad(ltrim($codigo, '0') ?: '0', 5, '0', STR_PAD_LEFT),
            ltrim($codigo, '0'),
        ])));

        return Puntoventa::query()
            ->whereIn('codigo', $candidatos)
            ->orderBy('id')
            ->first();
    }

    public static function puntoventaDefault(?string $storeId = null): ?Puntoventa
    {
        $id = TiendanubeConfiguracionSupport::puntoventaIdDefault($storeId);
        if ($id && $id > 0) {
            $pv = Puntoventa::query()->find($id);
            if ($pv) {
                return $pv;
            }
        }

        if (TiendanubeConfiguracionSupport::storeIdEfectivo($storeId) !== TiendanubeConfiguracionSupport::storeIdFerli()) {
            return null;
        }

        return self::puntoventaPorCodigo((string) config('tiendanube.puntoventa_codigo_default'));
    }

    /**
     * PV y depósito default de la tienda (configuración). Fuente de verdad para facturar sin pedir al usuario.
     *
     * @return array{puntoventa_id:int,deposito_id:int}
     */
    public static function defaultsFacturacion(?string $storeId = null): array
    {
        $pvId = (int) (self::puntoventaDefault($storeId)?->id ?? 0);
        $depId = (int) (self::depositoDefault($pvId > 0 ? $pvId : null, $storeId)?->id ?? 0);

        return [
            'puntoventa_id' => $pvId,
            'deposito_id' => $depId,
        ];
    }

    /**
     * PV y depósito a usar en un pedido: el sugerido si pertenece a la tienda; si no, el default de esa tienda.
     * Preferir {@see defaultsFacturacion()} en emisión (manual o lote) para no depender de sugeridos viejos.
     *
     * @return array{puntoventa_id:int,deposito_id:int}
     */
    public static function resolverPuntoventaYDeposito(?string $storeId, ?int $pvSugerido, ?int $depSugerido): array
    {
        $online = self::puntoventasOnline($storeId);
        $ids = $online->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $pvSugerido = (int) $pvSugerido;
        $depSugerido = (int) $depSugerido;
        $usaSugerido = $pvSugerido > 0 && ($ids === [] || in_array($pvSugerido, $ids, true));
        $pvId = $usaSugerido
            ? $pvSugerido
            : (int) (self::puntoventaDefault($storeId)?->id ?? 0);
        if ($usaSugerido && $depSugerido > 0) {
            $depId = $depSugerido;
        } else {
            $depId = (int) (self::depositoDefault($pvId > 0 ? $pvId : null, $storeId)?->id ?? 0);
        }

        return [
            'puntoventa_id' => $pvId,
            'deposito_id' => $depId,
        ];
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    public static function puntoventasOnline(?string $storeId = null): Collection
    {
        $pares = TiendanubeConfiguracionSupport::paresPuntoventaDeposito($storeId);
        if ($pares->isNotEmpty()) {
            $ids = $pares->pluck('puntoventa_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
            if ($ids !== []) {
                return Puntoventa::query()
                    ->whereIn('id', $ids)
                    ->orderBy('codigo')
                    ->get();
            }
        }

        if (TiendanubeConfiguracionSupport::storeIdEfectivo($storeId) !== TiendanubeConfiguracionSupport::storeIdFerli()
            && TiendanubeConfiguracionSupport::cabeceraPropia($storeId)) {
            return collect();
        }

        $codigos = (array) config('tiendanube.puntoventa_codigos_online', []);
        if ($codigos === []) {
            return collect();
        }

        return Puntoventa::query()
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->get();
    }

    public static function depositoPorCodigo(?string $codigo): ?Depmae
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        return Depmae::query()->where('codigo', $codigo)->orderBy('id')->first();
    }

    public static function depositoDefault(?int $puntoventaId = null, ?string $storeId = null): ?Depmae
    {
        $id = TiendanubeConfiguracionSupport::depositoIdDefault($puntoventaId, $storeId);
        if ($id && $id > 0) {
            $dep = Depmae::query()->find($id);
            if ($dep) {
                return $dep;
            }
        }

        if (TiendanubeConfiguracionSupport::storeIdEfectivo($storeId) !== TiendanubeConfiguracionSupport::storeIdFerli()) {
            return null;
        }

        return self::depositoPorCodigo((string) config('tiendanube.deposito_codigo_default'));
    }

    public static function listaprecioIdDefault(?string $storeId = null): int
    {
        $cfg = TiendanubeConfiguracionSupport::cabecera($storeId);
        if ($cfg && (int) $cfg->listaprecio_id > 0) {
            return (int) $cfg->listaprecio_id;
        }

        if (TiendanubeConfiguracionSupport::storeIdEfectivo($storeId) !== TiendanubeConfiguracionSupport::storeIdFerli()
            && TiendanubeConfiguracionSupport::cabeceraPropia($storeId)) {
            return 0;
        }

        $codigo = trim((string) config('tiendanube.listaprecio_codigo_default', ''));
        if ($codigo === '') {
            return 0;
        }

        return (int) (\Illuminate\Support\Facades\DB::table('listaprecio')
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
    }

    public static function resolverArticuloPorSku(?string $sku): ?Articulo
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return null;
        }

        return ArticuloSkuMatchSupport::resolverCanonico($sku);
    }

    public static function articuloEnvio(?string $storeId = null): ?Articulo
    {
        return self::resolverArticuloPorSku(self::skuConfigurado('articulo_envio_sku', 'articulo_envio_sku', $storeId));
    }

    public static function articuloDescuento(?string $storeId = null): ?Articulo
    {
        return self::resolverArticuloPorSku(self::skuConfigurado('articulo_descuento_sku', 'articulo_descuento_sku', $storeId));
    }

    private static function skuConfigurado(string $columna, string $configKey, ?string $storeId): string
    {
        $cfg = TiendanubeConfiguracionSupport::cabecera($storeId);
        $sku = trim((string) ($cfg?->{$columna} ?? ''));
        if ($sku !== '') {
            return $sku;
        }
        if (TiendanubeConfiguracionSupport::storeIdEfectivo($storeId) !== TiendanubeConfiguracionSupport::storeIdFerli()
            && TiendanubeConfiguracionSupport::cabeceraPropia($storeId)) {
            return '';
        }

        return (string) config('tiendanube.'.$configKey);
    }

    /**
     * Sugiere cuentacaja del uso «TIENDA NUBE» según gateway TN.
     * Prioridad: mapa explícito config → heurística por nombre → primera del uso.
     *
     * Claves útiles en mapa (.env TIENDANUBE_GATEWAY_CUENTACAJA):
     * pago-nube, offline, gocuotas, mercadopago, tarjeta_naranja, etc. → código o id.
     */
    public static function sugerirCuentacajaId(
        ?string $gateway,
        ?string $gatewayName = null,
        ?array $paymentJson = null,
        ?string $storeId = null,
    ): ?int {
        $paymentJson = is_array($paymentJson) ? $paymentJson : [];
        $map = TiendanubeConfiguracionSupport::mapaGatewayCuentacaja($storeId);
        $keys = array_values(array_unique(array_filter([
            strtolower(trim((string) $gateway)),
            strtolower(trim((string) $gatewayName)),
            strtolower(trim((string) ($paymentJson['gateway'] ?? ''))),
            strtolower(trim((string) ($paymentJson['gateway_name'] ?? ''))),
            strtolower(trim((string) ($paymentJson['method'] ?? ''))),
            strtolower(trim((string) ($paymentJson['credit_card_company'] ?? ''))),
        ])));
        foreach ($keys as $key) {
            if ($key === '' || ! array_key_exists($key, $map)) {
                continue;
            }
            $val = $map[$key];
            if ($val === null || $val === '') {
                continue;
            }
            if (is_numeric($val)) {
                $asId = (int) $val;
                if ($asId > 0 && Cuentacaja::query()->whereKey($asId)->exists()) {
                    return $asId;
                }
                $byCodigo = (int) (Cuentacaja::query()->where('codigo', (string) $val)->value('id') ?? 0);
                if ($byCodigo > 0) {
                    return $byCodigo;
                }
            } elseif (is_string($val)) {
                $byCodigo = (int) (Cuentacaja::query()->where('codigo', $val)->value('id') ?? 0);
                if ($byCodigo > 0) {
                    return $byCodigo;
                }
            }
        }

        $cuentas = self::cuentacajasOperativas($storeId);
        if ($cuentas->isEmpty()) {
            return null;
        }
        if ($cuentas->count() === 1) {
            return (int) $cuentas->first()->id;
        }

        $haystackGw = strtolower(trim(implode(' ', array_filter([
            (string) $gateway,
            (string) $gatewayName,
            (string) ($paymentJson['gateway'] ?? ''),
            (string) ($paymentJson['gateway_name'] ?? ''),
            (string) ($paymentJson['method'] ?? ''),
            (string) ($paymentJson['credit_card_company'] ?? ''),
        ]))));
        $tokens = self::tokensBusquedaGateway($haystackGw);

        $mejor = null;
        $mejorScore = 0;
        foreach ($cuentas as $cuenta) {
            $haystack = strtolower(trim(($cuenta->codigo ?? '').' '.($cuenta->nombre ?? '')));
            $score = 0;
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    $score += strlen($token);
                }
            }
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $cuenta;
            }
        }

        if ($mejor && $mejorScore > 0) {
            return (int) $mejor->id;
        }

        return (int) $cuentas->first()->id;
    }

    /**
     * @return list<string>
     */
    private static function tokensBusquedaGateway(string $gateway): array
    {
        $g = strtolower(trim($gateway));

        return match (true) {
            $g === '' => [],
            str_contains($g, 'naranja') => ['naranja', 'tarjeta naranja'],
            // Procesador Pago Nube (antes de mirar credit_card → no confundir con MP)
            str_contains($g, 'pago-nube')
                || str_contains($g, 'pago nube')
                || str_contains($g, 'nuvempago') => ['pago nube', 'nube', '609'],
            str_contains($g, 'offline')
                || str_contains($g, 'transferencia')
                || str_contains($g, 'depósito')
                || str_contains($g, 'deposito')
                || str_contains($g, 'custom') => ['frances', 'lugano', '4781', 'transferencia'],
            str_contains($g, 'gocuotas') || str_contains($g, 'go cuotas') || str_contains($g, 'go-cuotas') => ['go cuotas', '610'],
            str_contains($g, 'boa') => ['boa', 'nube', '11310112', '612'],
            str_contains($g, 'libre') || str_contains($g, 'meli') => ['mercadolibre', 'meli', '608'],
            str_contains($g, 'mercado') || str_contains($g, 'mercadopago') => ['mercadopago', 'mep', 'mercado', '1002', '611'],
            // Método sin procesador conocido: preferir Pago Nube (canal TN Ferli)
            str_contains($g, 'credit_card')
                || str_contains($g, 'debit_card')
                || str_contains($g, 'wallet') => ['pago nube', 'nube', '609'],
            default => array_values(array_filter(preg_split('/[\s_\-]+/', $g) ?: [])),
        };
    }

    /**
     * Cuentas de caja del uso TIENDA NUBE.
     *
     * @return Collection<int, Cuentacaja>
     */
    public static function cuentacajasOperativas(?string $storeId = null): Collection
    {
        $empresaId = TiendanubeConfiguracionSupport::empresaId($storeId);
        $usoNombre = TiendanubeUsoCuentacajaSupport::nombre($storeId);

        $q = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', function ($uq) use ($usoNombre) {
                $uq->where('nombre', $usoNombre);
            })
            ->orderBy('codigo');

        return $q->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }
}
