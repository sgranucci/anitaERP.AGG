<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownComprobante;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownMovimientoListadoFiltros
{
    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'fecha' => ['column' => 'tesoreria_crown_jornada.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'tipo' => ['column' => 'tesoreria_crown_comprobante.tipo', 'type' => 'texto', 'label' => 'Tipo'],
        'caja' => ['column' => 'tesoreria_crown_deposito.nombre', 'type' => 'texto', 'label' => 'Caja'],
        'cajero' => ['column' => 'cajero_crown.nombre', 'type' => 'texto', 'label' => 'Cajero'],
        'estado' => ['column' => 'tesoreria_crown_comprobante.estado', 'type' => 'texto', 'label' => 'Estado'],
        'entrega' => ['column' => 'tesoreria_crown_comprobante.entrega_at', 'type' => 'fecha', 'label' => 'Entrega'],
        'recibe' => ['column' => 'tesoreria_crown_comprobante.recibe_at', 'type' => 'fecha', 'label' => 'Recibe'],
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'igual' => 'Igual a',
        'empieza' => 'Empieza con',
    ];

    /**
     * @return array{valor: string, sala_id: int, qbe: array<string, mixed>}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::CAMPOS,
                static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
        ];
    }

    /**
     * @return array{valor: string, sala_id: int, qbe: array<string, mixed>}
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
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
        $params = [];
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
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
     * @param  array<string, mixed>  $filtros
     * @return Builder<TesoreriaCrownComprobante>
     */
    public static function consulta(array $filtros): Builder
    {
        $query = TesoreriaCrownComprobante::query()
            ->select('tesoreria_crown_comprobante.*')
            ->join('tesoreria_crown_jornada', 'tesoreria_crown_jornada.id', '=', 'tesoreria_crown_comprobante.jornada_id')
            ->join('tesoreria_crown_asignacion', 'tesoreria_crown_asignacion.id', '=', 'tesoreria_crown_comprobante.asignacion_id')
            ->join('tesoreria_crown_deposito', 'tesoreria_crown_deposito.id', '=', 'tesoreria_crown_asignacion.deposito_id')
            ->join('cajero_crown', 'cajero_crown.id', '=', 'tesoreria_crown_asignacion.cajero_crown_id')
            ->with(['jornada', 'asignacion.deposito', 'asignacion.cajero', 'empresa']);

        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_comprobante.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        self::aplicarTexto($query, trim((string) ($filtros['valor'] ?? '')));
        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));
        ListadoOrdenamientoSupport::aplicar(
            $query,
            (array) ($filtros['sort'] ?? []),
            self::camposOrdenables(),
            ['campo' => 'fecha', 'dir' => 'desc']
        );

        return $query->orderByDesc('tesoreria_crown_comprobante.id');
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownMovimientoListadoColumnas::camposOrdenables();
    }

    public static function camposQbe(): array
    {
        return self::CAMPOS;
    }

    public static function normalizarOperadorPublico(string $op, string $campo): string
    {
        return self::normalizarOperador($op, $campo);
    }

    /**
     * @param  Builder<TesoreriaCrownComprobante>  $query
     */
    private static function aplicarTexto(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $like = '%'.$valor.'%';
        $fecha = ListadoQbeSupport::normalizarFecha($valor);
        $tipos = self::codigosQueCoinciden(TesoreriaCrownComprobanteSupport::TIPOS, $valor);
        $estados = self::codigosQueCoinciden(TesoreriaCrownComprobanteSupport::ESTADOS, $valor);

        $query->where(function (Builder $q) use ($like, $fecha, $tipos, $estados) {
            $q->where('tesoreria_crown_deposito.nombre', 'like', $like)
                ->orWhere('tesoreria_crown_deposito.codigo', 'like', $like)
                ->orWhere('cajero_crown.nombre', 'like', $like)
                ->orWhere('tesoreria_crown_comprobante.observacion', 'like', $like);
            if ($fecha !== null) {
                $q->orWhereDate('tesoreria_crown_jornada.fecha', $fecha);
            }
            if ($tipos !== []) {
                $q->orWhereIn('tesoreria_crown_comprobante.tipo', $tipos);
            }
            if ($estados !== []) {
                $q->orWhereIn('tesoreria_crown_comprobante.estado', $estados);
            }
            if (ctype_digit($valor)) {
                $q->orWhere('tesoreria_crown_comprobante.numero', (int) $valor);
            }
        });
    }

    /**
     * @param  Builder<TesoreriaCrownComprobante>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }

        ListadoQbeSupport::aplicar(
            $query,
            $qbe,
            static function (Builder $q, array $criterio, string $boolean = 'and'): void {
                if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                    $q->orWhere(function (Builder $inner) use ($criterio) {
                        self::aplicarCriterio($inner, $criterio);
                    });

                    return;
                }
                self::aplicarCriterio($q, $criterio);
            }
        );
    }

    /**
     * @param  Builder<TesoreriaCrownComprobante>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterio(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::CAMPOS[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $op = (string) ($criterio['op'] ?? 'contiene');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));

        if ($def['type'] === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $def['column'], $op, $valor, $hasta);

            return;
        }

        if ($campo === 'tipo') {
            self::aplicarCodigo($query, $def['column'], $op, $valor, TesoreriaCrownComprobanteSupport::TIPOS);

            return;
        }
        if ($campo === 'estado') {
            self::aplicarCodigo($query, $def['column'], $op, $valor, TesoreriaCrownComprobanteSupport::ESTADOS);

            return;
        }

        $like = match ($op) {
            'igual' => null,
            'empieza' => $valor.'%',
            default => '%'.$valor.'%',
        };
        if ($like === null) {
            $query->where($def['column'], $valor);

            return;
        }
        $query->where($def['column'], 'like', $like);
    }

    /**
     * @param  Builder<TesoreriaCrownComprobante>  $query
     * @param  array<string, string>  $etiquetas
     */
    private static function aplicarCodigo(Builder $query, string $column, string $op, string $valor, array $etiquetas): void
    {
        $codigo = strtoupper($valor);
        if ($op === 'igual' && isset($etiquetas[$codigo])) {
            $query->where($column, $codigo);

            return;
        }
        $codigos = self::codigosQueCoinciden($etiquetas, $valor);
        $query->whereIn($column, $codigos !== [] ? $codigos : ['__']);
    }

    /**
     * @param  array<string, string>  $etiquetas
     * @return list<string>
     */
    private static function codigosQueCoinciden(array $etiquetas, string $valor): array
    {
        $needle = mb_strtolower($valor);
        $codigos = [];
        foreach ($etiquetas as $codigo => $etiqueta) {
            if (mb_strtolower($codigo) === $needle || str_contains(mb_strtolower($etiqueta), $needle)) {
                $codigos[] = $codigo;
            }
        }

        return $codigos;
    }

    private static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::CAMPOS[$campo]['type'] ?? 'texto';
        $validos = $tipo === 'fecha'
            ? array_keys(ListadoQbeSupport::OPERADORES_FECHA)
            : array_keys(self::OPERADORES_TEXTO);

        return in_array($op, $validos, true) ? $op : ($tipo === 'fecha' ? 'igual' : 'contiene');
    }
}
