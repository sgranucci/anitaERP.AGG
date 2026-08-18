<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura única Anita compra + concmov del período (sistema compras).
 * Equivalente al barrido de p-rg3685.c procesa_compra() por com_fecha_iva.
 */
final class LibroIvaDigitalComprasAnitaBridgeReader
{
    private const CHUNK_CONCMOV = 100;

    /**
     * @return list<array{
     *     compra: array<string, mixed>,
     *     conceptos: list<array{concepto: int, importe: float}>
     * }>
     */
    public function listarPeriodo(int $empresaId, string $desdeYmd, string $hastaYmd): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return [];
        }

        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta <= 0 || $hasta < $desde) {
            return [];
        }

        // Descripción / textos al final: evita corrimiento por | en el bridge.
        $camposCompra = implode(', ', [
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

        $whereCompra = ' WHERE com_fecha_iva >= '.$desde
            .' AND com_fecha_iva <= '.$hasta
            .' AND com_empresa = '.$empresaAnita;

        try {
            $api = new ApiAnita();
            $filasCompra = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'compra',
                'campos' => $camposCompra,
                'whereArmado' => $whereCompra,
                'orderBy' => 'com_fecha_iva, com_sucursal, com_nro',
            ]));
        } catch (\Throwable $e) {
            Log::warning('libro_iva_digital.compras_anita_bridge_compra', [
                'empresa_id' => $empresaId,
                'empresa_anita' => $empresaAnita,
                'desde' => $desde,
                'hasta' => $hasta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if ($filasCompra === []) {
            return [];
        }

        $compras = [];
        $nrosInternos = [];
        foreach ($filasCompra as $fila) {
            $fila = (array) $fila;
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

        return $resultado;
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

        $porInterno = [];
        $api = new ApiAnita();

        foreach (array_chunk($nros, self::CHUNK_CONCMOV) as $lote) {
            $in = implode(',', $lote);
            try {
                $filas = ApiAnita::decodificarListaFilas($api->apiCall([
                    'acc' => 'list',
                    'sistema' => 'compras',
                    'tabla' => 'concmov',
                    'campos' => 'concv_nro_interno, concv_concepto, concv_importe',
                    'whereArmado' => ' WHERE concv_nro_interno IN ('.$in.')',
                    'orderBy' => 'concv_nro_interno, concv_concepto',
                ]));
            } catch (\Throwable $e) {
                Log::warning('libro_iva_digital.compras_anita_bridge_concmov', [
                    'lote' => count($lote),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($filas as $fila) {
                $fila = (array) $fila;
                $nro = (int) ($fila['concv_nro_interno'] ?? 0);
                $importe = (float) ($fila['concv_importe'] ?? 0);
                $concepto = (int) ($fila['concv_concepto'] ?? 0);
                if ($nro <= 0 || $concepto <= 0 || abs($importe) < 0.0001) {
                    continue;
                }
                $porInterno[$nro][] = [
                    'concepto' => $concepto,
                    'importe' => $importe,
                ];
            }
        }

        return $porInterno;
    }
}
