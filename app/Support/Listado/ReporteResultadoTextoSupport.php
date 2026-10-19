<?php

declare(strict_types=1);

namespace App\Support\Listado;

use Illuminate\Http\Request;

/**
 * Filtro de texto sobre filas ya armadas de reportes (estilo índices CRUD).
 */
final class ReporteResultadoTextoSupport
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, string> */
    public const OPERADORES = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /**
     * @return array{
     *   filtro_modo: string,
     *   filtro_campo: string,
     *   filtro_operador: string,
     *   filtro_valor: string,
     *   filtro_busqueda_rapida: bool
     * }
     */
    public static function resolverParams(Request $request, string $campoDefault, array $camposKeys): array
    {
        $valor = FiltrosListadoRequest::valorBusqueda($request);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');
        $modo = trim((string) $request->input('filtro_modo', self::MODO_TODOS));
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = trim((string) $request->input('filtro_campo', $campoDefault));
        if (! in_array($campo, $camposKeys, true)) {
            $campo = $campoDefault;
        }
        $operador = self::normalizarOperador((string) $request->input('filtro_operador', 'contiene'));
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        return [
            'filtro_modo' => $modo,
            'filtro_campo' => $campo,
            'filtro_operador' => $operador,
            'filtro_valor' => $valor,
            'filtro_busqueda_rapida' => $busquedaRapida,
        ];
    }

    /**
     * Compatibilidad mayor plano: también lee filtro_texto legado.
     *
     * @return array{
     *   filtro_modo: string,
     *   filtro_campo: string,
     *   filtro_operador: string,
     *   filtro_valor: string,
     *   filtro_busqueda_rapida: bool
     * }
     */
    public static function resolverParamsConLegadoTexto(
        Request $request,
        string $campoDefault,
        array $camposKeys
    ): array {
        $params = self::resolverParams($request, $campoDefault, $camposKeys);
        if ($params['filtro_valor'] === '' && $request->filled('filtro_texto')) {
            $params['filtro_valor'] = trim((string) $request->input('filtro_texto'));
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            return true;
        }

        return ($filtros['filtro_operador'] ?? '') === 'vacio';
    }

    /**
     * @param  array<string, mixed>  $out
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function mergeParaQueryString(array $out, array $filtros): array
    {
        if (! self::tieneAplicados($filtros)) {
            return $out;
        }

        $out['filtro_modo'] = $filtros['filtro_modo'] ?? self::MODO_TODOS;
        $out['filtro_campo'] = $filtros['filtro_campo'] ?? '';
        $out['filtro_operador'] = $filtros['filtro_operador'] ?? 'contiene';
        $out['filtro_valor'] = trim((string) ($filtros['filtro_valor'] ?? ''));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function sinTexto(array $filtros): array
    {
        $copia = $filtros;
        $copia['filtro_valor'] = '';
        $copia['filtro_operador'] = 'contiene';
        $copia['filtro_busqueda_rapida'] = false;
        unset($copia['filtro_texto']);

        return $copia;
    }

    public static function normalizarOperador(string $operador): string
    {
        return isset(self::OPERADORES[$operador]) ? $operador : 'contiene';
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array{prop: string|\Closure, label: string, flexible?: bool}>  $campos
     * @param  array{
     *   tipo_key?: string,
     *   detalle_tipos?: list<string>,
     *   group_key?: string|null,
     *   header_tipos?: list<string>,
     *   siempre_conservar_tipos?: list<string>
     * }  $opciones
     * @return list<array<string, mixed>>
     */
    public static function filtrarFilas(array $filas, array $filtros, array $campos, array $opciones = []): array
    {
        if (! self::tieneAplicados($filtros)) {
            return $filas;
        }

        $tipoKey = $opciones['tipo_key'] ?? 'tipo';
        $detalleTipos = $opciones['detalle_tipos'] ?? ['detalle', 'movimiento', 'aplicacion'];
        $groupKey = $opciones['group_key'] ?? null;
        $headerTipos = $opciones['header_tipos'] ?? ['header_empresa'];
        $siempre = $opciones['siempre_conservar_tipos'] ?? [];

        $matchingGroups = [];
        $keepDetalleSinGrupo = [];

        foreach ($filas as $idx => $fila) {
            $tipo = (string) ($fila[$tipoKey] ?? '');
            if ($siempre !== [] && in_array($tipo, $siempre, true)) {
                continue;
            }
            if ($detalleTipos !== [] && ! in_array($tipo, $detalleTipos, true)
                && $groupKey !== null
                && empty($fila[$groupKey])
            ) {
                // Cabeceras / totales: se deciden después.
                if (! self::filaCoincide($fila, $filtros, $campos)) {
                    continue;
                }
            }

            if (! self::filaCoincide($fila, $filtros, $campos)) {
                continue;
            }

            if ($groupKey !== null && (int) ($fila[$groupKey] ?? 0) > 0) {
                $matchingGroups[(int) $fila[$groupKey]] = true;
            } else {
                $keepDetalleSinGrupo[$idx] = true;
            }
        }

        if ($matchingGroups === [] && $keepDetalleSinGrupo === []) {
            return [];
        }

        if ($groupKey === null) {
            return self::filtrarPlanoConHeaders(
                $filas,
                $filtros,
                $campos,
                $tipoKey,
                $detalleTipos,
                $headerTipos,
                $siempre
            );
        }

        $out = [];
        $i = 0;
        $n = count($filas);
        while ($i < $n) {
            $fila = $filas[$i];
            $tipo = (string) ($fila[$tipoKey] ?? '');

            if (in_array($tipo, $headerTipos, true)) {
                $j = $i + 1;
                $bloque = [$fila];
                $incluye = false;
                while ($j < $n && ! in_array((string) ($filas[$j][$tipoKey] ?? ''), $headerTipos, true)) {
                    $bloque[] = $filas[$j];
                    $gid = (int) ($filas[$j][$groupKey] ?? 0);
                    if ($gid > 0 && isset($matchingGroups[$gid])) {
                        $incluye = true;
                    }
                    $j++;
                }
                if ($incluye) {
                    foreach ($bloque as $idx => $row) {
                        if ($idx === 0) {
                            $out[] = $row;
                            continue;
                        }
                        $gid = (int) ($row[$groupKey] ?? 0);
                        $t = (string) ($row[$tipoKey] ?? '');
                        if (in_array($t, $siempre, true) || ($gid > 0 && isset($matchingGroups[$gid]))) {
                            $out[] = $row;
                        }
                    }
                }
                $i = $j;
                continue;
            }

            $gid = (int) ($fila[$groupKey] ?? 0);
            if ($gid > 0 && isset($matchingGroups[$gid])) {
                $out[] = $fila;
            } elseif (in_array($tipo, $siempre, true)) {
                $out[] = $fila;
            } elseif (isset($keepDetalleSinGrupo[$i])) {
                $out[] = $fila;
            }
            $i++;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array{prop: string|\Closure, label: string, flexible?: bool}>  $campos
     * @param  list<string>  $detalleTipos
     * @param  list<string>  $headerTipos
     * @param  list<string>  $siempre
     * @return list<array<string, mixed>>
     */
    private static function filtrarPlanoConHeaders(
        array $filas,
        array $filtros,
        array $campos,
        string $tipoKey,
        array $detalleTipos,
        array $headerTipos,
        array $siempre
    ): array {
        $matchIdx = [];
        foreach ($filas as $idx => $fila) {
            $tipo = (string) ($fila[$tipoKey] ?? '');
            if ($headerTipos !== [] && in_array($tipo, $headerTipos, true)) {
                continue;
            }
            if ($detalleTipos !== [] && ! in_array($tipo, $detalleTipos, true)
                && ! in_array($tipo, $siempre, true)
            ) {
                if (! self::filaCoincide($fila, $filtros, $campos)) {
                    continue;
                }
            }
            if (self::filaCoincide($fila, $filtros, $campos) || in_array($tipo, $siempre, true)) {
                $matchIdx[$idx] = true;
            }
        }

        if ($matchIdx === []) {
            return [];
        }

        $out = [];
        $i = 0;
        $n = count($filas);
        while ($i < $n) {
            $fila = $filas[$i];
            $tipo = (string) ($fila[$tipoKey] ?? '');
            if (in_array($tipo, $headerTipos, true)) {
                $j = $i + 1;
                $hay = false;
                while ($j < $n && ! in_array((string) ($filas[$j][$tipoKey] ?? ''), $headerTipos, true)) {
                    if (isset($matchIdx[$j])) {
                        $hay = true;
                    }
                    $j++;
                }
                if ($hay) {
                    $out[] = $fila;
                    for ($k = $i + 1; $k < $j; $k++) {
                        if (isset($matchIdx[$k])) {
                            $out[] = $filas[$k];
                        }
                    }
                }
                $i = $j;
                continue;
            }
            if (isset($matchIdx[$i])) {
                $out[] = $fila;
            }
            $i++;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array{prop: string|\Closure, label: string, flexible?: bool}>  $campos
     */
    public static function filaCoincide(array $fila, array $filtros, array $campos): bool
    {
        $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        $modo = (string) ($filtros['filtro_modo'] ?? self::MODO_TODOS);

        if ($operador !== 'vacio' && $valor === '') {
            return true;
        }

        $keys = $modo === self::MODO_CAMPO
            ? [((string) ($filtros['filtro_campo'] ?? ''))]
            : array_keys($campos);

        foreach ($keys as $campo) {
            if (! isset($campos[$campo])) {
                continue;
            }
            $texto = self::valorCampo($fila, $campos[$campo]['prop']);
            $flexible = ! empty($campos[$campo]['flexible']);
            if (self::textoCoincide($texto, $operador, $valor, $flexible)) {
                return true;
            }
        }

        return false;
    }

    private static function valorCampo(array $fila, string|\Closure $prop): string
    {
        if ($prop instanceof \Closure) {
            $v = $prop($fila);
        } else {
            $v = $fila[$prop] ?? null;
        }
        if ($v === null) {
            return '';
        }
        if (is_float($v) || is_int($v)) {
            return (string) $v;
        }

        return trim((string) $v);
    }

    private static function textoCoincide(string $texto, string $operador, string $valor, bool $flexible): bool
    {
        if ($operador === 'vacio') {
            return $texto === '';
        }

        $hay = mb_strtolower($texto);
        $bus = mb_strtolower($valor);

        return match ($operador) {
            'igual' => $hay === $bus,
            'distinto' => $hay !== $bus,
            'empieza' => $bus === '' || str_starts_with($hay, $bus),
            'termina' => $bus === '' || str_ends_with($hay, $bus),
            default => self::contieneFlexible($hay, $bus, $flexible),
        };
    }

    private static function contieneFlexible(string $haystack, string $needle, bool $flexible): bool
    {
        if ($needle === '') {
            return true;
        }
        if (mb_strpos($haystack, $needle) !== false) {
            return true;
        }
        if (! $flexible || mb_strlen($needle) < CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT) {
            return false;
        }

        $pref = mb_substr($needle, 0, 3);
        $longitudSufijo = mb_strlen($needle) >= 8 ? 5 : 4;
        $suf = mb_substr($needle, -$longitudSufijo);
        if ($pref === '' || $suf === '' || $pref === $suf) {
            return false;
        }

        return mb_strpos($haystack, $pref) !== false && mb_strpos($haystack, $suf) !== false;
    }
}
