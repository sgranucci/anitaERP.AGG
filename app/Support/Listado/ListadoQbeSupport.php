<?php

declare(strict_types=1);

namespace App\Support\Listado;

use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * QBE multi-grupo estilo Dynamics Advanced Find / NetSuite / SAP P13n Filter.
 *
 * Forma canónica:
 * [
 *   'entre_grupos' => 'and'|'or',
 *   'grupos' => [
 *     ['logic' => 'and'|'or', 'not' => bool, 'criterios' => list<{campo,op,valor,valor_hasta?}>],
 *   ],
 * ]
 *
 * Legacy (lista plana de criterios) se normaliza a un solo grupo AND.
 */
final class ListadoQbeSupport
{
    public const LOGIC_AND = 'and';

    public const LOGIC_OR = 'or';

    public const MAX_GRUPOS = 8;

    public const MAX_CRITERIOS_POR_GRUPO = 12;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = [
        'mayor_igual' => 'Desde',
        'menor_igual' => 'Hasta',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
        'igual' => 'Igual a',
        'entre' => 'Entre',
        'vacio' => 'Vacío',
    ];

    /**
     * Períodos sin valor (etapa de transacciones: el criterio es el período, no una fecha tipeada).
     *
     * @var array<string, string>
     */
    public const OPERADORES_PERIODO = [
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        'esta_semana' => 'Esta semana',
        'este_mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        'este_anio' => 'Este año',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = [
        'mayor_igual' => 'Desde',
        'menor_igual' => 'Hasta',
        'mayor' => 'Posterior a',
        'menor' => 'Anterior a',
        'igual' => 'Es el día',
        'entre' => 'Entre',
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        'esta_semana' => 'Esta semana',
        'este_mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        'este_anio' => 'Este año',
        'vacio' => 'Sin fecha',
    ];

