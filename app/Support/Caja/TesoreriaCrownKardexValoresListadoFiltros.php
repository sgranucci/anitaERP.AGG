<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

final class TesoreriaCrownKardexValoresListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, int $salaId, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'deposito_id' => (int) $request->input('deposito_id', 0),
            'tipo_id' => (int) $request->input('tipo_id', 0),
            'subtipo_id' => (int) $request->input('subtipo_id', 0),
            'valor_id' => (int) $request->input('valor_id', 0),
            'operacion' => trim((string) $request->input('operacion', '')),
            'consultar' => $request->boolean('consultar')
                || $request->filled('filtro_valor')
                || $request->boolean('aplicar_qbe')
                || ListadoQbeSupport::tieneCriterios(ListadoQbeSupport::resolverDesdeRequest(
                    $request,
                    self::camposQbe(),
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )),
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::camposQbe(),
                static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
            'deposito_codigo' => trim((string) $request->input('deposito_codigo', '')),
            'deposito_nombre' => trim((string) $request->input('deposito_nombre', '')),
            'tipo_codigo' => trim((string) $request->input('tipo_codigo', '')),
            'tipo_nombre' => trim((string) $request->input('tipo_nombre', '')),
            'subtipo_codigo' => trim((string) $request->input('subtipo_codigo', '')),
            'subtipo_nombre' => trim((string) $request->input('subtipo_nombre', '')),
            'valor_codigo' => trim((string) $request->input('valor_codigo', '')),
            'valor_nombre' => trim((string) $request->input('valor_nombre', '')),
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
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'deposito_id' => 0,
            'tipo_id' => 0,
            'subtipo_id' => 0,
            'valor_id' => 0,
            'operacion' => '',
            'consultar' => false,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
            'deposito_codigo' => '',
            'deposito_nombre' => '',
            'tipo_codigo' => '',
            'tipo_nombre' => '',
            'subtipo_codigo' => '',
            'subtipo_nombre' => '',
            'valor_codigo' => '',
            'valor_nombre' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))
            || ($filtros['fecha_desde'] ?? '') !== ''
            || ($filtros['fecha_hasta'] ?? '') !== ''
            || (int) ($filtros['deposito_id'] ?? 0) > 0
            || (int) ($filtros['tipo_id'] ?? 0) > 0
            || (int) ($filtros['subtipo_id'] ?? 0) > 0
            || (int) ($filtros['valor_id'] ?? 0) > 0
            || ($filtros['operacion'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'sala_id' => (int) ($filtros['sala_id'] ?? 0),
            'consultar' => 1,
        ];
        foreach (['fecha_desde', 'fecha_hasta', 'operacion', 'deposito_codigo', 'deposito_nombre', 'tipo_codigo', 'tipo_nombre', 'subtipo_codigo', 'subtipo_nombre', 'valor_codigo', 'valor_nombre'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                $params[$k] = $filtros[$k];
            }
        }
        foreach (['deposito_id', 'tipo_id', 'subtipo_id', 'valor_id'] as $k) {
            if ((int) ($filtros[$k] ?? 0) > 0) {
                $params[$k] = (int) $filtros[$k];
            }
        }
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
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposQbe(): array
    {
        return TesoreriaCrownKardexValoresListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownKardexValoresListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::camposQbe()[$campo]['type'] ?? 'texto';
        if ($tipo === 'fecha') {
            $validos = array_keys(ListadoQbeSupport::OPERADORES_FECHA);

            return in_array($op, $validos, true) ? $op : 'entre';
        }
        if ($tipo === 'decimal') {
            $validos = array_keys(ListadoQbeSupport::OPERADORES_DECIMAL);

            return in_array($op, $validos, true) ? $op : 'igual';
        }
        $validos = ['contiene', 'igual', 'empieza'];

        return in_array($op, $validos, true) ? $op : 'contiene';
    }

    public static function aplicarExternos(Builder $query, array $filtros): void
    {
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('j.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('j.fecha', '<=', $filtros['fecha_hasta']);
        }
        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            $query->where('b.deposito_id', (int) $filtros['deposito_id']);
        }
        if ((int) ($filtros['tipo_id'] ?? 0) > 0) {
            $query->where('b.tipo_id', (int) $filtros['tipo_id']);
        }
        if ((int) ($filtros['subtipo_id'] ?? 0) > 0) {
            $query->where('b.subtipo_id', (int) $filtros['subtipo_id']);
        }
        if ((int) ($filtros['valor_id'] ?? 0) > 0) {
            $query->where('l.valor_id', (int) $filtros['valor_id']);
        }
        if (($filtros['operacion'] ?? '') !== '') {
            $query->where('t.operacion', $filtros['operacion']);
        }
        $texto = trim((string) ($filtros['valor'] ?? ''));
        if ($texto !== '') {
            $like = '%'.addcslashes($texto, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $texto) {
                $q->where('t.abreviatura', 'like', $like)
                    ->orWhere('t.nombre', 'like', $like)
                    ->orWhere('b.comentario', 'like', $like)
                    ->orWhere('d.codigo', 'like', $like)
                    ->orWhere('d.nombre', 'like', $like)
                    ->orWhere('st.nombre', 'like', $like)
                    ->orWhere('tv.codigo', 'like', $like)
                    ->orWhereRaw('CAST(b.numero AS CHAR) like ?', [$like]);
                if (ctype_digit($texto)) {
                    $q->orWhere('b.numero', (int) $texto);
                }
                CoincidenciaFlexibleTexto::aplicar($q, 'b.comentario', $texto, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT);
            });
        }
    }

    public static function aplicarQbe(Builder $query, array $qbe): void
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
        $columna = (string) $def['column'];
        $tipo = (string) ($def['type'] ?? 'texto');

        if ($tipo === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $columna, $op, $valor, $hasta);

            return;
        }
        if ($tipo === 'decimal') {
            if ($campo === 'monto') {
                $expr = '(l.cantidad * v.denominacion)';
                self::aplicarDecimalExpr($query, $expr, $op, $valor, $hasta);

                return;
            }
            ListadoQbeSupport::aplicarDecimal($query, $columna, $op, $valor, $hasta);

            return;
        }
        if ($campo === 'efecto') {
            $map = ['entra' => '0', 'sale' => '1', 'no mueve' => '2', '0' => '0', '1' => '1', '2' => '2'];
            $clave = mb_strtolower($valor);
            $codigo = $map[$clave] ?? null;
            $codigo === null ? $query->whereRaw('1 = 0') : $query->where('t.signo', $codigo);

            return;
        }
        if ($op === 'vacio') {
            $query->where(function (Builder $q) use ($columna) {
                $q->whereNull($columna)->orWhere($columna, '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = $op === 'empieza' ? addcslashes($valor, '%_\\').'%' : ($op === 'igual' ? null : '%'.addcslashes($valor, '%_\\').'%');
        $columnas = match ($campo) {
            'deposito' => ['d.codigo', 'd.nombre'],
            'tipo' => ['t.abreviatura', 't.nombre'],
            'subtipo' => ['st.codigo', 'st.nombre', 'b.subtipo_texto'],
            'valor' => ['tv.codigo', 'tv.nombre'],
            'abreviatura' => ['t.abreviatura'],
            'numero' => ['b.numero'],
            'moneda' => ['tv.codigo'],
            'operacion' => ['t.operacion'],
            default => ['b.comentario'],
        };
        $query->where(function (Builder $q) use ($columnas, $like, $valor, $campo) {
            foreach ($columnas as $col) {
                if ($campo === 'numero') {
                    $q->orWhereRaw('CAST('.$col.' AS CHAR) like ?', [$like ?? $valor]);
                    if (ctype_digit($valor)) {
                        $q->orWhere($col, (int) $valor);
                    }
                } elseif ($like === null) {
                    $q->orWhere($col, $valor);
                } else {
                    $q->orWhere($col, 'like', $like);
                }
            }
        });
    }

    private static function aplicarDecimalExpr(Builder $query, string $expr, string $op, string $valor, string $hasta): void
    {
        $n = (float) str_replace(',', '.', $valor);
        $n2 = (float) str_replace(',', '.', $hasta);
        match ($op) {
            'mayor' => $query->whereRaw($expr.' > ?', [$n]),
            'menor' => $query->whereRaw($expr.' < ?', [$n]),
            'mayor_igual' => $query->whereRaw($expr.' >= ?', [$n]),
            'menor_igual' => $query->whereRaw($expr.' <= ?', [$n]),
            'entre' => $query->whereRaw($expr.' between ? and ?', [$n, $n2]),
            'vacio' => $query->whereRaw($expr.' is null'),
            default => $query->whereRaw($expr.' = ?', [$n]),
        };
    }
}
