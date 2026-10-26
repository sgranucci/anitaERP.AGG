<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownJornadaListadoFiltros
{
    /**
     * @return array{valor: string, sala_id: int, fecha_desde: string, fecha_hasta: string, estado: string}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        $estado = (string) $request->input('estado', '');
        if (! in_array($estado, [TesoreriaCrownJornada::ABIERTA, TesoreriaCrownJornada::CERRADA], true)) {
            $estado = '';
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'estado' => $estado,
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
     * @return array{valor: string, sala_id: int, fecha_desde: string, fecha_hasta: string, estado: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'estado' => '',
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
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($filtros['estado'] ?? '')) !== ''
            || ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        foreach (['fecha_desde', 'fecha_hasta', 'estado'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $params[$clave] = $valor;
            }
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
        return TesoreriaCrownJornadaListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownJornadaListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::camposQbe()[$campo]['type'] ?? 'texto';
        $validos = $tipo === 'fecha'
            ? array_keys(ListadoQbeSupport::OPERADORES_FECHA)
            : ['contiene', 'igual', 'empieza'];

        return in_array($op, $validos, true) ? $op : ($tipo === 'fecha' ? 'igual' : 'contiene');
    }

    /**
     * @param  Builder<TesoreriaCrownJornada>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_jornada.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($desde !== '') {
            $query->where('tesoreria_crown_jornada.fecha', '>=', $desde);
        }
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta !== '') {
            $query->where('tesoreria_crown_jornada.fecha', '<=', $hasta);
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('tesoreria_crown_jornada.estado', $estado);
        }

        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            self::aplicarOrden($query, $filtros);

            return;
        }

        $like = '%'.addcslashes($valor, '%_\\').'%';
        $fecha = self::fecha($valor) !== '' ? self::fecha($valor) : self::fechaDma($valor);
        $query->where(function ($q) use ($valor, $like, $fecha) {
            $q->whereHas('asignaciones.cajero', function ($cajero) use ($valor, $like) {
                $cajero->where('nombre', 'like', $like);
                CoincidenciaFlexibleTexto::aplicar(
                    $cajero,
                    'nombre',
                    $valor,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
                if (ctype_digit($valor)) {
                    $cajero->orWhere('codigo', (int) $valor)->orWhere('legajo', (int) $valor);
                }
            });
            if ($fecha !== '') {
                $q->orWhere('tesoreria_crown_jornada.fecha', $fecha);
            }
        });
        self::aplicarOrden($query, $filtros);
    }

    /**
     * @param  Builder<TesoreriaCrownJornada>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarOrden(Builder $query, array $filtros): void
    {
        ListadoOrdenamientoSupport::aplicar(
            $query,
            (array) ($filtros['sort'] ?? []),
            self::camposOrdenables(),
            ['campo' => 'fecha', 'dir' => 'desc']
        );
        $query->orderByDesc('tesoreria_crown_jornada.id');
    }

    /**
     * @param  Builder<TesoreriaCrownJornada>  $query
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
     * @param  Builder<TesoreriaCrownJornada>  $query
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
        if ($campo === 'cajeros') {
            $like = $op === 'empieza' ? $valor.'%' : ($op === 'igual' ? null : '%'.$valor.'%');
            $query->whereHas('asignaciones.cajero', function (Builder $cajero) use ($like, $valor) {
                if ($like === null) {
                    $cajero->where('nombre', $valor);
                } else {
                    $cajero->where('nombre', 'like', $like);
                }
            });

            return;
        }
        if ($campo === 'estado') {
            $codigo = mb_strtolower($valor);
            $mapa = ['abierta' => TesoreriaCrownJornada::ABIERTA, 'cerrada' => TesoreriaCrownJornada::CERRADA];
            foreach ($mapa as $etiqueta => $guardado) {
                if ($codigo === $guardado || (strlen($codigo) >= 4 && str_starts_with($etiqueta, $codigo))) {
                    $query->where('tesoreria_crown_jornada.estado', $guardado);

                    return;
                }
            }
            $query->whereRaw('1 = 0');

            return;
        }
        if (($def['type'] ?? '') === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $def['column'], $op, $valor, $hasta);
        }
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        $dt = DateTime::createFromFormat('Y-m-d', $texto);

        return $dt && $dt->format('Y-m-d') === $texto ? $texto : '';
    }

    private static function fechaDma(string $valor): string
    {
        $dt = DateTime::createFromFormat('d/m/Y', $valor);

        return $dt && $dt->format('d/m/Y') === $valor ? $dt->format('Y-m-d') : '';
    }
}