    /**
     * @param  array<string, array{column?: string, type?: string, label?: string}>  $campos
     * @param  callable(string $op, string $campo): string  $normalizarOperador
     * @return array{entre_grupos: string, grupos: list<array{logic: string, not: bool, criterios: list<array{campo: string, op: string, valor: string, valor_hasta?: string, formula?: string}>}>}
     */
    public static function resolverDesdeRequest(
        Request $request,
        array $campos,
        callable $normalizarOperador
    ): array {
        $raw = $request->input('qbe', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        return self::normalizar($raw, $campos, $normalizarOperador);
    }

    /**
     * @param  mixed  $raw
     * @param  array<string, array{column?: string, type?: string, label?: string}>  $campos
     * @param  callable(string $op, string $campo): string  $normalizarOperador
     * @return array{entre_grupos: string, grupos: list<array{logic: string, not: bool, criterios: list<array{campo: string, op: string, valor: string, valor_hasta?: string}>}>}
     */
    public static function normalizar(mixed $raw, array $campos, callable $normalizarOperador): array
    {
        $vacio = self::vacio();
        if (! is_array($raw) || $raw === []) {
            return $vacio;
        }

        // Forma canónica con grupos
        if (isset($raw['grupos']) && is_array($raw['grupos'])) {
            return self::normalizarConGrupos($raw, $campos, $normalizarOperador);
        }

        // Legacy: lista de criterios {campo,op,valor}
        $criterios = self::normalizarCriteriosPlanos($raw, $campos, $normalizarOperador);
        if ($criterios === []) {
            return $vacio;
        }

        return [
            'entre_grupos' => self::LOGIC_AND,
            'grupos' => [[
                'logic' => self::LOGIC_AND,
                'not' => false,
                'criterios' => $criterios,
            ]],
        ];
    }

    /**
     * @return array{entre_grupos: string, grupos: list}
     */
    public static function vacio(): array
    {
        return [
            'entre_grupos' => self::LOGIC_AND,
            'grupos' => [],
        ];
    }

    /**
     * @param  array{entre_grupos?: string, grupos?: list}|list  $qbe
     */
    public static function tieneCriterios(array $qbe): bool
    {
        $norm = self::esFormaCanonica($qbe)
            ? $qbe
            : ['entre_grupos' => self::LOGIC_AND, 'grupos' => []];

        if (! self::esFormaCanonica($qbe)) {
            // Puede venir legacy sin normalizar
            foreach ($qbe as $criterio) {
                if (is_array($criterio) && isset($criterio['campo'])) {
                    $op = (string) ($criterio['op'] ?? '');
                    $valor = trim((string) ($criterio['valor'] ?? ''));
                    if (self::operadorSinValor($op) || $valor !== '') {
                        return true;
                    }
                } elseif (! is_array($criterio) && trim((string) $criterio) !== '') {
                    return true;
                }
            }

            return false;
        }

        foreach ((array) ($norm['grupos'] ?? []) as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }
            foreach ((array) ($grupo['criterios'] ?? []) as $criterio) {
                if (! is_array($criterio)) {
                    continue;
                }
                $op = (string) ($criterio['op'] ?? '');
                $valor = trim((string) ($criterio['valor'] ?? ''));
                $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
                $formula = trim((string) ($criterio['formula'] ?? ''));
                if (self::operadorSinValor($op) || $op === 'entre' || $valor !== '' || $hasta !== '' || $formula !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{entre_grupos: string, grupos: list}  $qbe
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $qbe): array
    {
        if (! self::tieneCriterios($qbe) || ! self::esFormaCanonica($qbe)) {
            return [];
        }

        $gruposOut = [];
        $gi = 0;
        foreach ($qbe['grupos'] as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }
            $criteriosOut = [];
            $ci = 0;
            foreach ((array) ($grupo['criterios'] ?? []) as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $campo = (string) ($c['campo'] ?? '');
                $op = (string) ($c['op'] ?? 'contiene');
                $valor = trim((string) ($c['valor'] ?? ''));
                $hasta = trim((string) ($c['valor_hasta'] ?? ''));
                $formula = trim((string) ($c['formula'] ?? ''));
                if ($campo === '' && $formula === '') {
                    continue;
                }
                if ($formula === '' && ! self::operadorSinValor($op) && $op !== 'entre' && $valor === '') {
                    continue;
                }
                if ($formula !== '' && $op !== 'vacio' && $op !== 'entre' && $valor === '') {
                    continue;
                }
                if ($op === 'entre' && $valor === '' && $hasta === '') {
                    continue;
                }
                $fila = [
                    'campo' => $campo !== '' ? $campo : ListadoQbeFormulaSupport::CAMPO_KEY,
                    'op' => $op,
                    'valor' => $valor,
                ];
                if ($hasta !== '') {
                    $fila['valor_hasta'] = $hasta;
                }
                if ($formula !== '') {
                    $fila['formula'] = $formula;
                }
                $criteriosOut[$ci++] = $fila;
            }
            if ($criteriosOut === []) {
                continue;
            }
            $gruposOut[$gi++] = [
                'logic' => self::normalizarLogic((string) ($grupo['logic'] ?? self::LOGIC_AND)),
                'not' => ! empty($grupo['not']) ? 1 : 0,
                'criterios' => $criteriosOut,
            ];
        }

        if ($gruposOut === []) {
            return [];
        }

        // Un solo grupo AND sin NOT → forma plana legacy (compat URL cortas / vistas viejas)
        if (
            count($gruposOut) === 1
            && ($gruposOut[0]['logic'] ?? '') === self::LOGIC_AND
            && empty($gruposOut[0]['not'])
            && self::normalizarLogic((string) ($qbe['entre_grupos'] ?? self::LOGIC_AND)) === self::LOGIC_AND
        ) {
            return ['qbe' => array_values($gruposOut[0]['criterios'])];
        }

        return [
            'qbe' => [
                'entre_grupos' => self::normalizarLogic((string) ($qbe['entre_grupos'] ?? self::LOGIC_AND)),
                'grupos' => $gruposOut,
            ],
        ];
    }

    /**
     * Aplica el árbol QBE. $aplicarCriterio(Builder $q, array $criterio, string $boolean).
     *
     * @param  array{entre_grupos: string, grupos: list}|list  $qbe
     * @param  callable(Builder, array{campo: string, op: string, valor: string, valor_hasta?: string}, string): void  $aplicarCriterio
     */
    public static function aplicar(Builder $query, array $qbe, callable $aplicarCriterio): void
    {
        if (! self::esFormaCanonica($qbe)) {
            // Legacy plano: AND de criterios
            $primero = true;
            foreach ($qbe as $key => $criterio) {
                if (is_array($criterio) && isset($criterio['campo'])) {
                    $c = $criterio;
                } elseif (! is_array($criterio)) {
                    $c = ['campo' => (string) $key, 'op' => 'contiene', 'valor' => trim((string) $criterio)];
                } else {
                    continue;
                }
                $op = (string) ($c['op'] ?? '');
                $valor = trim((string) ($c['valor'] ?? ''));
                if (! self::operadorSinValor($op) && $op !== 'entre' && $valor === '') {
                    continue;
                }
                $aplicarCriterio($query, [
                    'campo' => (string) $c['campo'],
                    'op' => $op !== '' ? $op : 'contiene',
                    'valor' => $valor,
                    'valor_hasta' => trim((string) ($c['valor_hasta'] ?? '')),
                ], 'and');
                $primero = false;
            }
            unset($primero);

            return;
        }

        if (! self::tieneCriterios($qbe)) {
            return;
        }

        $entre = self::normalizarLogic((string) ($qbe['entre_grupos'] ?? self::LOGIC_AND));
        $gruposValidos = [];
        foreach ($qbe['grupos'] as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }
            $criterios = [];
            foreach ((array) ($grupo['criterios'] ?? []) as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $formula = trim((string) ($c['formula'] ?? ''));
                $campo = (string) ($c['campo'] ?? '');
                if ($campo === '' && $formula === '') {
                    continue;
                }
                $op = (string) ($c['op'] ?? 'contiene');
                $valor = trim((string) ($c['valor'] ?? ''));
                $hasta = trim((string) ($c['valor_hasta'] ?? ''));
                if (! self::operadorSinValor($op) && $op !== 'entre' && $valor === '') {
                    continue;
                }
                if ($op === 'entre' && $valor === '' && $hasta === '') {
                    continue;
                }
                $fila = [
                    'campo' => $campo !== '' ? $campo : ListadoQbeFormulaSupport::CAMPO_KEY,
                    'op' => $op,
                    'valor' => $valor,
                    'valor_hasta' => $hasta,
                ];
                if ($formula !== '') {
                    $fila['formula'] = $formula;
                }
                $criterios[] = $fila;
            }
            if ($criterios === []) {
                continue;
            }
            $gruposValidos[] = [
                'logic' => self::normalizarLogic((string) ($grupo['logic'] ?? self::LOGIC_AND)),
                'not' => ! empty($grupo['not']),
                'criterios' => $criterios,
            ];
        }

        if ($gruposValidos === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($gruposValidos, $entre, $aplicarCriterio) {
            foreach ($gruposValidos as $i => $grupo) {
                $method = ($i === 0 || $entre === self::LOGIC_AND) ? 'where' : 'orWhere';
                $outer->{$method}(function (Builder $g) use ($grupo, $aplicarCriterio) {
                    $inner = function (Builder $box) use ($grupo, $aplicarCriterio): void {
                        foreach ($grupo['criterios'] as $j => $c) {
                            $boolean = ($j === 0 || $grupo['logic'] === self::LOGIC_AND)
                                ? self::LOGIC_AND
                                : self::LOGIC_OR;
                            $aplicarCriterio($box, $c, $boolean);
                        }
                    };

                    if ($grupo['not']) {
                        $g->whereNot(function (Builder $box) use ($inner) {
                            $inner($box);
                        });
                    } else {
                        $inner($g);
                    }
                });
            }
        });
    }

    /**
     * Para la UI: siempre al menos un grupo con un criterio vacío.
     *
     * @param  array{entre_grupos?: string, grupos?: list}|list  $qbe
     * @return array{entre_grupos: string, grupos: list<array{logic: string, not: bool, criterios: list}>}
     */
    public static function paraUi(array $qbe): array
    {
        if (! self::esFormaCanonica($qbe) || ($qbe['grupos'] ?? []) === []) {
            $criterios = [];
            if (is_array($qbe) && ! isset($qbe['grupos'])) {
                foreach ($qbe as $c) {
                    if (is_array($c) && ! empty($c['campo'])) {
                        $criterios[] = [
                            'campo' => $c['campo'],
                            'op' => $c['op'] ?? 'contiene',
                            'valor' => $c['valor'] ?? '',
                            'valor_hasta' => $c['valor_hasta'] ?? '',
                        ];
                    }
                }
            }
            if ($criterios === []) {
                $criterios[] = ['campo' => 'nombre', 'op' => 'contiene', 'valor' => '', 'valor_hasta' => '', 'formula' => ''];
            }

            return [
                'entre_grupos' => self::LOGIC_AND,
                'grupos' => [[
                    'logic' => self::LOGIC_AND,
                    'not' => false,
                    'criterios' => $criterios,
                ]],
            ];
        }

        $out = [
            'entre_grupos' => self::normalizarLogic((string) ($qbe['entre_grupos'] ?? self::LOGIC_AND)),
            'grupos' => [],
        ];
        foreach ($qbe['grupos'] as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }
            $criterios = [];
            foreach ((array) ($grupo['criterios'] ?? []) as $c) {
                if (! is_array($c) || (empty($c['campo']) && trim((string) ($c['formula'] ?? '')) === '')) {
                    continue;
                }
                $criterios[] = [
                    'campo' => $c['campo'] ?? ListadoQbeFormulaSupport::CAMPO_KEY,
                    'op' => $c['op'] ?? 'contiene',
                    'valor' => $c['valor'] ?? '',
                    'valor_hasta' => $c['valor_hasta'] ?? '',
                    'formula' => trim((string) ($c['formula'] ?? '')),
                ];
            }
            if ($criterios === []) {
                $criterios[] = ['campo' => 'nombre', 'op' => 'contiene', 'valor' => '', 'valor_hasta' => '', 'formula' => ''];
            }
            $out['grupos'][] = [
                'logic' => self::normalizarLogic((string) ($grupo['logic'] ?? self::LOGIC_AND)),
                'not' => ! empty($grupo['not']),
                'criterios' => $criterios,
            ];
        }
        if ($out['grupos'] === []) {
            $out['grupos'][] = [
                'logic' => self::LOGIC_AND,
                'not' => false,
                'criterios' => [
                    ['campo' => 'nombre', 'op' => 'contiene', 'valor' => '', 'valor_hasta' => '', 'formula' => ''],
                ],
            ];
        }

        return $out;
    }

