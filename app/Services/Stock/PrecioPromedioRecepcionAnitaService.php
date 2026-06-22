<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\Log;

/**
 * Promedio de las últimas 3 recepciones de compra (recepmov) por artículo.
 * Réplica legacy calcula_precio_promedio en a-stkmov.c.
 */
class PrecioPromedioRecepcionAnitaService
{
    private const LONGITUD_CODIGO = 13;

    private const ULTIMAS_COMPRAS = 3;

    public function __construct(
        private ApiAnita $apiAnita,
    ) {}

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, float|null> clave SKU ERP
     */
    public function obtenerPreciosPromedioPorSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        $out = [];
        foreach ($skus as $sku) {
            $out[$sku] = $this->calcularParaSku($sku);
        }

        return $out;
    }

    public function calcularParaSku(string $sku): ?float
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $codigo = $this->codigoAnitaDesdeSku($sku);
        $filas = $this->consultarUltimasRecepciones($codigo);
        if ($filas === []) {
            return null;
        }

        $acum = 0.0;
        for ($i = 0; $i < self::ULTIMAS_COMPRAS; $i++) {
            $fila = $filas[$i] ?? null;
            if ($fila === null) {
                continue;
            }
            $precio = (float) ($fila['precio'] ?? 0);
            $codMon = trim((string) ($fila['cod_mon'] ?? '1'));
            $cotizacion = (float) ($fila['cotizacion'] ?? 1) ?: 1.0;

            if ($codMon > '1' && $precio != 0.0) {
                $acum += $precio * $cotizacion;
            } else {
                $acum += $precio;
            }
        }

        return round($acum / self::ULTIMAS_COMPRAS, 6);
    }

    /**
     * @return list<array{precio: float, cod_mon: string, cotizacion: float}>
     */
    private function consultarUltimasRecepciones(string $codigoAnita): array
    {
        $codigoSql = str_replace("'", "''", $codigoAnita);
        $payload = [
            'acc' => 'list',
            'sistema' => RecepcionProveedorAnitaImportSupport::sistemaCompras(),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea', 'recepmov'),
            'campos' => 'recv_articulo,recv_precio,recv_cod_mon,recv_cotizacion,recv_fecha,recv_nro',
            'orderBy' => 'recv_fecha DESC, recv_nro DESC, recv_orden DESC',
            'whereArmado' => " WHERE recv_articulo = '".$codigoSql."' ",
            'limit' => 'FIRST '.self::ULTIMAS_COMPRAS,
        ];

        try {
            $respuesta = $this->apiAnita->apiCall($payload);
        } catch (\Throwable $e) {
            Log::warning('PrecioPromedioRecepcionAnita: error ApiAnita', [
                'articulo' => $codigoAnita,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($respuesta);
        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $out[] = [
                'precio' => (float) ($row['recv_precio'] ?? 0),
                'cod_mon' => trim((string) ($row['recv_cod_mon'] ?? '1')),
                'cotizacion' => (float) ($row['recv_cotizacion'] ?? 1) ?: 1.0,
            ];
        }

        return $out;
    }
}
