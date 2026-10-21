<?php

namespace App\Support\Sueldos;

use Carbon\CarbonImmutable;

/**
 * Proyecciones del headcount (KPIs, consolidado, rotación, composición).
 * La pantalla y el Excel leen este mismo arreglo.
 */
final class EstructuraPersonalCubo
{
    /**
     * @param  array{
     *     corte: CarbonImmutable,
     *     empresa_id: int,
     *     personas: list<array<string, mixed>>,
     *     bajas: list<array<string, mixed>>,
     *     altas: list<array<string, mixed>>,
     *     inicio_mes_por_cc: array<int, int>,
     *     inicio_anio_por_cc: array<int, int>,
     *     inicio_mes_total: int,
     *     inicio_anio_total: int,
     *     presupuesto: array<string, int>
     * }  $facts
     * @return array<string, mixed>
     */
    public static function desde(array $facts): array
    {
        /** @var CarbonImmutable $corte */
        $corte = $facts['corte'];
        $personas = $facts['personas'];
        $bajas = $facts['bajas'];
        $altas = $facts['altas'];
        $presupuesto = $facts['presupuesto'];

        $siglas = self::siglas($personas, $bajas, $altas, $presupuesto);
        $dotacion = count($personas);
        $pptoTotal = array_sum($presupuesto);
        $tienePpto = $presupuesto !== [];
        $desvio = $tienePpto ? $dotacion - $pptoTotal : null;

        $inicioMes = (int) $facts['inicio_mes_total'];
        $promedioMes = ($inicioMes + $dotacion) / 2;
        $bajasMes = 0;
        foreach ($bajas as $baja) {
            if ((int) $baja['mes'] === $corte->month) {
                $bajasMes++;
            }
        }
        $rotacionMes = $promedioMes > 0 ? round($bajasMes / $promedioMes * 100, 1) : 0.0;

        $inicioAnio = (int) $facts['inicio_anio_total'];
        $promedioAnio = ($inicioAnio + $dotacion) / 2;
        $rotacionAnio = $promedioAnio > 0 ? round(count($bajas) / $promedioAnio * 100, 1) : 0.0;

        $sumaAnios = 0.0;
        $nAnios = 0;
        $sumaEdad = 0;
        $nEdad = 0;
        $mujeres = 0;
        $fuera = 0;
        foreach ($personas as $persona) {
            if ($persona['anios'] !== null) {
                $sumaAnios += (float) $persona['anios'];
                $nAnios++;
            }
            if ($persona['edad'] !== null) {
                $sumaEdad += (int) $persona['edad'];
                $nEdad++;
            }
            if ($persona['sexo'] === 'F') {
                $mujeres++;
            }
            if ($persona['convenio'] === 'Fuera de convenio') {
                $fuera++;
            }
        }

        $empresas = self::empresas($personas, $siglas, $presupuesto);
        $grupos = self::consolidado($personas, $siglas, $presupuesto);

        return [
            'corte' => $corte->toDateString(),
            'corte_label' => $corte->format('d/m/Y'),
            'anio' => $corte->year,
            'mes' => $corte->month,
            'mes_label' => EstructuraPersonalPresentacion::mesNombre($corte->month).' '.$corte->year,
            'periodo_bajas' => 'Ene–'.EstructuraPersonalPresentacion::mesCorto($corte->month).' '.$corte->year,
            'empresa_id' => (int) $facts['empresa_id'],
            'siglas' => $siglas,
            'tiene_presupuesto' => $tienePpto,
            'kpis' => [
                'dotacion' => $dotacion,
                'presupuesto' => $tienePpto ? $pptoTotal : null,
                'desvio' => $desvio,
                'bajas_anio' => count($bajas),
                'bajas_mes' => $bajasMes,
                'altas_mes' => count($altas),
                'antiguedad' => $nAnios > 0 ? round($sumaAnios / $nAnios, 1) : null,
                'edad' => $nEdad > 0 ? round($sumaEdad / $nEdad, 1) : null,
                'rotacion_mes' => $rotacionMes,
                'rotacion_anio' => $rotacionAnio,
                'mujeres_pct' => $dotacion > 0 ? round($mujeres * 100 / $dotacion, 1) : 0.0,
                'fuera_pct' => $dotacion > 0 ? round($fuera * 100 / $dotacion, 1) : 0.0,
            ],
            'empresas' => $empresas,
            'sectores' => self::sectores($personas, $dotacion),
            'consolidado' => $grupos,
            'bajas' => $bajas,
            'bajas_por_mes' => self::bajasPorMes($bajas, $corte->month),
            'motivos' => self::contar($bajas, 'motivo'),
            'altas' => $altas,
            'rotacion' => self::rotacion($personas, $bajas, $facts['inicio_anio_por_cc']),
            'composicion' => [
                'genero' => self::genero($personas),
                'convenio' => self::contar($personas, 'convenio'),
                'sindicato' => self::contar($personas, 'sindicato'),
                'edad' => self::tramos($personas, 'tramo_edad', [
                    'Hasta 24', '25 a 34', '35 a 44', '45 a 54', '55 o más', 'Sin dato',
                ]),
                'antiguedad' => self::tramos($personas, 'tramo_antiguedad', [
                    'Menos de 1 año', '1 a 3 años', '3 a 5 años', '5 a 10 años', '10 a 20 años', 'Más de 20 años', 'Sin dato',
                ]),
                'conduccion' => self::conduccion($personas, $siglas),
            ],
            'base' => $personas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<array<string, mixed>>  $bajas
     * @param  list<array<string, mixed>>  $altas
     * @param  array<string, int>  $presupuesto
     * @return list<string>
     */
    private static function siglas(array $personas, array $bajas, array $altas, array $presupuesto): array
    {
        $mapa = [];
        foreach ([$personas, $bajas, $altas] as $lista) {
            foreach ($lista as $fila) {
                $sigla = (string) ($fila['sigla'] ?? '');
                if ($sigla !== '') {
                    $mapa[$sigla] = true;
                }
            }
        }
        if ($mapa === [] && $presupuesto !== []) {
            $mapa['BSA'] = true;
        }
        $siglas = array_keys($mapa);
        usort($siglas, function (string $a, string $b): int {
            $orden = EstructuraPersonalPresentacion::ordenSigla($a) <=> EstructuraPersonalPresentacion::ordenSigla($b);

            return $orden !== 0 ? $orden : strcmp($a, $b);
        });

        return $siglas;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<string>  $siglas
     * @param  array<string, int>  $presupuesto
     * @return list<array<string, mixed>>
     */
    private static function empresas(array $personas, array $siglas, array $presupuesto): array
    {
        $nombres = [];
        $ids = [];
        $cuentas = [];
        foreach ($personas as $persona) {
            $sigla = (string) $persona['sigla'];
            $cuentas[$sigla] = ($cuentas[$sigla] ?? 0) + 1;
            $nombres[$sigla] = (string) $persona['empresa'];
            $ids[$sigla] = (int) $persona['empresa_id'];
        }
        $pptoPorSigla = [];
        foreach ($presupuesto as $clave => $cantidad) {
            [$empresaId] = array_pad(explode('|', $clave, 2), 2, '0');
            foreach ($ids as $sigla => $id) {
                if ($id === (int) $empresaId) {
                    $pptoPorSigla[$sigla] = ($pptoPorSigla[$sigla] ?? 0) + $cantidad;
                }
            }
        }
        $total = count($personas);
        $salida = [];
        foreach ($siglas as $sigla) {
            $dotacion = (int) ($cuentas[$sigla] ?? 0);
            $salida[] = [
                'id' => (int) ($ids[$sigla] ?? 0),
                'sigla' => $sigla,
                'nombre' => (string) ($nombres[$sigla] ?? $sigla),
                'dotacion' => $dotacion,
                'pct' => $total > 0 ? round($dotacion * 100 / $total, 1) : 0.0,
                'presupuesto' => array_key_exists($sigla, $pptoPorSigla) ? $pptoPorSigla[$sigla] : null,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @return list<array{nombre: string, dotacion: int, pct: float}>
     */
    private static function sectores(array $personas, int $total): array
    {
        $cuentas = [];
        foreach ($personas as $persona) {
            $nombre = (string) $persona['cc'];
            $cuentas[$nombre] = ($cuentas[$nombre] ?? 0) + 1;
        }
        $filas = [];
        foreach ($cuentas as $nombre => $dotacion) {
            $filas[] = [
                'nombre' => $nombre,
                'dotacion' => $dotacion,
                'pct' => $total > 0 ? round($dotacion * 100 / $total, 1) : 0.0,
            ];
        }
        usort($filas, fn (array $a, array $b): int => $b['dotacion'] <=> $a['dotacion'] ?: strcmp($a['nombre'], $b['nombre']));

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<string>  $siglas
     * @param  array<string, int>  $presupuesto
     * @return list<array<string, mixed>>
     */
    private static function consolidado(array $personas, array $siglas, array $presupuesto): array
    {
        $centros = [];
        foreach ($personas as $persona) {
            $ccId = (int) $persona['cc_id'];
            if (! isset($centros[$ccId])) {
                $centros[$ccId] = [
                    'cc_id' => $ccId,
                    'nombre' => (string) $persona['cc'],
                    'direccion_id' => (string) $persona['direccion_id'],
                    'direccion' => (string) $persona['direccion'],
                    'badge' => (string) $persona['badge'],
                    'orden' => EstructuraPersonalPresentacion::ordenCentro((string) $persona['cc']),
                    'por_sigla' => array_fill_keys($siglas, 0),
                    'ppto' => array_fill_keys($siglas, null),
                    'total' => 0,
                    'ppto_total' => null,
                ];
            }
            $sigla = (string) $persona['sigla'];
            if (! array_key_exists($sigla, $centros[$ccId]['por_sigla'])) {
                $centros[$ccId]['por_sigla'][$sigla] = 0;
                $centros[$ccId]['ppto'][$sigla] = null;
            }
            $centros[$ccId]['por_sigla'][$sigla]++;
            $centros[$ccId]['total']++;
        }

        $siglaPorEmpresa = [];
        foreach ($personas as $persona) {
            $siglaPorEmpresa[(int) $persona['empresa_id']] = (string) $persona['sigla'];
        }
        foreach ($presupuesto as $clave => $cantidad) {
            [$empresaId, $ccId] = array_pad(explode('|', $clave, 2), 2, '0');
            $ccId = (int) $ccId;
            $sigla = $siglaPorEmpresa[(int) $empresaId] ?? null;
            if ($sigla === null || ! isset($centros[$ccId])) {
                continue;
            }
            $previo = $centros[$ccId]['ppto'][$sigla];
            $centros[$ccId]['ppto'][$sigla] = (int) $previo + $cantidad;
            $centros[$ccId]['ppto_total'] = (int) $centros[$ccId]['ppto_total'] + $cantidad;
        }

        $gruposOrden = [
            'operaciones' => ['nombre' => 'Dirección de Operaciones', 'badge' => 'op'],
            'admin' => ['nombre' => 'Dirección Adm. y Finanzas', 'badge' => 'fin'],
            'otras' => ['nombre' => 'Sin centro de costo', 'badge' => 'svc'],
        ];
        $grupos = [];
        foreach ($centros as $centro) {
            $id = (string) $centro['direccion_id'];
            if (! isset($grupos[$id])) {
                $meta = $gruposOrden[$id] ?? ['nombre' => (string) $centro['direccion'], 'badge' => (string) $centro['badge']];
                $grupos[$id] = [
                    'id' => $id,
                    'nombre' => $meta['nombre'],
                    'badge' => $meta['badge'],
                    'filas' => [],
                    'por_sigla' => array_fill_keys($siglas, 0),
                    'ppto' => array_fill_keys($siglas, 0),
                    'ppto_alguno' => false,
                    'total' => 0,
                    'ppto_total' => 0,
                ];
            }
            $centro['variacion'] = $centro['ppto_total'] === null ? null : $centro['total'] - (int) $centro['ppto_total'];
            $grupos[$id]['filas'][] = $centro;
            $grupos[$id]['total'] += $centro['total'];
            foreach ($siglas as $sigla) {
                $grupos[$id]['por_sigla'][$sigla] += (int) ($centro['por_sigla'][$sigla] ?? 0);
                if ($centro['ppto'][$sigla] !== null) {
                    $grupos[$id]['ppto'][$sigla] += (int) $centro['ppto'][$sigla];
                    $grupos[$id]['ppto_alguno'] = true;
                }
            }
            if ($centro['ppto_total'] !== null) {
                $grupos[$id]['ppto_total'] += (int) $centro['ppto_total'];
            }
        }

        foreach ($grupos as &$grupo) {
            usort($grupo['filas'], fn (array $a, array $b): int => $a['orden'] <=> $b['orden'] ?: strcmp($a['nombre'], $b['nombre']));
            $grupo['variacion'] = $grupo['ppto_alguno'] ? $grupo['total'] - $grupo['ppto_total'] : null;
            if (! $grupo['ppto_alguno']) {
                $grupo['ppto_total'] = null;
            }
        }
        unset($grupo);

        $ordenGrupo = ['operaciones' => 0, 'admin' => 1, 'otras' => 2];
        uasort($grupos, fn (array $a, array $b): int => ($ordenGrupo[$a['id']] ?? 9) <=> ($ordenGrupo[$b['id']] ?? 9));

        return array_values($grupos);
    }

    /**
     * @param  list<array<string, mixed>>  $bajas
     * @return list<array{mes: int, label: string, cantidad: int}>
     */
    private static function bajasPorMes(array $bajas, int $hastaMes): array
    {
        $cuentas = array_fill(1, 12, 0);
        foreach ($bajas as $baja) {
            $mes = (int) $baja['mes'];
            if ($mes >= 1 && $mes <= 12) {
                $cuentas[$mes]++;
            }
        }
        $filas = [];
        for ($mes = 1; $mes <= $hastaMes; $mes++) {
            $filas[] = [
                'mes' => $mes,
                'label' => EstructuraPersonalPresentacion::mesCorto($mes),
                'cantidad' => $cuentas[$mes],
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{nombre: string, cantidad: int}>
     */
    private static function contar(array $filas, string $campo): array
    {
        $cuentas = [];
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila[$campo] ?? ''));
            if ($nombre === '') {
                $nombre = 'Sin dato';
            }
            $cuentas[$nombre] = ($cuentas[$nombre] ?? 0) + 1;
        }
        $salida = [];
        foreach ($cuentas as $nombre => $cantidad) {
            $salida[] = ['nombre' => $nombre, 'cantidad' => $cantidad];
        }
        usort($salida, fn (array $a, array $b): int => $b['cantidad'] <=> $a['cantidad'] ?: strcmp($a['nombre'], $b['nombre']));

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<string>  $orden
     * @return list<array{nombre: string, cantidad: int}>
     */
    private static function tramos(array $personas, string $campo, array $orden): array
    {
        $cuentas = array_fill_keys($orden, 0);
        foreach ($personas as $persona) {
            $nombre = (string) ($persona[$campo] ?? 'Sin dato');
            if (! array_key_exists($nombre, $cuentas)) {
                $cuentas[$nombre] = 0;
            }
            $cuentas[$nombre]++;
        }
        $salida = [];
        foreach ($cuentas as $nombre => $cantidad) {
            if ($cantidad === 0 && $nombre === 'Sin dato') {
                continue;
            }
            $salida[] = ['nombre' => $nombre, 'cantidad' => $cantidad];
        }

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @return array{m: int, f: int, nd: int, m_pct: float, f_pct: float, nd_pct: float}
     */
    private static function genero(array $personas): array
    {
        $m = 0;
        $f = 0;
        $nd = 0;
        foreach ($personas as $persona) {
            match ($persona['sexo']) {
                'M' => $m++,
                'F' => $f++,
                default => $nd++,
            };
        }
        $total = max(1, count($personas));

        return [
            'm' => $m,
            'f' => $f,
            'nd' => $nd,
            'm_pct' => round($m * 100 / $total, 1),
            'f_pct' => round($f * 100 / $total, 1),
            'nd_pct' => round($nd * 100 / $total, 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<array<string, mixed>>  $bajas
     * @param  array<int, int>  $inicioAnio
     * @return list<array<string, mixed>>
     */
    private static function rotacion(array $personas, array $bajas, array $inicioAnio): array
    {
        $fin = [];
        $meta = [];
        foreach ($personas as $persona) {
            $ccId = (int) $persona['cc_id'];
            $fin[$ccId] = ($fin[$ccId] ?? 0) + 1;
            $meta[$ccId] = [
                'nombre' => (string) $persona['cc'],
                'direccion' => (string) $persona['direccion'],
            ];
        }
        $bajasCc = [];
        foreach ($bajas as $baja) {
            $ccId = (int) $baja['cc_id'];
            $bajasCc[$ccId] = ($bajasCc[$ccId] ?? 0) + 1;
            $meta[$ccId] = $meta[$ccId] ?? [
                'nombre' => (string) $baja['cc'],
                'direccion' => (string) $baja['direccion'],
            ];
        }
        $ids = array_unique(array_merge(array_keys($fin), array_keys($bajasCc), array_keys($inicioAnio)));
        $filas = [];
        foreach ($ids as $ccId) {
            $dotacion = (int) ($fin[$ccId] ?? 0);
            $inicio = (int) ($inicioAnio[$ccId] ?? 0);
            $bajasN = (int) ($bajasCc[$ccId] ?? 0);
            $promedio = ($inicio + $dotacion) / 2;
            $filas[] = [
                'nombre' => $meta[$ccId]['nombre'] ?? 'Sin centro de costo',
                'direccion' => $meta[$ccId]['direccion'] ?? '',
                'dotacion' => $dotacion,
                'inicio_anio' => $inicio,
                'bajas' => $bajasN,
                'promedio' => round($promedio, 1),
                'indice' => $promedio > 0 ? round($bajasN / $promedio * 100, 1) : 0.0,
            ];
        }
        usort($filas, fn (array $a, array $b): int => $b['indice'] <=> $a['indice'] ?: $b['bajas'] <=> $a['bajas']);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $personas
     * @param  list<string>  $siglas
     * @return list<array<string, mixed>>
     */
    private static function conduccion(array $personas, array $siglas): array
    {
        $filas = [];
        foreach ($personas as $persona) {
            if (! EstructuraPersonalPresentacion::esConduccion((string) $persona['categoria'])) {
                continue;
            }
            $nombre = (string) $persona['categoria'];
            if (! isset($filas[$nombre])) {
                $filas[$nombre] = [
                    'nombre' => $nombre,
                    'por_sigla' => array_fill_keys($siglas, 0),
                    'total' => 0,
                ];
            }
            $sigla = (string) $persona['sigla'];
            if (! array_key_exists($sigla, $filas[$nombre]['por_sigla'])) {
                $filas[$nombre]['por_sigla'][$sigla] = 0;
            }
            $filas[$nombre]['por_sigla'][$sigla]++;
            $filas[$nombre]['total']++;
        }
        $salida = array_values($filas);
        usort($salida, fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['nombre'], $b['nombre']));

        return $salida;
    }
}