    public static function normalizarLogic(string $logic): string
    {
        return strtolower(trim($logic)) === self::LOGIC_OR ? self::LOGIC_OR : self::LOGIC_AND;
    }

    /**
     * @param  array<string, mixed>  $qbe
     */
    public static function esFormaCanonica(array $qbe): bool
    {
        return array_key_exists('grupos', $qbe) && is_array($qbe['grupos']);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, array{column?: string, type?: string, label?: string}>  $campos
     * @param  callable(string $op, string $campo): string  $normalizarOperador
     * @return array{entre_grupos: string, grupos: list}
     */
    private static function normalizarConGrupos(array $raw, array $campos, callable $normalizarOperador): array
    {
        $entre = self::normalizarLogic((string) ($raw['entre_grupos'] ?? self::LOGIC_AND));
        $grupos = [];
        foreach (array_values($raw['grupos']) as $grupo) {
            if (count($grupos) >= self::MAX_GRUPOS) {
                break;
            }
            if (! is_array($grupo)) {
                continue;
            }
            $criterios = self::normalizarCriteriosPlanos(
                (array) ($grupo['criterios'] ?? []),
                $campos,
                $normalizarOperador
            );
            if ($criterios === []) {
                continue;
            }
            $criterios = array_slice($criterios, 0, self::MAX_CRITERIOS_POR_GRUPO);
            $grupos[] = [
                'logic' => self::normalizarLogic((string) ($grupo['logic'] ?? self::LOGIC_AND)),
                'not' => ! empty($grupo['not']),
                'criterios' => $criterios,
            ];
        }

        if ($grupos === []) {
            return self::vacio();
        }

        return [
            'entre_grupos' => $entre,
            'grupos' => $grupos,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @param  array<string, array{column?: string, type?: string, label?: string}>  $campos
     * @param  callable(string $op, string $campo): string  $normalizarOperador
     * @return list<array{campo: string, op: string, valor: string, valor_hasta: string}>
     */
    private static function normalizarCriteriosPlanos(array $raw, array $campos, callable $normalizarOperador): array
    {
        $criterios = [];
        $esLista = $raw !== [] && (array_is_list($raw) || isset($raw[0]));

        if ($esLista || (isset($raw[0]) && is_array($raw[0] ?? null))) {
            foreach ($raw as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $formula = trim((string) ($fila['formula'] ?? ''));
                $campo = (string) ($fila['campo'] ?? '');
                $valor = trim((string) ($fila['valor'] ?? ''));
                $hasta = trim((string) ($fila['valor_hasta'] ?? ''));

                if ($formula !== '') {
                    if (! ListadoQbeFormulaSupport::puedeCompilar($formula, $campos)) {
                        continue;
                    }
                    $tipo = ListadoQbeFormulaSupport::inferirTipo($formula);
                    $op = ListadoQbeFormulaSupport::normalizarOperador(
                        (string) ($fila['op'] ?? ($tipo === 'entero' ? 'igual' : 'contiene')),
                        $tipo
                    );
                    if ($op === 'vacio' || $op === 'entre' || $valor !== '' || $hasta !== '') {
                        $criterios[] = [
                            'campo' => ListadoQbeFormulaSupport::CAMPO_KEY,
                            'op' => $op,
                            'valor' => $valor,
                            'valor_hasta' => $hasta,
                            'formula' => $formula,
                        ];
                    }
                    continue;
                }

                if (! isset($campos[$campo])) {
                    continue;
                }
                $op = $normalizarOperador((string) ($fila['op'] ?? 'contiene'), $campo);
                if ($op === 'entre' || $valor !== '' || $hasta !== '' || self::operadorSinValor($op)) {
                    $criterios[] = [
                        'campo' => $campo,
                        'op' => $op,
                        'valor' => $valor,
                        'valor_hasta' => $hasta,
                    ];
                }
            }

            return $criterios;
        }

        // Legacy plano qbe[nombre]=acme
        foreach ($raw as $key => $valor) {
            $key = (string) $key;
            if (in_array($key, ['grupos', 'entre_grupos'], true) || ! isset($campos[$key])) {
                continue;
            }
            $valor = trim((string) $valor);
            if ($valor === '') {
                continue;
            }
            $criterios[] = [
                'campo' => $key,
                'op' => 'contiene',
                'valor' => $valor,
                'valor_hasta' => '',
            ];
        }

        return $criterios;
    }

    /**
     * Importe. Acepta 12067.14, 12067,14 y 12.067,14. No compara como texto.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarDecimal(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }

        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        $desde = self::normalizarDecimal($valor);
        $hasta = self::normalizarDecimal($valorHasta);

        if ($operador === 'entre') {
            if ($desde !== null) {
                $query->where($column, '>=', $desde);
            }
            if ($hasta !== null) {
                $query->where($column, '<=', $hasta);
            }

            return;
        }

        if ($desde === null) {
            return;
        }

        match ($operador) {
            'mayor' => $query->where($column, '>', $desde),
            'menor' => $query->where($column, '<', $desde),
            'menor_igual' => $query->where($column, '<=', $desde),
            'igual' => $query->whereRaw('ROUND('.$column.', 2) = ?', [$desde]),
            default => $query->where($column, '>=', $desde),
        };
    }

    public static function normalizarDecimal(string $valor): ?float
    {
        $valor = trim(str_replace([' ', '$'], '', $valor));
        if ($valor === '') {
            return null;
        }

        $coma = strrpos($valor, ',');
        $punto = strrpos($valor, '.');
        if ($coma !== false && $punto !== false) {
            if ($coma > $punto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($coma !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        if (! is_numeric($valor)) {
            return null;
        }

        return round((float) $valor, 2);
    }

    /**
     * Compara por día calendario. Acepta Y-m-d (input date) o d/m/Y.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFecha(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }

        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        if (isset(self::OPERADORES_PERIODO[$operador])) {
            $rango = self::rangoPeriodo($operador);
            if ($rango === null) {
                return;
            }
            $query->where($column, '>=', $rango[0].' 00:00:00');
            $query->where($column, '<', self::diaSiguiente($rango[1]).' 00:00:00');

            return;
        }

        $desde = self::normalizarFecha($valor);
        $hasta = self::normalizarFecha($valorHasta);

        if ($operador === 'entre') {
            if ($desde !== null) {
                $query->where($column, '>=', $desde.' 00:00:00');
            }
            if ($hasta !== null) {
                $query->where($column, '<', self::diaSiguiente($hasta).' 00:00:00');
            }

            return;
        }

        if ($desde === null) {
            return;
        }

        $siguiente = self::diaSiguiente($desde);
        match ($operador) {
            'mayor' => $query->where($column, '>=', $siguiente.' 00:00:00'),
            'menor' => $query->where($column, '<', $desde.' 00:00:00'),
            'menor_igual' => $query->where($column, '<', $siguiente.' 00:00:00'),
            'igual' => $query->where($column, '>=', $desde.' 00:00:00')
                ->where($column, '<', $siguiente.' 00:00:00'),
            default => $query->where($column, '>=', $desde.' 00:00:00'),
        };
    }

    public static function operadorSinValor(string $op): bool
    {
        return $op === 'vacio' || isset(self::OPERADORES_PERIODO[$op]);
    }

    /**
     * Desde y hasta inclusive (Y-m-d). Semana = lunes a domingo.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function rangoPeriodo(string $clave, ?DateTimeImmutable $hoy = null): ?array
    {
        if (! isset(self::OPERADORES_PERIODO[$clave])) {
            return null;
        }

        $hoy = ($hoy ?? new DateTimeImmutable('today'))->setTime(0, 0);
        if ($clave === 'hoy') {
            $dia = $hoy->format('Y-m-d');

            return [$dia, $dia];
        }
        if ($clave === 'ayer') {
            $dia = $hoy->modify('-1 day')->format('Y-m-d');

            return [$dia, $dia];
        }
        if ($clave === 'esta_semana') {
            $lunes = $hoy->modify('-'.((int) $hoy->format('N') - 1).' days');

            return [$lunes->format('Y-m-d'), $lunes->modify('+6 days')->format('Y-m-d')];
        }
        if ($clave === 'este_mes') {
            return [
                $hoy->modify('first day of this month')->format('Y-m-d'),
                $hoy->modify('last day of this month')->format('Y-m-d'),
            ];
        }
        if ($clave === 'mes_anterior') {
            $inicio = $hoy->modify('first day of last month');

            return [
                $inicio->format('Y-m-d'),
                $inicio->modify('last day of this month')->format('Y-m-d'),
            ];
        }

        $anio = $hoy->format('Y');

        return [$anio.'-01-01', $anio.'-12-31'];
    }

    public static function formatearFecha(mixed $valor): string
    {
        $ymd = self::normalizarFecha(is_scalar($valor) ? (string) $valor : '');

        if ($ymd === null) {
            return '';
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $fecha instanceof DateTimeImmutable ? $fecha->format('d/m/Y') : '';
    }

    public static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) === 1) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
            $dia = (int) $m[3];

            return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $valor, $m) === 1) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            $anio = (int) $m[3];

            return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
        }

        return null;
    }

    private static function diaSiguiente(string $ymd): string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        if (! $fecha instanceof DateTimeImmutable) {
            return $ymd;
        }

        return $fecha->modify('+1 day')->format('Y-m-d');
    }
}
