<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Coherencia neto gravado (G) ↔ IVA liquidado (I) por alícuota en comprobantes de compra.
 *
 * Si hay 2+ alícuotas de IVA y el neto viene unificado (sin tasa o en una sola alícuota),
 * abre gravados a partir de los importes de IVA (fuente de verdad). Si la suma teórica
 * no cuadra con el neto original, reparte la diferencia entre los gravados; nunca ajusta IVA.
 * Tolerancia de cuadre IVA↔neto tras el ajuste: $0,90.
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

        if (self::necesitaAperturaGravados($estado)) {
            $lineas = self::abrirGravadosDesdeIva($lineas, $conceptos, $estado);
            $conceptos = self::cargarConceptos($lineas);
            $estado = self::analizar($lineas, $conceptos);
        }

        self::assertCoherenciaIvaNeto($estado);

        return $lineas;
    }

    /**
     * Completa/actualiza codigo_concepto_anita desde el maestro.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerCodigosAnita(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        $conceptos = self::cargarConceptos($lineas);
        foreach ($lineas as &$linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if ($concepto) {
                $linea['codigo_concepto_anita'] = $concepto->codigo;
            }
        }
        unset($linea);

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
     *     tasas_iva: list<string>,
     *     neto_total: float
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
        $netoTotal = round($netoSinTasa + array_sum($netoPorTasa), 2);

        return [
            'aplica' => $tasasIva !== [],
            'neto_sin_tasa' => $netoSinTasa,
            'neto_por_tasa' => $netoPorTasa,
            'iva_por_tasa' => $ivaPorTasa,
            'tasas_iva' => $tasasIva,
            'neto_total' => $netoTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private static function necesitaAperturaGravados(array $estado): bool
    {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        if (count($ivaPorTasa) < 2) {
            return false;
        }

        $netoTotal = (float) ($estado['neto_total'] ?? 0);
        if ($netoTotal <= 0) {
            return false;
        }

        $netoSinTasa = (float) ($estado['neto_sin_tasa'] ?? 0);
        $netoPorTasa = (array) ($estado['neto_por_tasa'] ?? []);
        $buckets = ($netoSinTasa > 0 ? 1 : 0) + count($netoPorTasa);

        // Un solo gravado (sin tasa o una sola alícuota) con 2+ IVA → abrir.
        if ($buckets <= 1) {
            return true;
        }

        // Varios gravados pero faltan tasas o no cierran con el IVA.
        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $neto = (float) ($netoPorTasa[$tasaKey] ?? 0);
            if ($neto <= 0) {
                return true;
            }
            $esperado = round($neto * ((float) $tasaKey) / 100., 2);
            if (abs($esperado - (float) $iva) > self::TOLERANCIA) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @param  array<string, mixed>  $estado
     * @return list<array<string, mixed>>
     */
    private static function abrirGravadosDesdeIva(array $lineas, Collection $conceptos, array $estado): array
    {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        $netoTotal = (float) ($estado['neto_total'] ?? 0);

        $netoTeorico = [];
        $sumaTeorica = 0.0;

        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $tasa = (float) $tasaKey;
            if ($tasa <= 0) {
                continue;
            }
            $neto = round((float) $iva / ($tasa / 100.), 2);
            $netoTeorico[$tasaKey] = $neto;
            $sumaTeorica = round($sumaTeorica + $neto, 2);
        }

        if ($netoTeorico === []) {
            return $lineas;
        }

        $netoDescompuesto = self::repartirDiferenciaEntreGravados(
            $netoTeorico,
            $sumaTeorica,
            $netoTotal,
        );

        $semillaGravado = self::resolverSemillaGravado($lineas, $conceptos);
        $preferidosPorTasa = self::preferidosGravadoPorTasa($lineas, $conceptos);

        $gravadosPorTasa = self::resolverConceptosGravadoPorTasa(
            array_keys($netoDescompuesto),
            $conceptos,
            $preferidosPorTasa,
            $semillaGravado,
        );

        $lineasFiltradas = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if ($concepto && strtoupper((string) ($concepto->tipoconcepto ?? '')) === 'G') {
                continue;
            }
            $lineasFiltradas[] = $linea;
        }

        foreach ($netoDescompuesto as $tasaKey => $neto) {
            $conceptoGravado = $gravadosPorTasa[$tasaKey] ?? null;
            if ($conceptoGravado === null) {
                throw new RuntimeException(
                    'No se encontró concepto de neto gravado para alícuota '.self::etiquetaTasa((float) $tasaKey).'.'
                );
            }

            $conceptoId = (int) $conceptoGravado->id;
            $insertada = false;
            foreach ($lineasFiltradas as &$lineaFiltrada) {
                if ((int) ($lineaFiltrada['concepto_ivacompra_id'] ?? 0) === $conceptoId) {
                    $lineaFiltrada['monto'] = round((float) ($lineaFiltrada['monto'] ?? 0) + $neto, 2);
                    $lineaFiltrada['codigo_concepto_anita'] = $conceptoGravado->codigo;
                    $insertada = true;
                    break;
                }
            }
            unset($lineaFiltrada);

            if (! $insertada) {
                $nueva = [
                    'concepto_ivacompra_id' => $conceptoId,
                    'codigo_concepto_anita' => $conceptoGravado->codigo,
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
     * Reparte (netoOriginal − sumaTeórica) entre los gravados; no toca IVA.
     * Proporcional al neto teórico; el remanente de redondeo va al gravado mayor.
     *
     * @param  array<string, float>  $netoTeorico
     * @return array<string, float>
     */
    private static function repartirDiferenciaEntreGravados(
        array $netoTeorico,
        float $sumaTeorica,
        float $netoOriginal,
    ): array {
        $delta = round($netoOriginal - $sumaTeorica, 2);
        if (abs($delta) < 0.005 || $sumaTeorica <= 0) {
            return $netoTeorico;
        }

        $ajustados = [];
        $repartido = 0.0;
        $keys = array_keys($netoTeorico);
        $ultimo = $keys[array_key_last($keys)];

        foreach ($keys as $tasaKey) {
            if ($tasaKey === $ultimo) {
                continue;
            }
            $share = round($delta * ($netoTeorico[$tasaKey] / $sumaTeorica), 2);
            $ajustados[$tasaKey] = round($netoTeorico[$tasaKey] + $share, 2);
            $repartido = round($repartido + $share, 2);
        }

        $ajustados[$ultimo] = round($netoTeorico[$ultimo] + ($delta - $repartido), 2);

        return $ajustados;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     */
    private static function resolverSemillaGravado(array $lineas, Collection $conceptos): ?Concepto_Ivacompra
    {
        $mejor = null;
        $mejorMonto = -1.0;

        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if (! $concepto || strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                continue;
            }
            $monto = abs((float) ($linea['monto'] ?? 0));
            if ($monto > $mejorMonto) {
                $mejorMonto = $monto;
                $mejor = $concepto;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @return array<string, Concepto_Ivacompra>
     */
    private static function preferidosGravadoPorTasa(array $lineas, Collection $conceptos): array
    {
        $preferidos = [];

        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if (! $concepto || strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                continue;
            }
            $tasa = self::tasaConcepto($concepto);
            if ($tasa <= 0) {
                continue;
            }
            $tasaKey = self::tasaKey($tasa);
            if (! isset($preferidos[$tasaKey])) {
                $preferidos[$tasaKey] = $concepto;
            }
        }

        return $preferidos;
    }

    /**
     * @param  list<string>  $tasasKeys
     * @param  Collection<int, Concepto_Ivacompra>  $conceptosUsados
     * @param  array<string, Concepto_Ivacompra>  $preferidosPorTasa
     * @return array<string, Concepto_Ivacompra>
     */
    private static function resolverConceptosGravadoPorTasa(
        array $tasasKeys,
        Collection $conceptosUsados,
        array $preferidosPorTasa = [],
        ?Concepto_Ivacompra $semilla = null,
    ): array {
        $resultado = [];

        foreach ($tasasKeys as $tasaKey) {
            if (isset($preferidosPorTasa[$tasaKey])) {
                $resultado[$tasaKey] = $preferidosPorTasa[$tasaKey];
                continue;
            }
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
                ->orderByRaw("CASE WHEN nombre LIKE 'Gravado%' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get();

            foreach ($faltantes as $tasaKey) {
                $candidatos = $extra->filter(
                    static fn (Concepto_Ivacompra $c): bool => self::tasaKey(self::tasaConcepto($c)) === $tasaKey
                );
                if ($candidatos->isEmpty()) {
                    continue;
                }
                $elegido = self::elegirCandidatoPorSemilla($candidatos, $semilla) ?? $candidatos->first();
                if ($elegido) {
                    $resultado[$tasaKey] = $elegido;
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, Concepto_Ivacompra>  $candidatos
     */
    private static function elegirCandidatoPorSemilla(Collection $candidatos, ?Concepto_Ivacompra $semilla): ?Concepto_Ivacompra
    {
        if ($semilla === null || $candidatos->isEmpty()) {
            return $candidatos->first();
        }

        $tokensSemilla = self::tokensNombreGravado((string) $semilla->nombre);
        if ($tokensSemilla === []) {
            return $candidatos->first();
        }

        $mejor = null;
        $mejorScore = -1;
        foreach ($candidatos as $candidato) {
            $tokens = self::tokensNombreGravado((string) $candidato->nombre);
            $score = count(array_intersect($tokensSemilla, $tokens));
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $candidato;
            }
        }

        return $mejorScore > 0 ? $mejor : $candidatos->first();
    }

    /**
     * @return list<string>
     */
    private static function tokensNombreGravado(string $nombre): array
    {
        $normalizado = mb_strtolower($nombre);
        $normalizado = preg_replace('/\d+(?:[.,]\d+)?%?/', ' ', $normalizado) ?? $normalizado;
        $normalizado = preg_replace('/[^a-záéíóúñü\s]/u', ' ', $normalizado) ?? $normalizado;
        $parts = preg_split('/\s+/', trim($normalizado)) ?: [];
        $stop = ['gravado', 'grav', 'al', 'de', 'la', 'el', 'los', 'las', 'op', 'l', 'com', 'otras'];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || in_array($part, $stop, true) || mb_strlen($part) < 3) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
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

    /**
     * Clave estable con decimales (evita que PHP casteé "21" a int en arrays).
     */
    private static function tasaKey(float $tasa): string
    {
        return number_format(round($tasa, 3), 3, '.', '');
    }

    private static function etiquetaTasa(float $tasa): string
    {
        return rtrim(rtrim(number_format($tasa, 3, '.', ''), '0'), '.').'%';
    }
}
