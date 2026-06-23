<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;

class RecepcionProveedorStkmaePrecioAnitaVerificacionService
{
    private const TOLERANCIA_PRECIO = 0.02;

    /**
     * @param  array{
     *     incluir_importadas?: bool,
     *     solo_sync_at?: bool,
     * }  $opciones
     * @return array{
     *     recepciones: int,
     *     articulos_erp: int,
     *     ok_precio3: int,
     *     ok_fecha: int,
     *     sin_stkmae: list<array<string, mixed>>,
     *     diferencias_precio: list<array<string, mixed>>,
     *     diferencias_fecha: list<array<string, mixed>>,
     * }
     */
    public function verificar(array $opciones = []): array
    {
        $incluirImportadas = (bool) ($opciones['incluir_importadas'] ?? false);
        $soloSyncAt = (bool) ($opciones['solo_sync_at'] ?? true);

        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('numerorecepcion', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id');

        if ($soloSyncAt) {
            $query->whereNotNull('stkmae_precio_anita_sync_at');
        }

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        $recepciones = $query->get();

        /** @var array<string, array{codigo_anita: string, precio_pesos: float, fecha_anita: int, recepcion_id: int, numerorecepcion: int, empresa_id: int}> $ultimoPorCodigo */
        $ultimoPorCodigo = [];

        foreach ($recepciones as $recepcion) {
            $empresaId = max(1, (int) ($recepcion->empresa_id ?? 1));
            foreach (StkmaePrecioCompraAnitaBridgeSupport::agruparLineasRecepcion($recepcion) as $grupo) {
                $codigo = $grupo['codigo_anita'];
                $clave = $empresaId.'|'.$codigo;
                $ultimoPorCodigo[$clave] = [
                    'codigo_anita' => $codigo,
                    'precio_pesos' => (float) $grupo['precio_pesos'],
                    'fecha_anita' => (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d')),
                    'recepcion_id' => (int) $recepcion->id,
                    'numerorecepcion' => (int) $recepcion->numerorecepcion,
                    'empresa_id' => $empresaId,
                ];
            }
        }

        /** @var array<int, list<string>> $codigosPorEmpresa */
        $codigosPorEmpresa = [];
        foreach ($ultimoPorCodigo as $item) {
            $codigosPorEmpresa[$item['empresa_id']][] = $item['codigo_anita'];
        }

        /** @var array<string, array<string, mixed>> $stkmaePorCodigo */
        $stkmaePorCodigo = [];
        foreach ($codigosPorEmpresa as $empresaId => $codigosEmpresa) {
            $leidos = StkmaePrecioCompraAnitaBridgeSupport::leerStkmaePorCodigos(
                array_values(array_unique($codigosEmpresa)),
                $empresaId
            );
            foreach ($leidos as $codigo => $fila) {
                $stkmaePorCodigo[$empresaId.'|'.$codigo] = $fila;
            }
        }

        $resultado = [
            'recepciones' => $recepciones->count(),
            'articulos_erp' => count($ultimoPorCodigo),
            'ok_precio3' => 0,
            'ok_fecha' => 0,
            'sin_stkmae' => [],
            'diferencias_precio' => [],
            'diferencias_fecha' => [],
        ];

        foreach ($ultimoPorCodigo as $clave => $esperado) {
            $fila = $stkmaePorCodigo[$clave] ?? null;
            if ($fila === null) {
                $resultado['sin_stkmae'][] = $esperado;

                continue;
            }

            $precioAnita = (float) ($fila['stkm_pre_compra3'] ?? 0);
            $fechaAnita = (int) ($fila['stkm_fe_ult_compra'] ?? 0);
            $diffPrecio = abs($precioAnita - $esperado['precio_pesos']);

            if ($diffPrecio <= self::TOLERANCIA_PRECIO) {
                $resultado['ok_precio3']++;
            } else {
                $resultado['diferencias_precio'][] = array_merge($esperado, [
                    'precio_anita' => $precioAnita,
                    'diferencia' => round($diffPrecio, 4),
                    'pre_compra1_anita' => (float) ($fila['stkm_pre_compra1'] ?? 0),
                    'pre_compra2_anita' => (float) ($fila['stkm_pre_compra2'] ?? 0),
                ]);
            }

            if ($fechaAnita === $esperado['fecha_anita']) {
                $resultado['ok_fecha']++;
            } else {
                $resultado['diferencias_fecha'][] = array_merge($esperado, [
                    'fecha_anita_stk' => $fechaAnita,
                ]);
            }
        }

        return $resultado;
    }
}
