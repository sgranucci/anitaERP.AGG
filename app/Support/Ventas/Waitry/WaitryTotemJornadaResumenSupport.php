<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\TotemWaitryGastronomia;
use Illuminate\Support\Collection;

/**
 * Agrupa ingresos Waitry por tótem y medio real (MP, Totalcoin, etc.) para Informe Z.
 * Excluye cash y movimientos que irían a la cuenta puente TOTEM (ajuste en cierre final).
 */
final class WaitryTotemJornadaResumenSupport
{
    private const CLAVE_SIN_TOTEM = 0;

    /**
     * @param  Collection<int, TotemWaitryGastronomia>  $totems
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   por_totem: list<array<string, mixed>>,
     *   total_general: array{cantidad_ordenes:int,total_ingreso:float,por_medio_pago:list<array<string, mixed>>}
     * }
     */
    public static function armar(Collection $totems, array $lineas): array
    {
        $mapTotems = self::mapaTotemsPorTableId($totems);
        $unicoTotem = $totems->count() === 1 ? $totems->first() : null;

        $buckets = [];

        foreach ($lineas as $ln) {
            if (! self::lineaCuentaParaIngresoTotem($ln)) {
                continue;
            }

            $clave = self::claveTotemParaLinea($ln, $mapTotems, $unicoTotem);
            if (! isset($buckets[$clave])) {
                $buckets[$clave] = self::bucketVacio($clave, $mapTotems, $unicoTotem, $totems);
            }

            $monto = self::montoIngresoLinea($ln);
            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['waitry_tipo_pago'] ?? null) ?? 'totem';
            $medioKey = $tipo;

            if (! isset($buckets[$clave]['medios'][$medioKey])) {
                $buckets[$clave]['medios'][$medioKey] = [
                    'tipo' => $ln['waitry_tipo_pago'] ?? null,
                    'etiqueta' => (string) ($ln['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo)),
                    'cuentacaja_label' => $ln['cuentacaja_esperada_label'] ?? null,
                    'cantidad' => 0,
                    'total' => 0.0,
                ];
            }

            $buckets[$clave]['medios'][$medioKey]['cantidad']++;
            $buckets[$clave]['medios'][$medioKey]['total'] = round(
                $buckets[$clave]['medios'][$medioKey]['total'] + $monto,
                2,
            );
            $buckets[$clave]['cantidad_ordenes']++;
            $buckets[$clave]['total_ingreso'] = round($buckets[$clave]['total_ingreso'] + $monto, 2);
        }

        ksort($buckets);
        $porTotem = [];
        $globalMedios = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;

        foreach ($buckets as $bucket) {
            $medios = array_values($bucket['medios']);
            usort($medios, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

            $porTotem[] = [
                'totem_id' => $bucket['totem_id'],
                'ubicacion_nombre' => $bucket['ubicacion_nombre'],
                'detalle' => $bucket['detalle'],
                'waitry_table_id' => $bucket['waitry_table_id'],
                'cantidad_ordenes' => (int) $bucket['cantidad_ordenes'],
                'total_ingreso' => (float) $bucket['total_ingreso'],
                'por_medio_pago' => $medios,
            ];

            $globalCantidad += (int) $bucket['cantidad_ordenes'];
            $globalIngreso = round($globalIngreso + (float) $bucket['total_ingreso'], 2);

            foreach ($medios as $medio) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($medio['tipo'] ?? null) ?? 'totem';
                if (! isset($globalMedios[$tipo])) {
                    $globalMedios[$tipo] = [
                        'tipo' => $medio['tipo'],
                        'etiqueta' => $medio['etiqueta'],
                        'cuentacaja_label' => $medio['cuentacaja_label'],
                        'cantidad' => 0,
                        'total' => 0.0,
                    ];
                }
                $globalMedios[$tipo]['cantidad'] += (int) $medio['cantidad'];
                $globalMedios[$tipo]['total'] = round($globalMedios[$tipo]['total'] + (float) $medio['total'], 2);
            }
        }

        $globalMediosList = array_values($globalMedios);
        usort($globalMediosList, fn (array $a, array $b) => strcmp($a['etiqueta'], $b['etiqueta']));

        return [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => $globalMediosList,
            ],
        ];
    }

    /**
     * Ingreso tótem: cobrada en Waitry (mismo criterio que facturación con waitry_cobro_totem).
     *
     * @param  array<string, mixed>  $ln
     */
    public static function lineaCuentaParaIngresoTotem(array $ln): bool
    {
        if (WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($ln['waitry_tipo_pago'] ?? null)) {
            return false;
        }

        if (($ln['paid_waitry'] ?? null) === true) {
            return true;
        }

        return ! empty($ln['waitry_cobro_totem']);
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    private static function montoIngresoLinea(array $ln): float
    {
        $montoCobro = (float) ($ln['monto_cobro_waitry'] ?? 0);
        if ($montoCobro > 0.0001) {
            return round($montoCobro, 2);
        }

        return round((float) ($ln['total'] ?? 0), 2);
    }

    /**
     * @param  array<int, TotemWaitryGastronomia>  $mapTotems
     * @param  array<string, mixed>  $ln
     */
    private static function claveTotemParaLinea(array $ln, array $mapTotems, ?TotemWaitryGastronomia $unicoTotem): int
    {
        $tableId = isset($ln['waitry_table_id']) ? (int) $ln['waitry_table_id'] : 0;
        if ($tableId > 0 && isset($mapTotems[$tableId])) {
            return (int) $mapTotems[$tableId]->id;
        }

        if ($unicoTotem !== null) {
            return (int) $unicoTotem->id;
        }

        return self::CLAVE_SIN_TOTEM;
    }

    /**
     * @return array<int, TotemWaitryGastronomia>
     */
    private static function mapaTotemsPorTableId(Collection $totems): array
    {
        $map = [];
        foreach ($totems as $totem) {
            $tableId = (int) ($totem->waitry_table_id ?? 0);
            if ($tableId > 0) {
                $map[$tableId] = $totem;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, TotemWaitryGastronomia>  $mapTotems
     * @return array<string, mixed>
     */
    private static function bucketVacio(
        int $clave,
        array $mapTotems,
        ?TotemWaitryGastronomia $unicoTotem,
        Collection $totems,
    ): array {
        if ($clave === self::CLAVE_SIN_TOTEM) {
            return [
                'totem_id' => null,
                'ubicacion_nombre' => 'Sin tótem asignado',
                'detalle' => $totems->isEmpty()
                    ? 'Configure tótems Waitry en Ventas → Tótem Waitry'
                    : 'Órdenes sin tableId o sin match de waitry_table_id',
                'waitry_table_id' => null,
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'medios' => [],
            ];
        }

        $totem = $unicoTotem !== null && (int) $unicoTotem->id === $clave
            ? $unicoTotem
            : $totems->firstWhere('id', $clave);

        return [
            'totem_id' => $totem?->id,
            'ubicacion_nombre' => (string) ($totem?->ubicacion?->nombre ?? '—'),
            'detalle' => trim((string) ($totem?->detalle ?? '')),
            'waitry_table_id' => $totem?->waitry_table_id,
            'cantidad_ordenes' => 0,
            'total_ingreso' => 0.0,
            'medios' => [],
        ];
    }
}
