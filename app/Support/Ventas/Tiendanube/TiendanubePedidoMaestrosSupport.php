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

    public static function puntoventaDefault(): ?Puntoventa
    {
        return self::puntoventaPorCodigo((string) config('tiendanube.puntoventa_codigo_default'));
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    public static function puntoventasOnline(): Collection
    {
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

    public static function depositoDefault(): ?Depmae
    {
        return self::depositoPorCodigo((string) config('tiendanube.deposito_codigo_default'));
    }

    public static function listaprecioIdDefault(): int
    {
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

    public static function articuloEnvio(): ?Articulo
    {
        return self::resolverArticuloPorSku((string) config('tiendanube.articulo_envio_sku'));
    }

    public static function articuloDescuento(): ?Articulo
    {
        return self::resolverArticuloPorSku((string) config('tiendanube.articulo_descuento_sku'));
    }

    /**
     * Sugiere cuentacaja del uso «TIENDA NUBE» según gateway TN.
     */
    public static function sugerirCuentacajaId(?string $gateway): ?int
    {
        $cuentas = self::cuentacajasOperativas();
        if ($cuentas->isEmpty()) {
            return null;
        }
        if ($cuentas->count() === 1) {
            return (int) $cuentas->first()->id;
        }

        $gateway = strtolower(trim((string) $gateway));
        $tokens = self::tokensBusquedaGateway($gateway);

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

        // Fallback: primera del uso (orden)
        return (int) $cuentas->first()->id;
    }

    /**
     * @return list<string>
     */
    private static function tokensBusquedaGateway(string $gateway): array
    {
        return match (true) {
            $gateway === '' => [],
            str_contains($gateway, 'mercado') || $gateway === 'credit_card' => ['mercadopago', 'mep', 'mercado'],
            str_contains($gateway, 'boa') => ['boa', 'nube'],
            $gateway === 'custom' || str_contains($gateway, 'nube') || str_contains($gateway, 'offline') => ['pago nube', 'nube', '609'],
            str_contains($gateway, 'libre') => ['mercadolibre', 'meli'],
            default => array_values(array_filter(preg_split('/[\s_\-]+/', $gateway) ?: [])),
        };
    }

    /**
     * Cuentas de caja del uso TIENDA NUBE.
     *
     * @return Collection<int, Cuentacaja>
     */
    public static function cuentacajasOperativas(): Collection
    {
        $empresaId = (int) config('tiendanube.empresa_id', 1);
        $usoNombre = TiendanubeUsoCuentacajaSupport::nombre();

        $q = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', function ($uq) use ($usoNombre) {
                $uq->where('nombre', $usoNombre);
            })
            ->orderBy('codigo');

        return $q->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }
}
