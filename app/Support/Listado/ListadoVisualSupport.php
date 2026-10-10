<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Models\Admin\Rol;
use App\Models\Listado\ListadoVista;
use Illuminate\Http\Request;

/**
 * Gráfico, color de fila y clic sobre la barra, compartido por las grillas del workbench.
 */
final class ListadoVisualSupport
{
    /** @var list<string> */
    public const TIPOS = ['barras', 'linea', 'torta'];

    /** @var list<string> */
    public const TONOS = ['danger', 'warning', 'success'];

    /**
     * @param  array<string, array{column?: string, label?: string}>  $campos
     * @return array{tipo: string, dimension: string, medida: string}
     */
    public static function normalizarGrafico(mixed $raw, array $campos): array
    {
        $raw = is_array($raw) ? $raw : [];
        $tipo = (string) ($raw['tipo'] ?? '');
        $dimension = (string) ($raw['dimension'] ?? '');
        $medida = (string) ($raw['medida'] ?? 'conteo');
        $ejes = $campos;
        unset($ejes['monto'], $ejes['total'], $ejes['ingresos'], $ejes['egresos'], $ejes['tiempo_insumido']);
        if (! in_array($tipo, self::TIPOS, true) || ! isset($ejes[$dimension])) {
            return self::graficoVacio();
        }
        $medidas = self::medidasDisponibles($campos);
        if (! isset($medidas[$medida])) {
            $medida = 'conteo';
        }

        return [
            'tipo' => $tipo,
            'dimension' => $dimension,
            'medida' => $medida,
        ];
    }

    /**
     * @return array{tipo: string, dimension: string, medida: string}
     */
    public static function graficoVacio(): array
    {
        return ['tipo' => '', 'dimension' => '', 'medida' => 'conteo'];
    }

