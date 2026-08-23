<?php

namespace App\Support\Seguridad;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class IngresoProveedorListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'ingreso_proveedor.id', 'type' => 'entero', 'label' => 'ID'],
        'fecha' => ['column' => 'ingreso_proveedor.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'proveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor / Empresa'],
        'visitante' => ['column' => 'ingreso_proveedor.visitante_nombre', 'type' => 'texto', 'label' => 'Visitante'],
        'motivo' => ['column' => 'ingreso_proveedor_motivo.nombre', 'type' => 'texto', 'label' => 'Motivo de visita'],
        'punto' => ['column' => 'ingreso_proveedor_punto.nombre', 'type' => 'texto', 'label' => 'Sala / Punto de ingreso'],
        'sector' => ['column' => 'ingreso_proveedor_sector.nombre', 'type' => 'texto', 'label' => 'Sector'],
        'area' => ['column' => 'ingreso_proveedor_area.nombre', 'type' => 'texto', 'label' => 'Área de destino'],
        'usuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Generó usuario'],
        'estado' => ['column' => 'ingreso_proveedor.estado', 'type' => 'texto', 'label' => 'Estado'],
        'titulo' => ['column' => 'ingreso_proveedor.titulo', 'type' => 'texto', 'label' => 'Título'],
        'comentario' => ['column' => 'ingreso_proveedor.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'proveedor.nombre',
        'ingreso_proveedor.visitante_nombre',
        'ingreso_proveedor_motivo.nombre',
        'ingreso_proveedor_punto.nombre',
        'ingreso_proveedor_sector.nombre',
        'ingreso_proveedor_area.nombre',
        'usuario.nombre',
        'ingreso_proveedor.titulo',
        'ingreso_proveedor.comentario',
        'empresa.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
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

        $campo = (string) $request->input('filtro_campo', 'proveedor');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'proveedor';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'proveedor');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'fecha_desde' => self::fechaFiltro($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaFiltro($request->input('fecha_hasta')),
            'estado' => self::estadoFiltro($request->input('estado')),
            'sector_id' => self::enteroFiltro($request->input('sector_id')),
            'area_id' => self::enteroFiltro($request->input('area_id')),
        ];
    }

    /**
     * @return array{0:?int,1:string}
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

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }
        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros) || self::tieneCriteriosEstructurados($filtros);
    }

    public static function tieneCriteriosEstructurados(array $filtros): bool
    {
        return ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || ! empty($filtros['estado'])
            || ! empty($filtros['sector_id'])
            || ! empty($filtros['area_id']);
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'proveedor',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'estado' => '',
            'sector_id' => null,
            'area_id' => null,
        ];
    }

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

    public static function aplicarEmpresa(Builder $query, array $filtros, string $columna = 'ingreso_proveedor.empresa_id'): void
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'una' && ! empty($filtros['empresa_id'])) {
            $query->where($columna, (int) $filtros['empresa_id']);
        }
    }

    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'proveedor';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['valor_hasta'])) {
            $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['estado'])) {
            $params['estado'] = $filtros['estado'];
        }
        if (! empty($filtros['sector_id'])) {
            $params['sector_id'] = (int) $filtros['sector_id'];
        }
        if (! empty($filtros['area_id'])) {
            $params['area_id'] = (int) $filtros['area_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Seguridad\IngresoProveedor>  $query
     */
    public static function aplicarEstructurados(Builder $query, array $filtros): void
    {
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('ingreso_proveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('ingreso_proveedor.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('ingreso_proveedor.estado', $filtros['estado']);
        }
        if (! empty($filtros['sector_id'])) {
            $query->where('ingreso_proveedor.sector_id', (int) $filtros['sector_id']);
        }
        if (! empty($filtros['area_id'])) {
            $query->where('ingreso_proveedor.area_id', (int) $filtros['area_id']);
        }
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'proveedor', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);
        $textCols = [
            'proveedor.nombre',
            'ingreso_proveedor.visitante_nombre',
            'ingreso_proveedor_motivo.nombre',
            'ingreso_proveedor_punto.nombre',
            'ingreso_proveedor_sector.nombre',
            'ingreso_proveedor_area.nombre',
            'usuario.nombre',
            'ingreso_proveedor.estado',
            'ingreso_proveedor.titulo',
            'ingreso_proveedor.comentario',
            'empresa.nombre',
        ];

        $query->where(function ($q) use ($valor, $like, $id, $operador, $textCols) {
            if ($id !== false) {
                $q->orWhere('ingreso_proveedor.id', (int) $id);
            }
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
        });
    }

    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['proveedor'];
        if (($def['type'] ?? '') === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            match ($operador) {
                'mayor' => $query->where($def['column'], '>', $id),
                'menor' => $query->where($def['column'], '<', $id),
                default => $query->where($def['column'], '=', $id),
            };

            return;
        }

        $column = (string) $def['column'];
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }
        match ($operador) {
            'empieza' => $query->where($column, 'like', self::escapeLike($valor).'%'),
            'termina' => $query->where($column, 'like', '%'.self::escapeLike($valor)),
            'igual' => $query->where($column, '=', $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where(function ($q) use ($column, $valor) {
                $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $column,
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }),
        };
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = self::escapeLike($valor);

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = $type === 'entero'
            ? array_keys(self::OPERADORES_ENTERO)
            : array_keys(self::OPERADORES_TEXTO);

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    /** @return array<string, string> */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }

    private static function fechaFiltro($valor): ?string
    {
        $v = trim((string) $valor);
        if ($v === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return $v;
    }

    private static function estadoFiltro($valor): string
    {
        $estado = strtoupper(trim((string) $valor));
        if ($estado === '' || ! in_array($estado, IngresoProveedorEstados::todos(), true)) {
            return '';
        }

        return $estado;
    }

    private static function enteroFiltro($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $n = (int) $valor;

        return $n > 0 ? $n : null;
    }
}
