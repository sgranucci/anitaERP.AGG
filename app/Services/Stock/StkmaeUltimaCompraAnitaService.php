<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura de precios de compra en Anita stkmae (stkm_pre_compra1/2/3).
 * Fuente de fallback cuando el ERP no tiene compra (o &lt; 3 recepciones para promedio TITO).
 */
class StkmaeUltimaCompraAnitaService
{
    private const TABLA = 'stkmae';

    private const CAMPO_ARTICULO = 'stkm_articulo';

    private const CAMPO_PRECIO1 = 'stkm_pre_compra1';

    private const CAMPO_PRECIO2 = 'stkm_pre_compra2';

    private const CAMPO_PRECIO3 = 'stkm_pre_compra3';

    private const CAMPO_MONEDA = 'stkm_cod_mon_co3';

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
        $datos = $this->obtenerDatosUltimaCompraPorSkus($skus);
        $out = [];
        foreach ($datos as $sku => $dato) {
            $out[$sku] = $dato['precio'] ?? null;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{precio: float|null, moneda_id: int|null, compra1: float|null, compra2: float|null, compra3: float|null}>
     */
    public function obtenerDatosUltimaCompraPorSkus(array $skus): array
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

        $datosPorCodigo = [];
        $codigos = array_values(array_unique($codigoPorSku));
        foreach (array_chunk($codigos, self::CHUNK_SIZE) as $chunk) {
            foreach ($this->consultarStkmaeDatosPorCodigos($chunk) as $codigo => $datos) {
                $datosPorCodigo[$codigo] = $datos;
            }
        }

        $out = [];
        foreach ($codigoPorSku as $sku => $codigo) {
            $out[$sku] = $datosPorCodigo[$codigo] ?? [
                'precio' => null,
                'moneda_id' => null,
                'compra1' => null,
                'compra2' => null,
                'compra3' => null,
            ];
        }

        return $out;
    }

    /**
     * Promedio (compra1 + compra2 + compra3) / 3. Null si el resultado es ≤ 0.
     *
     * @param  list<string>  $skus
     * @return array<string, float|null>
     */
    public function obtenerPromedioTresComprasPorSkus(array $skus): array
    {
        $datos = $this->obtenerDatosUltimaCompraPorSkus($skus);
        $out = [];
        foreach ($datos as $sku => $dato) {
            $c1 = (float) ($dato['compra1'] ?? 0);
            $c2 = (float) ($dato['compra2'] ?? 0);
            $c3 = (float) ($dato['compra3'] ?? 0);
            $promedio = round(($c1 + $c2 + $c3) / 3, 6);
            $out[$sku] = $promedio > 0 ? $promedio : null;
        }

        return $out;
    }

    /**
     * @param  iterable<object>  $hijos
     */
    public function enriquecerLineasFormulaConCosto(iterable $hijos): void
    {
        ArticuloPrecioUltimaCompraSupport::enriquecerLineasFormulaConCosto($hijos);
    }

    /**
     * @param  iterable<\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Support\Collection|array>  $formulas
     */
    public function enriquecerFormulasPaginadasConCosto(iterable $formulas): void
    {
        ArticuloPrecioUltimaCompraSupport::enriquecerFormulasPaginadasConCosto($formulas);
    }

    /**
     * @param  list<string>  $codigosAnita  códigos de 13 caracteres
     * @return array<string, array{precio: float|null, moneda_id: int|null, compra1: float|null, compra2: float|null, compra3: float|null}>
     */
    private function consultarStkmaeDatosPorCodigos(array $codigosAnita): array
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
            'campos' => implode(',', [
                self::CAMPO_ARTICULO,
                self::CAMPO_PRECIO1,
                self::CAMPO_PRECIO2,
                self::CAMPO_PRECIO3,
                self::CAMPO_MONEDA,
            ]),
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
            $compra1 = self::floatONull($row[self::CAMPO_PRECIO1] ?? null);
            $compra2 = self::floatONull($row[self::CAMPO_PRECIO2] ?? null);
            $compra3 = self::floatONull($row[self::CAMPO_PRECIO3] ?? null);
            $monedaRaw = $row[self::CAMPO_MONEDA] ?? null;
            $out[$codigo] = [
                'precio' => $compra3,
                'moneda_id' => $monedaRaw !== null && $monedaRaw !== '' ? (int) $monedaRaw : null,
                'compra1' => $compra1,
                'compra2' => $compra2,
                'compra3' => $compra3,
            ];
        }

        return $out;
    }

    private static function floatONull(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return (float) $raw;
    }
}