    /**
     * @param  array<string, array{label?: string}>  $campos
     * @return array<string, string>
     */
    public static function medidasDisponibles(array $campos): array
    {
        $out = ['conteo' => 'Cantidad'];
        foreach (['monto' => 'Suma de monto', 'total' => 'Suma de total', 'ingresos' => 'Ingresos', 'egresos' => 'Egresos'] as $key => $label) {
            if (isset($campos[$key])) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array{label?: string}>  $campos
     * @return list<array{campo: string, op: string, valor: string, tono: string}>
     */
    public static function normalizarFormato(mixed $raw, array $campos): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $fila) {
            if (count($out) >= 4 || ! is_array($fila)) {
                continue;
            }
            $campo = (string) ($fila['campo'] ?? '');
            $valor = trim((string) ($fila['valor'] ?? ''));
            $tono = (string) ($fila['tono'] ?? 'warning');
            if ($valor === '' || ! isset($campos[$campo]) || ! in_array($tono, self::TONOS, true)) {
                continue;
            }
            $out[] = [
                'campo' => $campo,
                'op' => ((string) ($fila['op'] ?? '')) === 'contiene' ? 'contiene' : 'igual',
                'valor' => mb_substr($valor, 0, 80),
                'tono' => $tono,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $vistaJson
     * @param  array<string, array{column?: string, label?: string}>  $campos
     * @return array<string, mixed>
     */
    public static function aplicarPedido(array $filtros, Request $request, ?array $vistaJson, array $campos): array
    {
        $listaExplicita = $request->exists('graficos');
        $pidio = $listaExplicita || $request->exists('grafico') || $request->boolean('grafico_off');
        if ($pidio) {
            $graficos = ListadoLienzoSupport::normalizar(
                $request->input('graficos'),
                $request->input('grafico'),
                $listaExplicita,
                $campos
            );
            $tipoUnico = trim((string) data_get($request->input('grafico'), 'tipo', ''));
            $apagado = $request->boolean('grafico_off')
                || ($listaExplicita && $graficos === [])
                || (! $listaExplicita && $request->exists('grafico') && $tipoUnico === '');
            if ($apagado) {
                $filtros['graficos'] = [];
                $filtros['grafico'] = self::graficoVacio();
                $filtros['grafico_off'] = true;
            } else {
                $filtros['graficos'] = $graficos;
                $filtros['grafico'] = ListadoLienzoSupport::primero($graficos);
                $filtros['grafico_off'] = false;
            }
        } elseif (is_array($vistaJson)) {
            $guardados = is_array($vistaJson['graficos'] ?? null) ? $vistaJson['graficos'] : [];
            $graficos = ListadoLienzoSupport::normalizar(
                $guardados,
                $vistaJson['grafico'] ?? ($filtros['grafico'] ?? []),
                $guardados !== [],
                $campos
            );
            $filtros['graficos'] = $graficos;
            $filtros['grafico'] = ListadoLienzoSupport::primero($graficos);
            $filtros['grafico_off'] = $graficos === [];
        } else {
            $graficos = ListadoLienzoSupport::normalizar(null, $filtros['grafico'] ?? [], false, $campos);
            $filtros['graficos'] = $graficos;
            $filtros['grafico'] = ListadoLienzoSupport::primero($graficos);
            $filtros['grafico_off'] = $graficos === [];
        }

        if ($request->exists('formato')) {
            $filtros['formato'] = self::normalizarFormato($request->input('formato'), $campos);
        } elseif (is_array($vistaJson)) {
            $filtros['formato'] = self::normalizarFormato($vistaJson['formato'] ?? ($filtros['formato'] ?? []), $campos);
        } else {
            $filtros['formato'] = self::normalizarFormato($filtros['formato'] ?? [], $campos);
        }

        $click = trim((string) $request->input('grafico_click', ''));
        $filtros['grafico_click'] = $click;
        $dimension = trim((string) $request->input('grafico_click_dimension', ''));
        $ejes = $campos;
        unset($ejes['monto'], $ejes['total'], $ejes['ingresos'], $ejes['egresos'], $ejes['tiempo_insumido']);
        if ($dimension === '' || ! isset($ejes[$dimension])) {
            $dimension = (string) ($filtros['grafico']['dimension'] ?? '');
        }
        $filtros['grafico_click_dimension'] = '';
        if ($click !== '' && $dimension !== '' && isset($ejes[$dimension])) {
            $filtros['grafico_click_dimension'] = $dimension;
            $filtros['qbe'] = self::agregarCriterioIgual(
                is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [],
                $dimension,
                $click
            );
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $qbe
     * @return array<string, mixed>
     */
    public static function agregarCriterioIgual(array $qbe, string $campo, string $valor): array
    {
        if (! isset($qbe['grupos']) || ! is_array($qbe['grupos'])) {
            $qbe = [
                'entre_grupos' => 'and',
                'grupos' => [],
            ];
        }
        foreach ($qbe['grupos'] as $grupo) {
            $criterios = is_array($grupo['criterios'] ?? null) ? $grupo['criterios'] : [];
            if (count($criterios) !== 1 || ! is_array($criterios[0])) {
                continue;
            }
            if (($criterios[0]['campo'] ?? '') === $campo
                && ($criterios[0]['op'] ?? '') === 'igual'
                && (string) ($criterios[0]['valor'] ?? '') === $valor) {
                return $qbe;
            }
        }
        $qbe['entre_grupos'] = $qbe['entre_grupos'] ?? 'and';
        $qbe['grupos'][] = [
            'logic' => 'and',
            'not' => false,
            'criterios' => [[
                'campo' => $campo,
                'op' => 'igual',
                'valor' => $valor,
                'valor_hasta' => '',
            ]],
        ];

        return $qbe;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $graficos = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        if (! empty($filtros['grafico_off'])) {
            $out['grafico_off'] = 1;
        } elseif ($graficos !== []) {
            $out['graficos'] = $graficos;
            $out['grafico'] = $graficos[0];
        } elseif (($filtros['grafico']['tipo'] ?? '') !== '') {
            $out['grafico'] = $filtros['grafico'];
        }
        foreach ($filtros['formato'] ?? [] as $i => $fila) {
            if (is_array($fila)) {
                $out['formato'][$i] = $fila;
            }
        }
        if (trim((string) ($filtros['grafico_click'] ?? '')) !== '') {
            $out['grafico_click'] = $filtros['grafico_click'];
        }
        if (trim((string) ($filtros['grafico_click_dimension'] ?? '')) !== '') {
            $out['grafico_click_dimension'] = $filtros['grafico_click_dimension'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cortes
     * @param  array<string, string>  $etiquetas
     * @return array{tipo: string, dimension: string, medida: string, labels: list<string>, valores: list<float>, series: list<array{nombre: string, valores: list<float>}>, titulo: string, partido_moneda: bool}
     */
    public static function serie(array $grafico, array $cortes, array $etiquetas): array
    {
        $medida = (string) ($grafico['medida'] ?? 'conteo');
        $dimension = (string) ($grafico['dimension'] ?? '');
        $tituloEje = $etiquetas[$dimension] ?? $dimension;
        $tituloMedida = match ($medida) {
            'conteo' => 'Cantidad',
            'tiempo_insumido' => 'Suma de minutos',
            default => 'Suma de '.$medida,
        };
        $partido = count($grafico['agrupar_extra'] ?? []) > 0;
        $hojas = [];
        foreach ($cortes['filas'] ?? [] as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $nivel = (int) ($fila['nivel'] ?? 0);
            if ($partido && $nivel === 0 && isset($fila['parent'])) {
                continue;
            }
            if ($partido && $nivel === 0 && count($grafico['agrupar_extra'] ?? []) > 0) {
                continue;
            }
            $path = $fila['path'] ?? [(string) ($fila['valor'] ?? '')];
            if (! is_array($path) || $path === []) {
                continue;
            }
            if ($partido && count($path) < 2) {
                continue;
            }
            $eje = (string) ($path[0] ?? '');
            $moneda = $partido ? (string) ($path[1] ?? '') : '';
            $valor = $medida === 'conteo'
                ? (float) ($fila['count'] ?? 0)
                : (float) ($fila['sumas'][$medida] ?? 0);
            $hojas[] = [$eje, $moneda, $valor];
        }
        if (! $partido) {
            $puntos = [];
            foreach ($cortes['filas'] ?? [] as $fila) {
                if (! is_array($fila) || (int) ($fila['nivel'] ?? 0) > 0) {
                    continue;
                }
                $valor = $medida === 'conteo'
                    ? (float) ($fila['count'] ?? 0)
                    : (float) ($fila['sumas'][$medida] ?? 0);
                $puntos[] = [(string) ($fila['valor'] ?? ''), $valor];
            }
            if ($puntos === []) {
                foreach ($hojas as $h) {
                    $puntos[] = [$h[0], $h[2]];
                }
            }
            usort($puntos, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
            $puntos = array_slice($puntos, 0, 12);

            return [
                'tipo' => (string) ($grafico['tipo'] ?? ''),
                'dimension' => $dimension,
                'medida' => $medida,
                'labels' => array_column($puntos, 0),
                'valores' => array_map(static fn (array $p): float => $p[1], $puntos),
                'series' => [[
                    'nombre' => $tituloMedida,
                    'valores' => array_map(static fn (array $p): float => $p[1], $puntos),
                ]],
                'titulo' => $tituloMedida.' por '.$tituloEje,
                'partido_moneda' => false,
            ];
        }

        if (($grafico['tipo'] ?? '') === 'torta') {
            $puntos = [];
            foreach ($hojas as [$eje, $moneda, $valor]) {
                $puntos[] = [$moneda !== '' ? $eje.' · '.$moneda : $eje, $valor];
            }
            usort($puntos, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
            $puntos = array_slice($puntos, 0, 12);

            return [
                'tipo' => 'torta',
                'dimension' => $dimension,
                'medida' => $medida,
                'labels' => array_column($puntos, 0),
                'valores' => array_map(static fn (array $p): float => $p[1], $puntos),
                'series' => [[
                    'nombre' => $tituloMedida,
                    'valores' => array_map(static fn (array $p): float => $p[1], $puntos),
                ]],
                'titulo' => $tituloMedida.' por '.$tituloEje.', separado por moneda',
                'partido_moneda' => true,
            ];
        }

        $totalesEje = [];
        $monedas = [];
        foreach ($hojas as [$eje, $moneda, $valor]) {
            $totalesEje[$eje] = ($totalesEje[$eje] ?? 0) + $valor;
            $monedas[$moneda] = true;
        }
        arsort($totalesEje);
        $labels = array_slice(array_keys($totalesEje), 0, 12);
        $series = [];
        foreach (array_keys($monedas) as $moneda) {
            $valores = [];
            foreach ($labels as $eje) {
                $acum = 0.0;
                foreach ($hojas as [$e, $m, $v]) {
                    if ($e === $eje && $m === $moneda) {
                        $acum += $v;
                    }
                }
                $valores[] = $acum;
            }
            $series[] = [
                'nombre' => $moneda !== '' ? $moneda : '(sin moneda)',
                'valores' => $valores,
            ];
        }

        return [
            'tipo' => (string) ($grafico['tipo'] ?? ''),
            'dimension' => $dimension,
            'medida' => $medida,
            'labels' => $labels,
            'valores' => $series[0]['valores'] ?? [],
            'series' => $series,
            'titulo' => $tituloMedida.' por '.$tituloEje.', separado por moneda',
            'partido_moneda' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function qbeSinClick(array $filtros, string $dimension): array
    {
        $qbe = is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [];
        $click = trim((string) ($filtros['grafico_click'] ?? ''));
        if ($click === '' || ! isset($qbe['grupos']) || ! is_array($qbe['grupos'])) {
            return $qbe;
        }
        $qbe['grupos'] = array_values(array_filter(
            $qbe['grupos'],
            static function ($grupo) use ($click, $dimension): bool {
                if (! is_array($grupo)) {
                    return true;
                }
                $criterios = is_array($grupo['criterios'] ?? null) ? $grupo['criterios'] : [];
                if (count($criterios) !== 1 || ! is_array($criterios[0])) {
                    return true;
                }

                return ! (($criterios[0]['campo'] ?? '') === $dimension
                    && ($criterios[0]['op'] ?? '') === 'igual'
                    && (string) ($criterios[0]['valor'] ?? '') === $click);
            }
        ));

        return $qbe;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array{column?: string, label?: string}>  $campos
     * @return array<string, mixed>|null
     */
    public static function filtrosParaCortes(array $filtros, array $campos): ?array
    {
        $grafico = self::normalizarGrafico($filtros['grafico'] ?? [], $campos);
        if ($grafico['tipo'] === '') {
            return null;
        }
        $out = $filtros;
        $out['qbe'] = self::qbeSinClick($filtros, $grafico['dimension']);
        $out['agrupar'] = [$grafico['dimension']];
        if (ListadoMedidaSupport::partirPorMoneda($grafico['medida'], $grafico['dimension'], $campos)) {
            $out['agrupar'][] = 'moneda';
            $grafico['agrupar_extra'] = ['moneda'];
        }
        $out['grafico'] = $grafico;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array{column?: string, label?: string}>  $campos
     * @param  array<string, string>  $etiquetas
     * @param  callable(array<string, mixed>): array<string, mixed>  $cortes
     * @return array{tipo: string, dimension: string, medida: string, labels: list<string>, valores: list<float>, series: list<array{nombre: string, valores: list<float>}>, titulo: string, partido_moneda: bool}
     */
    public static function serieDeConsulta(array $filtros, array $campos, array $etiquetas, callable $cortes): array
    {
        $para = self::filtrosParaCortes($filtros, $campos);
        if ($para === null) {
            return self::serie(self::graficoVacio(), ['filas' => []], $etiquetas);
        }
        $filas = $cortes($para);

        return self::serie($para['grafico'], is_array($filas) ? $filas : ['filas' => []], $etiquetas);
    }

    public static function tonoFila(object $fila, array $reglas, callable $valorCelda): ?string
    {
        foreach (self::normalizarFormato($reglas, self::camposDesdeReglas($reglas)) as $regla) {
            $actual = mb_strtolower(trim((string) $valorCelda($fila, $regla['campo'])));
            $esperado = mb_strtolower($regla['valor']);
            $coincide = $regla['op'] === 'contiene'
                ? ($esperado !== '' && str_contains($actual, $esperado))
                : $actual === $esperado;
            if ($coincide) {
                return $regla['tono'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $reglas
     * @return array<string, array{label: string}>
     */
    private static function camposDesdeReglas(array $reglas): array
    {
        $campos = [];
        foreach ($reglas as $regla) {
            if (is_array($regla) && isset($regla['campo'])) {
                $campos[(string) $regla['campo']] = ['label' => (string) $regla['campo']];
            }
        }

        return $campos;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Rol>
     */
    public static function rolesParaInstalacion()
    {
        if ((string) session('rol_nombre') !== 'administrador' || ! ListadoVistaSupport::columnaRolDisponible()) {
            return collect();
        }

        return Rol::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    public static function asignarRol(ListadoVista $vista, Request $request, string $recurso): void
    {
        if ((string) session('rol_nombre') !== 'administrador' || ! ListadoVistaSupport::columnaRolDisponible()) {
            return;
        }
        $rolId = (int) $request->input('rol_id', 0);
        if ($rolId > 0 && ! Rol::query()->whereKey($rolId)->exists()) {
            return;
        }
        if ($rolId > 0) {
            ListadoVista::query()
                ->where('recurso', $recurso)
                ->where('rol_id', $rolId)
                ->where('id', '!=', $vista->id)
                ->get()
                ->each(function (ListadoVista $otra): void {
                    $otra->rol_id = null;
                    $otra->save();
                });
        }
        $vista->rol_id = $rolId > 0 ? $rolId : null;
        $vista->save();
    }
}
