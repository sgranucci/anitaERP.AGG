<?php

namespace App\Services\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Precio de última compra (stkmae.stkm_pre_compra3) vía ApiAnita.
 * Hasta que el ERP tenga ingreso por compras, es la fuente operativa del costo de insumos.
 */
class StkmaeUltimaCompraAnitaService
{
    private const TABLA = 'stkmae';

    private const CAMPO_ARTICULO = 'stkm_articulo';

    private const CAMPO_PRECIO = 'stkm_pre_compra3';

    private const LONGITUD_CODIGO = 13;

    private const CHUNK_SIZE = 80;

    public function __construct(
        private ApiAnita $apiAnita,
    ) {}

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $skus  SKU tal como en articulo.sku (sin rellenar)
     * @return array<string, float|null> clave = SKU del ERP; valor = stkm_pre_compra3 o null si no hay fila
     */
    public function obtenerPreciosUltimaCompraPorSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        if ($skus === []) {
            return [];
        }

        $codigoPorSku = [];
        foreach ($skus as $sku) {
            $codigoPorSku[$sku] = $this->codigoAnitaDesdeSku($sku);
        }

        $precioPorCodigo = [];
        $codigos = array_values(array_unique($codigoPorSku));
        foreach (array_chunk($codigos, self::CHUNK_SIZE) as $chunk) {
            foreach ($this->consultarStkmaePorCodigos($chunk) as $codigo => $precio) {
                $precioPorCodigo[$codigo] = $precio;
            }
        }

        $out = [];
        foreach ($codigoPorSku as $sku => $codigo) {
            $out[$sku] = $precioPorCodigo[$codigo] ?? null;
        }

        return $out;
    }

    /**
     * Asigna {@see $hijo->costo_ultima_compra} (no persistido) a líneas con artículo.
     *
     * @param  iterable<object>  $hijos
     */
    public function enriquecerLineasFormulaConCosto(iterable $hijos): void
    {
        $skus = [];
        foreach ($hijos as $hijo) {
            $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        if ($skus === []) {
            return;
        }

        $precios = $this->obtenerPreciosUltimaCompraPorSkus($skus);
        foreach ($hijos as $hijo) {
            $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
            $hijo->costo_ultima_compra = $sku !== '' ? ($precios[$sku] ?? null) : null;
        }
    }

    /**
     * @param  iterable<\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Support\Collection|array>  $formulas
     */
    public function enriquecerFormulasPaginadasConCosto(iterable $formulas): void
    {
        $skus = [];
        foreach ($formulas as $formula) {
            foreach ($formula->formula_articulo_hijos ?? [] as $hijo) {
                $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        if ($skus === []) {
            return;
        }

        $precios = $this->obtenerPreciosUltimaCompraPorSkus($skus);
        foreach ($formulas as $formula) {
            foreach ($formula->formula_articulo_hijos ?? [] as $hijo) {
                $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
                $hijo->costo_ultima_compra = $sku !== '' ? ($precios[$sku] ?? null) : null;
            }
        }
    }

    /**
     * @param  list<string>  $codigosAnita  códigos de 13 caracteres
     * @return array<string, float|null> clave = código Anita exacto
     */
    private function consultarStkmaePorCodigos(array $codigosAnita): array
    {
        if ($codigosAnita === []) {
            return [];
        }

        $lista = implode(',', array_map(
            fn (string $c) => "'".str_replace("'", "''", $c)."'",
            $codigosAnita
        ));

        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => self::CAMPO_ARTICULO.','.self::CAMPO_PRECIO,
            'whereArmado' => ' WHERE '.self::CAMPO_ARTICULO.' IN ('.$lista.') ',
        ];

        try {
            $respuesta = $this->apiAnita->apiCallEscritura($payload);
        } catch (\Throwable $e) {
            Log::warning('StkmaeUltimaCompraAnita: error ApiAnita', ['exception' => $e]);

            return [];
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            Log::warning('StkmaeUltimaCompraAnita: respuesta inválida', ['respuesta' => substr((string) $respuesta, 0, 200)]);

            return [];
        }

        $filas = json_decode((string) $respuesta, true);
        if (! is_array($filas)) {
            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila) && ! is_object($fila)) {
                continue;
            }
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $codigo = trim((string) ($row[self::CAMPO_ARTICULO] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $precio = $row[self::CAMPO_PRECIO] ?? null;
            $out[$codigo] = $precio !== null && $precio !== '' ? (float) $precio : null;
        }

        return $out;
    }
}
