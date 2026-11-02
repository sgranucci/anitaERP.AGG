<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownCajeroListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, int $jornadaId, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['id'] = $jornadaId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'id' => $jornadaId,
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
            'id' => 0,
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
            'id' => (int) ($filtros['id'] ?? 0),
            'panel' => 'cajeros',
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
        return TesoreriaCrownCajeroListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownCajeroListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $validos = ['contiene', 'igual', 'empieza'];

        return in_array($op, $validos, true) ? $op : 'contiene';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TesoreriaCrownAsignacion>
     */
    public static function consulta(int $jornadaId, array $filtros): Builder
    {
        $vigente = DB::table('tesoreria_crown_cierre')
            ->select('asignacion_id', DB::raw('MAX(id) as id'))
            ->whereNull('reabierta_at')
            ->groupBy('asignacion_id');

        $query = TesoreriaCrownAsignacion::query()
            ->select('tesoreria_crown_asignacion.*')
            ->join('tesoreria_crown_deposito', 'tesoreria_crown_deposito.id', '=', 'tesoreria_crown_asignacion.deposito_id')
            ->join('cajero_crown', 'cajero_crown.id', '=', 'tesoreria_crown_asignacion.cajero_crown_id')
            ->leftJoinSub($vigente, 'tc_cierre_vigente', 'tc_cierre_vigente.asignacion_id', '=', 'tesoreria_crown_asignacion.id')
            ->leftJoin('tesoreria_crown_cierre', 'tesoreria_crown_cierre.id', '=', 'tc_cierre_vigente.id')
            ->where('tesoreria_crown_asignacion.jornada_id', $jornadaId)
            ->with(['deposito', 'cajero', 'comprobantes', 'cierre', 'cierres']);

        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));
        self::aplicarTexto($query, trim((string) ($filtros['valor'] ?? '')));
        self::aplicarOrden($query, $filtros);

        return $query;
    }

    /**
     * @param  Builder<TesoreriaCrownAsignacion>  $query
     */
    private static function aplicarTexto(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $like = '%'.addcslashes($valor, '%_\\').'%';
        $turnos = self::codigosDeCatalogo(TesoreriaCrownAsignacionCatalogo::TURNOS, $valor, 'contiene');
        $tareas = self::codigosDeCatalogo(TesoreriaCrownAsignacionCatalogo::TAREAS, $valor, 'contiene');
        $estados = self::codigosEstado($valor, 'contiene');

        $query->where(function (Builder $q) use ($valor, $like, $turnos, $tareas, $estados) {
            $q->where('tesoreria_crown_deposito.codigo', 'like', $like)
                ->orWhere('tesoreria_crown_deposito.nombre', 'like', $like)
                ->orWhere('cajero_crown.nombre', 'like', $like)
                ->orWhere('cajero_crown.codigo', 'like', $like);
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'cajero_crown.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'tesoreria_crown_deposito.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
            if ($turnos !== []) {
                $q->orWhereIn('tesoreria_crown_asignacion.turno', $turnos);
            }
            if ($tareas !== []) {
                $q->orWhereIn('tesoreria_crown_asignacion.tipo_tarea', $tareas);
            }
            if ($estados !== []) {
                $q->orWhere(function (Builder $estado) use ($estados) {
                    self::restringirEstado($estado, $estados);
                });
            }
        });
    }

    /**
     * @param  Builder<TesoreriaCrownAsignacion>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = (array) ($filtros['sort'] ?? []);
        if ($orden === []) {
            $query->orderBy('tesoreria_crown_asignacion.turno')
                ->orderBy('tesoreria_crown_deposito.codigo')
                ->orderBy('tesoreria_crown_asignacion.id');

            return;
        }

        ListadoOrdenamientoSupport::aplicar($query, $orden, self::camposOrdenables());
        $query->orderBy('tesoreria_crown_asignacion.id');
    }

    /**
     * @param  Builder<TesoreriaCrownAsignacion>  $query
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
     * @param  Builder<TesoreriaCrownAsignacion>  $query
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
        if ($valor === '') {
            return;
        }

        if ($campo === 'turno') {
            $codigos = self::codigosDeCatalogo(TesoreriaCrownAsignacionCatalogo::TURNOS, $valor, $op);
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tesoreria_crown_asignacion.turno', $codigos);

            return;
        }
        if ($campo === 'tarea') {
            $codigos = self::codigosDeCatalogo(TesoreriaCrownAsignacionCatalogo::TAREAS, $valor, $op);
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tesoreria_crown_asignacion.tipo_tarea', $codigos);

            return;
        }
        if ($campo === 'estado') {
            $codigos = self::codigosEstado($valor, $op);
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : self::restringirEstado($query, $codigos);

            return;
        }

        $columnas = $campo === 'cajero'
            ? ['cajero_crown.nombre', 'cajero_crown.codigo']
            : ['tesoreria_crown_deposito.nombre', 'tesoreria_crown_deposito.codigo'];
        $like = $op === 'empieza' ? $valor.'%' : ($op === 'igual' ? null : '%'.addcslashes($valor, '%_\\').'%');
        $query->where(function (Builder $q) use ($columnas, $like, $valor, $op, $campo) {
            foreach ($columnas as $columna) {
                if ($like === null) {
                    $q->orWhere($columna, $valor);
                } else {
                    $q->orWhere($columna, 'like', $like);
                }
            }
            if ($op === 'contiene') {
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    $campo === 'cajero' ? 'cajero_crown.nombre' : 'tesoreria_crown_deposito.nombre',
                    $valor,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }
        });
    }

    /**
     * @param  Builder<TesoreriaCrownAsignacion>  $query
     * @param  list<string>  $codigos
     */
    private static function restringirEstado(Builder $query, array $codigos): void
    {
        $query->where(function (Builder $q) use ($codigos) {
            if (in_array('ABIERTA', $codigos, true)) {
                $q->orWhereNull('tesoreria_crown_cierre.id');
            }
            if (in_array('CERRADA', $codigos, true)) {
                $q->orWhereNotNull('tesoreria_crown_cierre.id');
            }
            $estados = array_values(array_filter(
                $codigos,
                static fn (string $codigo): bool => ! in_array($codigo, ['ABIERTA', 'CERRADA'], true)
            ));
            if ($estados !== []) {
                $q->orWhereIn('tesoreria_crown_cierre.estado', $estados);
            }
        });
    }

    /**
     * @param  array<string, string>  $catalogo
     * @return list<string>
     */
    private static function codigosDeCatalogo(array $catalogo, string $valor, string $op): array
    {
        $buscado = self::plano($valor);
        $out = [];
        foreach ($catalogo as $codigo => $nombre) {
            $etiqueta = self::plano($nombre);
            $clave = self::plano((string) $codigo);
            $coincide = match ($op) {
                'igual' => $buscado === $etiqueta || $buscado === $clave,
                'empieza' => str_starts_with($etiqueta, $buscado) || str_starts_with($clave, $buscado),
                default => str_contains($etiqueta, $buscado) || str_contains($clave, $buscado),
            };
            if ($coincide) {
                $out[] = (string) $codigo;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function codigosEstado(string $valor, string $op): array
    {
        $buscado = self::plano($valor);
        if ($buscado === '' || mb_strlen($buscado) < 3) {
            return [];
        }
        $mapa = [
            'ABIERTA' => 'abierta',
            'CERRADA' => 'cerrada',
            TesoreriaCrownCierreSupport::CONFORME => 'conforme',
            TesoreriaCrownCierreSupport::NO_CONFORME => 'no conforme',
            TesoreriaCrownCierreSupport::REVISION => 'a revision',
            TesoreriaCrownCierreSupport::REVISADA => 'revisada',
            TesoreriaCrownCierreSupport::AUDITORIA => 'auditoria',
            TesoreriaCrownCierreSupport::CIERRE_AUDITORIA => 'cierre de auditoria',
        ];
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
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return $texto;
    }
}
