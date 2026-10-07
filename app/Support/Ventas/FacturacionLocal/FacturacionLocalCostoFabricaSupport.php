<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Listaprecio;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;

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
        $factor = number_format(self::factorCosto(), 2, ',', '');
        $lista = FacturacionLocalParametroSupport::costoListaprecioCodigo();
        if ($lista !== '') {
            return 'Costo = lista fábrica código '.$lista.' × '.$factor.' (descuento '.$pct.' %)';
        }

        $codigos = self::codigosListasFabrica();

        return 'Costo = precio fábrica (listas '.implode(',', $codigos).') × '.$factor.' (descuento '.$pct.' %)';
    }

    /**
     * Precio de venta fábrica vigente por artículo: primera lista fábrica con precio > 0.
     * Prioriza el precio genérico (sin combinación); si no hay, usa cualquier combinación.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, float>
     */
    public static function mapaPrecioVentaFabrica(array $articuloIds, string $fechaYmd): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $articuloIds),
            static fn (int $id) => $id > 0,
        )));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = 0.0;
        }
        if ($ids === [] || $fechaYmd === '') {
            return $out;
        }

        $listaForzada = self::listaprecioForzadaId();
        $listaIds = $listaForzada !== null ? [$listaForzada] : self::idsListasFabrica();
        if ($listaIds === []) {
            return $out;
        }

        $porLista = self::mapaUltimoPrecioPositivo($ids, $listaIds, $fechaYmd);
        foreach ($ids as $id) {
            $out[$id] = self::primerPrecioEnOrdenListas($porLista[$id] ?? [], $listaIds);
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, float>
     */
    public static function mapaPrecioCosto(array $articuloIds, string $fechaYmd): array
    {
        $factor = self::factorCosto();
        $out = [];
        foreach (self::mapaPrecioVentaFabrica($articuloIds, $fechaYmd) as $id => $fabrica) {
            $out[$id] = $fabrica > 0 ? round($fabrica * $factor, 4) : 0.0;
        }

        return $out;
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

    /**
     * Último precio > 0 por artículo y lista. En la misma fecha, el genérico
     * (sin combinación) pisa el de combinación.
     *
     * @param  list<int>  $articuloIds
     * @param  list<int>  $listaIds
     * @return array<int, array<int, float>> articulo_id => [listaprecio_id => precio]
     */
    private static function mapaUltimoPrecioPositivo(
        array $articuloIds,
        array $listaIds,
        string $fechaYmd,
    ): array {
        $buscar = array_fill_keys($articuloIds, true);
        $query = DB::table('precio')
            ->whereIn('listaprecio_id', $listaIds)
            ->where('fechavigencia', '<=', $fechaYmd)
            ->where('precio', '>', 0);
        if (count($articuloIds) <= 800) {
            $query->whereIn('articulo_id', $articuloIds);
        }

        /** @var array<int, array<int, array{precio:float,fecha:string,generico:bool}>> $mejor */
        $mejor = [];
        foreach ($query->get(['articulo_id', 'listaprecio_id', 'combinacion_id', 'fechavigencia', 'precio']) as $row) {
            $articuloId = (int) $row->articulo_id;
            if (! isset($buscar[$articuloId])) {
                continue;
            }
            $listaId = (int) $row->listaprecio_id;
            $precio = round((float) $row->precio, 4);
            if ($precio <= 0) {
                continue;
            }
            $fecha = substr((string) $row->fechavigencia, 0, 10);
            $combinacionId = $row->combinacion_id;
            $generico = $combinacionId === null || (int) $combinacionId === 0;
            $actual = $mejor[$articuloId][$listaId] ?? null;
            if ($actual === null
                || $fecha > $actual['fecha']
                || ($fecha === $actual['fecha'] && $generico && ! $actual['generico'])
                || ($fecha === $actual['fecha'] && $generico === $actual['generico'] && $precio > $actual['precio'])
            ) {
                $mejor[$articuloId][$listaId] = [
                    'precio' => $precio,
                    'fecha' => $fecha,
                    'generico' => $generico,
                ];
            }
        }

        $mapa = [];
        foreach ($mejor as $articuloId => $listas) {
            foreach ($listas as $listaId => $dato) {
                $mapa[$articuloId][$listaId] = $dato['precio'];
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, float>  $preciosPorLista
     * @param  list<int>  $listaIds
     */
    private static function primerPrecioEnOrdenListas(array $preciosPorLista, array $listaIds): float
    {
        foreach ($listaIds as $listaId) {
            $precio = (float) ($preciosPorLista[$listaId] ?? 0);
            if ($precio > 0) {
                return round($precio, 4);
            }
        }

        return 0.0;
    }
}
