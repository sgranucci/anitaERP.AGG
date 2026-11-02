<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownBovedaListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, int $salaId, int $jornadaId, int $depositoId, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;
            $vacios['jornada_id'] = $jornadaId;
            $vacios['deposito_id'] = $depositoId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'jornada_id' => $jornadaId,
            'deposito_id' => $depositoId,
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::camposQbe(),
                static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
            'jornada_id' => 0,
            'deposito_id' => 0,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'sala_id' => (int) ($filtros['sala_id'] ?? 0),
            'jornada_id' => (int) ($filtros['jornada_id'] ?? 0),
            'deposito_id' => (int) ($filtros['deposito_id'] ?? 0),
        ];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }

        return array_merge(
            $params,
            ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])),
            ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])),
            ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? []))
        );
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbe(): array
    {
        return TesoreriaCrownBovedaListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownBovedaListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::camposQbe()[$campo]['type'] ?? 'texto';
        $validos = $tipo === 'decimal'
            ? array_keys(ListadoQbeSupport::OPERADORES_DECIMAL)
            : ['contiene', 'igual', 'empieza'];

        return in_array($op, $validos, true) ? $op : ($tipo === 'decimal' ? 'igual' : 'contiene');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TesoreriaCrownBoveda>
     */
    public static function consulta(int $jornadaId, int $depositoId, array $filtros): Builder
    {
        $importe = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->join('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->whereNotIn('tv.codigo', ['FVAL', 'FCOL'])
            ->where(function ($q) {
                $q->where('tv.es_moneda_referencia', 1)->orWhere('tv.cotizacion_fija', 1);
            })
            ->groupBy('l.boveda_id')
            ->select('l.boveda_id')
            ->selectRaw("SUM(l.cantidad * v.denominacion * CASE t.signo WHEN '0' THEN 1 WHEN '1' THEN -1 ELSE 0 END) as importe");

        $query = TesoreriaCrownBoveda::query()
            ->select('tesoreria_crown_boveda.*')
            ->join('tesoreria_crown_boveda_tipo', 'tesoreria_crown_boveda_tipo.id', '=', 'tesoreria_crown_boveda.tipo_id')
            ->leftJoin('tesoreria_crown_comprobante', 'tesoreria_crown_comprobante.id', '=', 'tesoreria_crown_boveda.comprobante_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'tesoreria_crown_boveda.usuario_id')
            ->leftJoinSub($importe, 'tc_boveda_importe', 'tc_boveda_importe.boveda_id', '=', 'tesoreria_crown_boveda.id')
            ->where('tesoreria_crown_boveda.jornada_id', $jornadaId)
            ->where('tesoreria_crown_boveda.deposito_id', $depositoId)
            ->with(['tipo', 'deposito', 'comprobante', 'lineas.valor.tipoValor', 'usuario', 'revierte', 'reversa']);

        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));
        self::aplicarTexto($query, trim((string) ($filtros['valor'] ?? '')));
        self::aplicarOrden($query, $filtros);

        return $query;
    }

    /**
     * @param  Builder<TesoreriaCrownBoveda>  $query
     */
    private static function aplicarTexto(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $efectos = self::codigosEfecto($valor, 'contiene');
        $estados = self::codigosEstado($valor, 'contiene');
        $query->where(function (Builder $q) use ($valor, $like, $efectos, $estados) {
            $q->whereRaw('CAST(tesoreria_crown_boveda.numero AS CHAR) like ?', [$like])
                ->orWhere('tesoreria_crown_boveda.comentario', 'like', $like)
                ->orWhere('tesoreria_crown_boveda_tipo.abreviatura', 'like', $like)
                ->orWhere('tesoreria_crown_boveda_tipo.nombre', 'like', $like)
                ->orWhere('usuario.nombre', 'like', $like)
                ->orWhereRaw('CAST(tesoreria_crown_comprobante.numero AS CHAR) like ?', [$like]);
            CoincidenciaFlexibleTexto::aplicar($q, 'tesoreria_crown_boveda.comentario', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT);
            CoincidenciaFlexibleTexto::aplicar($q, 'tesoreria_crown_boveda_tipo.nombre', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT);
            if (ctype_digit(ltrim($valor, '0') === '' ? '0' : ltrim($valor, '0'))) {
                $q->orWhere('tesoreria_crown_boveda.numero', (int) $valor)
                    ->orWhere('tesoreria_crown_comprobante.numero', (int) $valor);
            }
            if ($efectos !== []) {
                $q->orWhereIn('tesoreria_crown_boveda_tipo.signo', $efectos);
            }
            if ($estados !== []) {
                $q->orWhereIn('tesoreria_crown_boveda.estado', $estados);
            }
        });
    }

    /**
     * @param  Builder<TesoreriaCrownBoveda>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = (array) ($filtros['sort'] ?? []);
        if ($orden === []) {
            $query->orderBy('tesoreria_crown_boveda.numero');

            return;
        }
        ListadoOrdenamientoSupport::aplicar($query, $orden, self::camposOrdenables());
        $query->orderBy('tesoreria_crown_boveda.id');
    }

    /**
     * @param  Builder<TesoreriaCrownBoveda>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }
        ListadoQbeSupport::aplicar($query, $qbe, static function (Builder $q, array $criterio, string $boolean = 'and'): void {
            if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                $q->orWhere(function (Builder $inner) use ($criterio) {
                    self::aplicarCriterio($inner, $criterio);
                });

                return;
            }
            self::aplicarCriterio($q, $criterio);
        });
    }

    /**
     * @param  Builder<TesoreriaCrownBoveda>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterio(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $op = (string) ($criterio['op'] ?? 'contiene');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
        if ($valor === '' && $op !== 'vacio') {
            return;
        }
        if ($campo === 'valor') {
            self::aplicarDecimal($query, (string) $def['column'], $op, $valor, $hasta);

            return;
        }
        if ($campo === 'efecto') {
            $codigos = self::codigosEfecto($valor, $op);
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tesoreria_crown_boveda_tipo.signo', $codigos);

            return;
        }
        if ($campo === 'estado') {
            $codigos = self::codigosEstado($valor, $op);
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tesoreria_crown_boveda.estado', $codigos);

            return;
        }

        $like = $op === 'empieza' ? addcslashes($valor, '%_\\').'%' : ($op === 'igual' ? null : '%'.addcslashes($valor, '%_\\').'%');
        $columnas = match ($campo) {
            'tipo' => ['tesoreria_crown_boveda_tipo.abreviatura', 'tesoreria_crown_boveda_tipo.nombre'],
            'comprobante' => ['tesoreria_crown_comprobante.numero'],
            'usuario' => ['usuario.nombre'],
            'numero' => ['tesoreria_crown_boveda.numero'],
            default => ['tesoreria_crown_boveda.comentario'],
        };
        $query->where(function (Builder $q) use ($columnas, $like, $valor, $op, $campo) {
            foreach ($columnas as $columna) {
                if (in_array($campo, ['numero', 'comprobante'], true)) {
                    $q->orWhereRaw('CAST('.$columna.' AS CHAR) like ?', [$like ?? $valor]);
                    if (ctype_digit($valor)) {
                        $q->orWhere($columna, (int) $valor);
                    }
                } elseif ($like === null) {
                    $q->orWhere($columna, $valor);
                } else {
                    $q->orWhere($columna, 'like', $like);
                }
            }
            if ($op === 'contiene' && $campo === 'comentario') {
                CoincidenciaFlexibleTexto::aplicar($q, 'tesoreria_crown_boveda.comentario', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT);
            }
            if ($op === 'contiene' && $campo === 'tipo') {
                CoincidenciaFlexibleTexto::aplicar($q, 'tesoreria_crown_boveda_tipo.nombre', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT);
            }
        });
    }

    private static function aplicarDecimal(Builder $query, string $column, string $op, string $valor, string $hasta): void
    {
        if ($op === 'vacio') {
            $query->whereNull($column);

            return;
        }
        $numero = self::numero($valor);
        $tope = self::numero($hasta);
        match ($op) {
            'mayor' => $query->where($column, '>', $numero),
            'menor' => $query->where($column, '<', $numero),
            'mayor_igual' => $query->where($column, '>=', $numero),
            'menor_igual' => $query->where($column, '<=', $numero),
            'entre' => $query->whereBetween($column, [min($numero, $tope), max($numero, $tope)]),
            default => $query->where($column, $numero),
        };
    }

    private static function numero(string $valor): float
    {
        $valor = trim($valor);
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }

    /**
     * @return list<string>
     */
    private static function codigosEfecto(string $valor, string $op): array
    {
        return self::codigosDe([
            '0' => 'entra',
            '1' => 'sale',
            '2' => 'no mueve saldo',
        ], $valor, $op);
    }

    /**
     * @return list<string>
     */
    private static function codigosEstado(string $valor, string $op): array
    {
        return self::codigosDe([
            TesoreriaCrownBoveda::BORRADOR => 'borrador',
            TesoreriaCrownBoveda::CONFIRMADO => 'confirmado',
            TesoreriaCrownBoveda::ANULADO => 'anulado',
        ], $valor, $op);
    }

    /**
     * @param  array<string, string>  $mapa
     * @return list<string>
     */
    private static function codigosDe(array $mapa, string $valor, string $op): array
    {
        $buscado = self::plano($valor);
        if ($buscado === '' || mb_strlen($buscado) < 3) {
            return [];
        }
        $out = [];
        foreach ($mapa as $codigo => $etiqueta) {
            $coincide = match ($op) {
                'igual' => $buscado === $etiqueta,
                'empieza' => str_starts_with($etiqueta, $buscado),
                default => str_contains($etiqueta, $buscado),
            };
            if ($coincide) {
                $out[] = (string) $codigo;
            }
        }

        return $out;
    }

    private static function plano(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));

        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
