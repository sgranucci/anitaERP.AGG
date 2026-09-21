<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Listaprecio;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Database\SqlDialectSupport;

/**
 * Precio de costo para Reportes Local (Ferli):
 * precio venta fábrica × (1 − descuento%).
 *
 * Parámetros en BD (`facturacion_local_parametro`) vía FacturacionLocalParametroSupport;
 * fallback .env / config. No hardcodear listas ni % en pantallas.
 */
final class FacturacionLocalCostoFabricaSupport
{
    /**
     * @param  array<string, float>  $cache
     */
    public static function precioVentaFabrica(
        int $articuloId,
        ?int $talleId,
        string $fechaYmd,
        array &$cache = [],
        ?int $combinacionId = null,
    ): float {
        if ($articuloId <= 0 || $fechaYmd === '') {
            return 0.0;
        }

        $key = $articuloId.'|'
            .((int) ($talleId ?? 0)).'|'
            .((int) ($combinacionId ?? 0)).'|'
            .$fechaYmd.'|fab';
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $listaForzadaId = self::listaprecioForzadaId();
        if ($listaForzadaId !== null) {
            $valor = app(PrecioServiceFerli::class)->precioVigente(
                $articuloId,
                $listaForzadaId,
                $combinacionId,
                $fechaYmd,
            );
            $cache[$key] = round(max(0.0, $valor), 4);

            return $cache[$key];
        }

        $valor = self::precioDesdeListasFabrica(
            $articuloId,
            $talleId,
            $combinacionId,
            $fechaYmd,
        );
        $cache[$key] = $valor;

        return $valor;
    }

    /**
     * @param  array<string, float>  $cache
     */
    public static function precioCosto(
        int $articuloId,
        ?int $talleId,
        string $fechaYmd,
        array &$cache = [],
        ?int $combinacionId = null,
    ): float {
        $fabrica = self::precioVentaFabrica($articuloId, $talleId, $fechaYmd, $cache, $combinacionId);
        if ($fabrica <= 0) {
            return 0.0;
        }

        return round($fabrica * self::factorCosto(), 4);
    }

    public static function descuentoPct(): float
    {
        return FacturacionLocalParametroSupport::costoDescuentoPct();
    }

    public static function factorCosto(): float
    {
        return 1.0 - (self::descuentoPct() / 100.0);
    }

    public static function etiquetaFormula(): string
    {
        $pct = self::descuentoPct();
        $lista = FacturacionLocalParametroSupport::costoListaprecioCodigo();
        if ($lista !== '') {
            return 'Costo = lista fábrica código '.$lista.' − '.$pct.' %';
        }

        $codigos = self::codigosListasFabrica();

        return 'Costo = precio venta fábrica (listas '.implode(',', $codigos).') − '.$pct.' %';
    }

    /**
     * @return list<string>
     */
    public static function codigosListasFabrica(): array
    {
        return FacturacionLocalParametroSupport::costoListasFabricaCodigos();
    }

    private static function listaprecioForzadaId(): ?int
    {
        $codigo = FacturacionLocalParametroSupport::costoListaprecioCodigo();
        if ($codigo === '') {
            return null;
        }

        $id = Listaprecio::query()->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : null;
    }

    private static function precioDesdeListasFabrica(
        int $articuloId,
        ?int $talleId,
        ?int $combinacionId,
        string $fechaYmd,
    ): float {
        $ferli = app(PrecioServiceFerli::class);
        $idsFabrica = self::idsListasFabrica();

        if ($talleId !== null && $talleId > 0) {
            $precios = $ferli->asignaPrecio(
                $articuloId,
                $combinacionId ?? 0,
                (string) $talleId,
                $fechaYmd,
            );
            $valor = self::primerPrecioPositivoEnListas($precios, $idsFabrica);
            if ($valor > 0) {
                return $valor;
            }
        }

        foreach ($idsFabrica as $listaId) {
            $valor = $ferli->precioVigente($articuloId, $listaId, $combinacionId, $fechaYmd);
            if ($valor > 0) {
                return round($valor, 4);
            }
        }

        return 0.0;
    }

    /**
     * @return list<int>
     */
    private static function idsListasFabrica(): array
    {
        $codigos = self::codigosListasFabrica();

        return Listaprecio::query()
            ->whereIn('codigo', $codigos)
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<array<string,mixed>>|array<int,array<string,mixed>>  $precios
     * @param  list<int>  $idsFabrica
     */
    private static function primerPrecioPositivoEnListas(array $precios, array $idsFabrica): float
    {
        $ids = array_fill_keys($idsFabrica, true);
        foreach ($precios as $row) {
            if (! is_array($row)) {
                continue;
            }
            $listaId = (int) ($row['listaprecio_id'] ?? 0);
            $p = (float) ($row['precio'] ?? 0);
            if ($p > 0 && ($ids === [] || isset($ids[$listaId]))) {
                return round($p, 4);
            }
        }

        return 0.0;
    }
}
