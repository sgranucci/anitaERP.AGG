<?php

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Consulta costos en Anita (stkpre) por SKU y código de lista (prem_lista).
 *
 * No filtra por stkp_fe_ult_act ni vigencia: la lista 5000+mes ya identifica el costo del mes.
 */
final class GastronomiaStkpreAnitaSupport
{
    private const TABLA = 'stkpre';

    private const LONGITUD_CODIGO = 13;

    private const CHUNK_SIZE = 80;

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {}

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $codigosLista
     * @return array<string, array<string, float|null>>  sku ERP → [codigo_lista => precio]
     */
    public function preciosPorSkusYListas(array $skus, array $codigosLista): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $skus,
        ))));

        $codigosLista = array_values(array_unique(array_filter(array_map(
            fn ($c) => trim((string) $c),
            $codigosLista,
        ))));

        if ($skus === [] || $codigosLista === []) {
            return [];
        }

        $skuPorCodigoAnita = [];
        foreach ($skus as $sku) {
            $codigoAnita = $this->codigoAnitaDesdeSku($sku);
            $skuPorCodigoAnita[$codigoAnita] = $sku;
            $sinCeros = ltrim($codigoAnita, '0');
            if ($sinCeros !== '') {
                $skuPorCodigoAnita[$sinCeros] = $sku;
            }
        }

        $listasPorClave = [];
        foreach ($codigosLista as $codigoLista) {
            $listasPorClave[$codigoLista] = $codigoLista;
            $listasPorClave[ltrim($codigoLista, '0')] = $codigoLista;
        }

        $out = [];
        foreach ($skus as $sku) {
            $out[$sku] = array_fill_keys($codigosLista, null);
        }

        $codigosAnita = array_keys($skuPorCodigoAnita);
        foreach (array_chunk($codigosAnita, self::CHUNK_SIZE) as $chunk) {
            $filas = $this->consultarStkprePorCodigos($chunk, $codigosLista);
            foreach ($filas as $fila) {
                $codigoAnita = trim((string) ($fila['stkp_articulo'] ?? ''));
                $listaRaw = trim((string) ($fila['stkp_lista'] ?? ''));
                $sku = $skuPorCodigoAnita[$codigoAnita]
                    ?? $skuPorCodigoAnita[ltrim($codigoAnita, '0')]
                    ?? null;
                $lista = $listasPorClave[$listaRaw] ?? $listasPorClave[ltrim($listaRaw, '0')] ?? null;
                if ($sku === null || $lista === null || ! array_key_exists($lista, $out[$sku] ?? [])) {
                    continue;
                }
                $precio = $this->normalizarPrecio($fila['stkp_precio'] ?? null);
                if ($precio !== null) {
                    $out[$sku][$lista] = $precio;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigosAnita
     * @param  list<string>  $codigosLista
     * @return list<array<string, mixed>>
     */
    private function consultarStkprePorCodigos(array $codigosAnita, array $codigosLista): array
    {
        if ($codigosAnita === [] || $codigosLista === []) {
            return [];
        }

        $articulosSql = implode(',', array_map(
            fn (string $c) => "'".str_replace("'", "''", $c)."'",
            $codigosAnita,
        ));
        $listasSql = implode(',', array_map(
            fn (string $c) => "'".str_replace("'", "''", $c)."'",
            $codigosLista,
        ));

        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => implode(',', [
                'stkp_articulo',
                'stkp_lista',
                'stkp_precio',
            ]),
            'whereArmado' => " WHERE stkp_lista IN ({$listasSql}) AND stkp_articulo IN ({$articulosSql}) ",
        ];

        try {
            $respuesta = $this->apiAnita->apiCall($payload);
        } catch (\Throwable $e) {
            Log::warning('GastronomiaStkpreAnitaSupport: error ApiAnita', ['exception' => $e]);

            throw new \RuntimeException('No se pudo consultar costos en Anita (stkpre).');
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            Log::warning('GastronomiaStkpreAnitaSupport: respuesta inválida', [
                'respuesta' => substr((string) $respuesta, 0, 200),
            ]);

            throw new \RuntimeException('Respuesta inválida al consultar costos en Anita (stkpre).');
        }

        $filas = json_decode((string) $respuesta, true);
        if (! is_array($filas)) {
            throw new \RuntimeException('No se pudo interpretar costos desde Anita (stkpre).');
        }

        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila) && ! is_object($fila)) {
                continue;
            }
            $out[] = is_array($fila) ? $fila : get_object_vars($fila);
        }

        return $out;
    }

    private function normalizarPrecio(mixed $precio): ?float
    {
        if ($precio === null || $precio === '' || ! is_numeric($precio)) {
            return null;
        }

        return round((float) $precio, 4);
    }
}
