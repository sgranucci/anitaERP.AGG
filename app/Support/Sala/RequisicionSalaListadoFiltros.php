<?php

namespace App\Support\Sala;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RequisicionSalaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const CAMPOS = [
        'id' => ['column' => 'requisicion_sala.id', 'type' => 'entero', 'label' => 'ID'],
        'numerorequisicion' => ['column' => 'requisicion_sala.numerorequisicion', 'type' => 'entero', 'label' => 'Número'],
        'nombreusuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Solicitante'],
        'fecha' => ['column' => 'requisicion_sala.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'fecha_entrega' => ['column' => 'requisicion_sala.fecha_entrega', 'type' => 'fecha', 'label' => 'Fecha entrega'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombrecentrocosto' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Centro costo'],
        'nombredeposito' => ['column' => 'depmae.nombre', 'type' => 'texto', 'label' => 'Depósito'],
        'nombrezona' => ['column' => 'zona_sala.nombre', 'type' => 'texto', 'label' => 'Zona sala'],
        'nombreprioridad' => ['column' => 'prioridad_sala.nombre', 'type' => 'texto', 'label' => 'Prioridad'],
        'estado' => ['column' => 'requisicion_sala.estado', 'type' => 'texto', 'label' => 'Estado'],
        'comentario' => ['column' => 'requisicion_sala.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'detalle' => ['column' => 'requisicion_sala.detalle', 'type' => 'texto', 'label' => 'Detalle'],
    ];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'centrocosto.nombre',
        'depmae.nombre',
        'zona_sala.nombre',
        'prioridad_sala.nombre',
        'usuario.nombre',
        'requisicion_sala.comentario',
        'requisicion_sala.detalle',
    ];

    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');
        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'numerorequisicion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numerorequisicion';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numerorequisicion');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
        ];
    }

    /**
     * Filtro externo del index: empresa (default primera asignada) o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numerorequisicion',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
        ];
    }

    /**
     * Criterios del panel / búsqueda rápida (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['valor_hasta'] ?? '')) !== ''
            || (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
                && in_array($filtros['operador'] ?? '', ['vacio'], true));
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (! self::tieneCriteriosTexto($filtros)) {
            return $params;
        }

        $params['filtro_modo'] = $filtros['modo'] ?? self::MODO_TODOS;
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerorequisicion';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
            if (($filtros['operador'] ?? '') === 'entre') {
                $params['filtro_valor_hasta'] = $filtros['valor_hasta'] ?? '';
            }
        }

        return $params;
    }

    /**
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('requisicion_sala.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numerorequisicion', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%';
        $textCols = [
            'usuario.nombre',
            'empresa.nombre',
            'centrocosto.nombre',
            'depmae.nombre',
            'zona_sala.nombre',
            'prioridad_sala.nombre',
            'requisicion_sala.estado',
            'requisicion_sala.comentario',
            'requisicion_sala.detalle',
        ];
        $query->where(function ($q) use ($valor, $like, $textCols) {
            if (is_numeric($valor)) {
                $id = (int) $valor;
                $q->where('requisicion_sala.id', $id)
                    ->orWhere('requisicion_sala.numerorequisicion', $id);
            }
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if (in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
            $q->orWhereHas('requisicion_sala_articulos.articulos', function ($aq) use ($like, $valor) {
                $aq->where(function ($w) use ($like, $valor) {
                    $w->where('articulo.sku', 'like', $like)
                        ->orWhere('articulo.descripcion', 'like', $like);
                    CoincidenciaFlexibleTexto::aplicar(
                        $w,
                        'articulo.sku',
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                    CoincidenciaFlexibleTexto::aplicar(
                        $w,
                        'articulo.descripcion',
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                });
            });
        });
    }

    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numerorequisicion'];
        $column = $def['column'];
        $type = $def['type'];
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($type === 'fecha') {
            self::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'entero') {
            self::aplicarEntero($query, $column, $operador, $valor);

            return;
        }
        if ($valor === '') {
            return;
        }
        match ($operador) {
            'empieza' => $query->where($column, 'like', CoincidenciaFlexibleTexto::escapeLike($valor).'%'),
            'termina' => $query->where($column, 'like', '%'.CoincidenciaFlexibleTexto::escapeLike($valor)),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where(function ($q) use ($column, $valor) {
                $like = '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%';
                $q->where($column, 'like', $like);
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false);
                }
            }),
        };
    }

    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($valor === '' || ! is_numeric($valor)) {
            return;
        }
        $id = (int) $valor;
        match ($operador) {
            'mayor' => $query->where($column, '>', $id),
            'menor' => $query->where($column, '<', $id),
            default => $query->where($column, '=', $id),
        };
    }

    private static function aplicarFecha(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'entre' && $valor !== '' && $valorHasta !== '') {
            $query->whereBetween($column, [$valor, $valorHasta]);

            return;
        }
        if ($valor === '') {
            return;
        }
        match ($operador) {
            'desde' => $query->where($column, '>=', $valor),
            'hasta' => $query->where($column, '<=', $valor),
            default => $query->whereDate($column, Carbon::parse($valor)->toDateString()),
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $map = match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };

        return isset($map[$operador]) ? $operador : array_key_first($map);
    }

    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
