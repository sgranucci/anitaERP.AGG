<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Coherencia neto gravado (G) ↔ IVA liquidado (I) por alícuota en comprobantes de compra.
 *
 * Si el neto gravado se cargó en un solo concepto sin tasa y hay IVA en 2+ alícuotas,
 * descompone el neto a partir de los importes de IVA. Tolerancia de cuadre: $0,90.
 */
final class ComprobanteProveedorConceptosIvaCoherenciaSupport
{
    public const TOLERANCIA = 0.90;

    /**
     * @param  list<int|string|null>  $conceptoIds
     * @param  list<int|float|string|null>  $montos
     * @return list<array{concepto_ivacompra_id: int, monto: float}>
     */
    public static function lineasDesdeArrays(array $conceptoIds, array $montos): array
    {
        $lineas = [];
        $max = max(count($conceptoIds), count($montos));

        for ($i = 0; $i < $max; $i++) {
            $conceptoId = (int) ($conceptoIds[$i] ?? 0);
            $monto = round((float) ($montos[$i] ?? 0), 2);
            if ($conceptoId <= 0 || abs($monto) < 0.0001) {
                continue;
            }
            $lineas[] = [
                'concepto_ivacompra_id' => $conceptoId,
                'monto' => $monto,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function normalizarYValidar(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        $conceptos = self::cargarConceptos($lineas);
        $estado = self::analizar($lineas, $conceptos);

        if (! $estado['aplica']) {
            return $lineas;
        }

        $lineas = self::descomponerNetoGravadoUnificado($lineas, $conceptos, $estado);
        $estado = self::analizar($lineas, $conceptos);
        self::assertCoherenciaIvaNeto($estado);

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return Collection<int, Concepto_Ivacompra>
     */
    private static function cargarConceptos(array $lineas): Collection
    {
        $ids = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', array_values($ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @return array{
     *     aplica: bool,
     *     neto_sin_tasa: float,
     *     neto_por_tasa: array<string, float>,
     *     iva_por_tasa: array<string, float>,
     *     tasas_iva: list<string>
     * }
     */
    private static function analizar(array $lineas, Collection $conceptos): array
    {
        $netoSinTasa = 0.0;
        $netoPorTasa = [];
        $ivaPorTasa = [];

        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if (! $concepto) {
                continue;
            }

            $monto = round(abs((float) ($linea['monto'] ?? 0)), 2);
            if ($monto <= 0) {
                continue;
            }

            $tipo = strtoupper((string) ($concepto->tipoconcepto ?? ''));
            $tasa = self::tasaConcepto($concepto);
            $tasaKey = self::tasaKey($tasa);

            if ($tipo === 'G') {
                if ($tasa > 0) {
                    $netoPorTasa[$tasaKey] = round(($netoPorTasa[$tasaKey] ?? 0) + $monto, 2);
                } else {
                    $netoSinTasa = round($netoSinTasa + $monto, 2);
                }
            } elseif ($tipo === 'I' && $tasa > 0) {
                $ivaPorTasa[$tasaKey] = round(($ivaPorTasa[$tasaKey] ?? 0) + $monto, 2);
            }
        }

        $tasasIva = array_keys($ivaPorTasa);

        return [
            'aplica' => $tasasIva !== [],
            'neto_sin_tasa' => $netoSinTasa,
            'neto_por_tasa' => $netoPorTasa,
            'iva_por_tasa' => $ivaPorTasa,
            'tasas_iva' => $tasasIva,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @param  array<string, mixed>  $estado
     * @return list<array<string, mixed>>
     */
    private static function descomponerNetoGravadoUnificado(array $lineas, Collection $conceptos, array $estado): array
    {
        $netoSinTasa = (float) ($estado['neto_sin_tasa'] ?? 0);
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        $netoPorTasa = (array) ($estado['neto_por_tasa'] ?? []);

        if ($netoSinTasa <= 0 || count($ivaPorTasa) < 2) {
            return $lineas;
        }

        if ($netoPorTasa !== []) {
            return $lineas;
        }

        $netoDescompuesto = [];
        $sumaNeto = 0.0;

        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $tasa = (float) $tasaKey;
            if ($tasa <= 0) {
                continue;
            }
            $neto = round((float) $iva / ($tasa / 100.), 2);
            $netoDescompuesto[$tasaKey] = $neto;
            $sumaNeto = round($sumaNeto + $neto, 2);
        }

        if (abs($sumaNeto - $netoSinTasa) > self::TOLERANCIA) {
            throw new RuntimeException(
                'El neto gravado único ('.number_format($netoSinTasa, 2, ',', '.')
                .') no coincide con la suma descompuesta por alícuotas IVA ('
                .number_format($sumaNeto, 2, ',', '.').'). Diferencia '
                .number_format(abs($sumaNeto - $netoSinTasa), 2, ',', '.')
                .' (tolerancia $'.number_format(self::TOLERANCIA, 2, ',', '.').').'
            );
        }

        $gravadosPorTasa = self::resolverConceptosGravadoPorTasa(
            array_keys($netoDescompuesto),
            $conceptos,
        );

        $lineasFiltradas = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if ($concepto && strtoupper((string) ($concepto->tipoconcepto ?? '')) === 'G' && self::tasaConcepto($concepto) <= 0) {
                continue;
            }
            $lineasFiltradas[] = $linea;
        }

        foreach ($netoDescompuesto as $tasaKey => $neto) {
            $conceptoGravado = $gravadosPorTasa[$tasaKey] ?? null;
            if ($conceptoGravado === null) {
                throw new RuntimeException(
                    'No se encontró concepto de neto gravado para alícuota '.rtrim(rtrim(number_format((float) $tasaKey, 3, '.', ''), '0'), '.').'%.'
                );
            }

            $conceptoId = (int) $conceptoGravado->id;
            $insertada = false;
            foreach ($lineasFiltradas as &$lineaFiltrada) {
                if ((int) ($lineaFiltrada['concepto_ivacompra_id'] ?? 0) === $conceptoId) {
                    $lineaFiltrada['monto'] = round((float) ($lineaFiltrada['monto'] ?? 0) + $neto, 2);
                    $insertada = true;
                    break;
                }
            }
            unset($lineaFiltrada);

            if (! $insertada) {
                $nueva = [
                    'concepto_ivacompra_id' => $conceptoId,
                    'monto' => $neto,
                ];
                if (isset($lineas[0]) && array_key_exists('cuentacontabledebe_id', $lineas[0])) {
                    $nueva['cuentacontabledebe_id'] = $lineas[0]['cuentacontabledebe_id'] ?? null;
                }
                $lineasFiltradas[] = $nueva;
            }
        }

        return $lineasFiltradas;
    }

    /**
     * @param  list<string>  $tasasKeys
     * @param  Collection<int, Concepto_Ivacompra>  $conceptosUsados
     * @return array<string, Concepto_Ivacompra>
     */
    private static function resolverConceptosGravadoPorTasa(array $tasasKeys, Collection $conceptosUsados): array
    {
        $resultado = [];

        foreach ($tasasKeys as $tasaKey) {
            foreach ($conceptosUsados as $concepto) {
                if (strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                    continue;
                }
                if (self::tasaKey(self::tasaConcepto($concepto)) === $tasaKey) {
                    $resultado[$tasaKey] = $concepto;
                    break;
                }
            }
        }

        $faltantes = array_diff($tasasKeys, array_keys($resultado));
        if ($faltantes !== []) {
            $tasasFaltantes = array_map(static fn (string $k): float => (float) $k, $faltantes);
            $extra = Concepto_Ivacompra::query()
                ->with('impuestos')
                ->where('tipoconcepto', 'G')
                ->whereHas('impuestos', static function ($q) use ($tasasFaltantes): void {
                    $q->whereIn('valor', $tasasFaltantes);
                })
                ->get();

            foreach ($faltantes as $tasaKey) {
                foreach ($extra as $concepto) {
                    if (self::tasaKey(self::tasaConcepto($concepto)) === $tasaKey) {
                        $resultado[$tasaKey] = $concepto;
                        break;
                    }
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private static function assertCoherenciaIvaNeto(array $estado): void
    {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        $netoPorTasa = (array) ($estado['neto_por_tasa'] ?? []);
        $netoSinTasa = (float) ($estado['neto_sin_tasa'] ?? 0);

        if ($netoSinTasa > 0 && count($ivaPorTasa) >= 2 && $netoPorTasa === []) {
            throw new RuntimeException(
                'Hay neto gravado sin alícuota y múltiples tasas de IVA: no se pudo descomponer. Revise los importes.'
            );
        }

        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $tasa = (float) $tasaKey;
            $neto = (float) ($netoPorTasa[$tasaKey] ?? 0);
            if ($neto <= 0) {
                throw new RuntimeException(
                    'Falta neto gravado para alícuota IVA '.self::etiquetaTasa($tasa)
                    .' (IVA cargado: '.number_format($iva, 2, ',', '.').').'
                );
            }

            $esperado = round($neto * $tasa / 100., 2);
            $diferencia = abs($esperado - $iva);
            if ($diferencia > self::TOLERANCIA) {
                throw new RuntimeException(
                    'IVA '.self::etiquetaTasa($tasa).' ('.number_format($iva, 2, ',', '.')
                    .') no coincide con neto gravado '.number_format($neto, 2, ',', '.')
                    .' × '.self::etiquetaTasa($tasa).' = '.number_format($esperado, 2, ',', '.')
                    .'. Diferencia '.number_format($diferencia, 2, ',', '.')
                    .' (tolerancia $'.number_format(self::TOLERANCIA, 2, ',', '.').').'
                );
            }
        }
    }

    private static function tasaConcepto(Concepto_Ivacompra $concepto): float
    {
        return round((float) ($concepto->impuestos->valor ?? 0), 3);
    }

    private static function tasaKey(float $tasa): string
    {
        return (string) round($tasa, 3);
    }

    private static function etiquetaTasa(float $tasa): string
    {
        return rtrim(rtrim(number_format($tasa, 3, '.', ''), '0'), '.').'%';
    }
}
