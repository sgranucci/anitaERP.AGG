<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita compra + concmov del período (sistema compras).
 * Una pasada de cabeceras y otra de conceptos (IN de todos los internos).
 * Equivalente a p-rg3685.c procesa_compra() por com_fecha_iva.
 */
final class LibroIvaDigitalComprasAnitaBridgeReader
{
    private const DIAS_POR_LOTE = 10;

    private const CHUNK_CONCMOV_RESPALDO = 500;

    /** @var array<string, list<array{compra: array<string, mixed>, conceptos: list<array{concepto: int, importe: float}>}>> */
    private static array $cachePeriodo = [];

    /**
     * @return list<array{
     *     compra: array<string, mixed>,
     *     conceptos: list<array{concepto: int, importe: float}>
     * }>
     */
    public function listarPeriodo(int $empresaId, string $desdeYmd, string $hastaYmd): array
    {
        $cacheKey = $empresaId.'|'.$desdeYmd.'|'.$hastaYmd;
        if (isset(self::$cachePeriodo[$cacheKey])) {
            return self::$cachePeriodo[$cacheKey];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return self::$cachePeriodo[$cacheKey] = [];
        }

        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta <= 0 || $hasta < $desde) {
            return self::$cachePeriodo[$cacheKey] = [];
        }

        $filasCompra = $this->listarCompraPeriodo($empresaAnita, $desde, $hasta);
        if ($filasCompra === []) {
            return self::$cachePeriodo[$cacheKey] = [];
        }

        $compras = [];
        $nrosInternos = [];
        foreach ($filasCompra as $fila) {
            $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);
            if ($nroInterno > 0) {
                $nrosInternos[$nroInterno] = true;
            }
            $compras[] = $fila;
        }

        $conceptosPorInterno = $this->listarConcmovPorInternos(array_keys($nrosInternos));

        $resultado = [];
        foreach ($compras as $compra) {
            $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
            $resultado[] = [
                'compra' => $compra,
                'conceptos' => $conceptosPorInterno[$nroInterno] ?? [],
            ];
        }

        return self::$cachePeriodo[$cacheKey] = $resultado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCompraPeriodo(int $empresaAnita, int $desde, int $hasta): array
    {
        $filas = $this->listarCompraRango($empresaAnita, $desde, $hasta);
        if ($filas !== null) {
            return $filas;
        }

        $acumulado = [];
        foreach (LibroIvaDigitalAnitaPeriodoSupport::partirRangoYmd($desde, $hasta, self::DIAS_POR_LOTE) as [$loteDesde, $loteHasta]) {
            $lote = $this->listarCompraRango($empresaAnita, $loteDesde, $loteHasta);
            if ($lote === null) {
                continue;
            }
            foreach ($lote as $fila) {
                $acumulado[] = $fila;
            }
        }

        return $acumulado;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function listarCompraRango(int $empresaAnita, int $desde, int $hasta): ?array
    {
        // Descripción / textos al final: evita corrimiento por | en el bridge.
        $campos = implode(', ', [
            'com_proveedor',
            'com_tipo',
            'com_letra',
            'com_sucursal',
            'com_nro',
            'com_fecha',
            'com_fecha_iva',
            'com_monto',
            'com_cod_mon',
            'com_cotizacion',
            'com_nro_interno',
            'com_empresa',
            'com_cuit_prov',
            'com_nombre_prov',
        ]);

        $where = ' WHERE com_fecha_iva >= '.$desde
            .' AND com_fecha_iva <= '.$hasta
            .' AND com_empresa = '.$empresaAnita;

        return $this->listar(
            'compra',
            $campos,
            $where,
            'com_fecha_iva, com_sucursal, com_nro',
            'compras_anita_bridge_compra',
            [
                'empresa_anita' => $empresaAnita,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        );
    }

    /**
     * @param  list<int>  $nrosInternos
     * @return array<int, list<array{concepto: int, importe: float}>>
     */
    private function listarConcmovPorInternos(array $nrosInternos): array
    {
        $nros = array_values(array_unique(array_filter(
            array_map('intval', $nrosInternos),
            static fn (int $n) => $n > 0,
        )));
        if ($nros === []) {
            return [];
        }

        $permitidos = array_fill_keys($nros, true);
        $unaPasada = $this->listarConcmovIn($nros, $permitidos);
        if ($unaPasada !== null) {
            return $unaPasada;
        }

        $porInterno = [];
        foreach (array_chunk($nros, self::CHUNK_CONCMOV_RESPALDO) as $lote) {
            $loteFilas = $this->listarConcmovIn($lote, $permitidos);
            if ($loteFilas === null) {
                continue;
            }
            foreach ($loteFilas as $nro => $conceptos) {
                foreach ($conceptos as $concepto) {
                    $porInterno[$nro][] = $concepto;
                }
            }
        }

        return $porInterno;
    }

    /**
     * @param  list<int>  $nros
     * @param  array<int, true>  $permitidos
     * @return array<int, list<array{concepto: int, importe: float}>>|null
     */
    private function listarConcmovIn(array $nros, array $permitidos): ?array
    {
        $filas = $this->listar(
            'concmov',
            'concv_nro_interno, concv_concepto, concv_importe',
            ' WHERE concv_nro_interno IN ('.implode(',', $nros).')',
            'concv_nro_interno, concv_concepto',
            'compras_anita_bridge_concmov',
            ['lote' => count($nros)],
        );
        if ($filas === null) {
            return null;
        }

        return $this->agruparConcmov($filas, $permitidos);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, true>  $permitidos
     * @return array<int, list<array{concepto: int, importe: float}>>
     */
    private function agruparConcmov(array $filas, array $permitidos): array
    {
        $porInterno = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila['concv_nro_interno'] ?? 0);
            $importe = (float) ($fila['concv_importe'] ?? 0);
            $concepto = (int) ($fila['concv_concepto'] ?? 0);
            if ($nro <= 0 || ! isset($permitidos[$nro]) || $concepto <= 0 || abs($importe) < 0.0001) {
                continue;
            }
            $porInterno[$nro][] = [
                'concepto' => $concepto,
                'importe' => $importe,
            ];
        }

        return $porInterno;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return list<array<string, mixed>>|null
     */
    private function listar(
        string $tabla,
        string $campos,
        string $where,
        string $orderBy,
        string $logKey,
        array $contexto = [],
    ): ?array {
        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita())->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
                'orderBy' => $orderBy,
            ]));
        } catch (\Throwable $e) {
            Log::warning('libro_iva_digital.'.$logKey, $contexto + ['error' => $e->getMessage()]);

            return null;
        }

        if ($parsed['error_lectura'] !== null) {
            Log::warning('libro_iva_digital.'.$logKey, $contexto + ['error' => $parsed['error_lectura']]);

            return null;
        }

        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }
}
