<?php

namespace App\Support\Contable;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del ABM de cuentas contables (index árbol / lista).
 */
class CuentacontableListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const VISTA_ARBOL = 'arbol';

    public const VISTA_LISTA = 'lista';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cuentacontable.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'cuentacontable.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'cuentacontable.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'tipocuenta' => ['column' => 'cuentacontable.tipocuenta', 'type' => 'texto', 'label' => 'Tipo'],
        'nivel' => ['column' => 'cuentacontable.nivel', 'type' => 'entero', 'label' => 'Nivel'],
        'rubro' => ['column' => 'rubrocontable.nombre', 'type' => 'texto', 'label' => 'Rubro'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'concepto' => ['column' => 'conceptogasto.nombre', 'type' => 'texto', 'label' => 'Concepto'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cuentacontable.nombre',
        'rubrocontable.nombre',
        'empresa.nombre',
        'conceptogasto.nombre',
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
            return array_merge(self::filtrosVacios($empresaDefault), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'vista' => self::vistaPorDefecto($empresaScope),
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'nombre');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombre';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');

        $vista = (string) $request->input('vista', self::vistaPorDefecto($empresaScope));
        if (! in_array($vista, [self::VISTA_ARBOL, self::VISTA_LISTA], true)) {
            $vista = self::vistaPorDefecto($empresaScope);
        }
        if ($empresaScope === 'todas') {
            $vista = self::VISTA_LISTA;
        }

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
            'vista' => $vista,
            'mostrar_totalizadoras' => $request->boolean('mostrar_totalizadoras'),
            'tipocuenta' => self::normalizarTipo($request->input('filtro_tipocuenta')),
            'nivel' => self::normalizarNivel($request->input('filtro_nivel')),
        ];
    }

    /**
     * @return array{0:?int,1:string}
     */
    public static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
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

    public static function vistaPorDefecto(string $empresaScope): string
    {
        return $empresaScope === 'todas' ? self::VISTA_LISTA : self::VISTA_ARBOL;
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
        if ((string) ($filtros['tipocuenta'] ?? '') !== '') {
            return true;
        }
        if ((int) ($filtros['nivel'] ?? 0) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(?int $empresaDefault = null): array
    {
        $base = [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'vista' => self::VISTA_ARBOL,
            'mostrar_totalizadoras' => false,
            'tipocuenta' => '',
            'nivel' => 0,
        ];

        if ($empresaDefault !== null && $empresaDefault > 0) {
            $base['empresa_id'] = $empresaDefault;
            $base['empresa_scope'] = 'una';
        }

        return $base;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['vista'] ?? self::VISTA_ARBOL) === self::VISTA_LISTA) {
            $params['vista'] = self::VISTA_LISTA;
        }
        if (! empty($filtros['mostrar_totalizadoras'])) {
            $params['mostrar_totalizadoras'] = 1;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
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
        if ((string) ($filtros['tipocuenta'] ?? '') !== '') {
            $params['filtro_tipocuenta'] = $filtros['tipocuenta'];
        }
        if ((int) ($filtros['nivel'] ?? 0) > 0) {
            $params['filtro_nivel'] = (int) $filtros['nivel'];
        }

        return $params;
    }

    /**
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

    /**
     * @param  Builder<\App\Models\Contable\Cuentacontable>  $query
     * @param  list<int>  $empresaIdsPermitidos
     */
    public static function aplicar(Builder $query, array $filtros, array $empresaIdsPermitidos = []): void
    {
        if ($empresaIdsPermitidos !== []) {
            $query->whereIn('cuentacontable.empresa_id', $empresaIdsPermitidos);
        }

        if (! empty($filtros['empresa_id'])) {
            $query->where('cuentacontable.empresa_id', (int) $filtros['empresa_id']);
        }

        $tipo = (string) ($filtros['tipocuenta'] ?? '');
        if ($tipo !== '') {
            $query->where('cuentacontable.tipocuenta', $tipo);
        }

        $nivel = (int) ($filtros['nivel'] ?? 0);
        if ($nivel > 0) {
            $query->where('cuentacontable.nivel', $nivel);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor);

            return;
        }

        if ($tipo !== '' || $nivel > 0) {
            if ($valor === '' && $operador === 'contiene') {
                return;
            }
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Contable\Cuentacontable>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cuentacontable.nombre')->orWhere('cuentacontable.nombre', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('cuentacontable.id', (int) $id)
                    ->orWhere('cuentacontable.codigo', (string) $id)
                    ->orWhere('cuentacontable.nivel', (int) $id);
            }
            foreach (['cuentacontable.codigo', 'cuentacontable.nombre', 'rubrocontable.nombre', 'empresa.nombre', 'conceptogasto.nombre'] as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
            self::aplicarCoincidenciaTipo($q, $valor, $operador);
        });
    }

    /**
     * @param  Builder<\App\Models\Contable\Cuentacontable>  $query
     */
    private static function aplicarCoincidenciaTipo(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }
        $v = mb_strtolower($valor);
        if (str_contains($v, 'imput')) {
            $query->orWhere('cuentacontable.tipocuenta', CuentacontableArbolSupport::TIPO_IMPUTABLE);
        }
        if (str_contains($v, 'títul') || str_contains($v, 'titul') || str_contains($v, 'no imput')) {
            $query->orWhere('cuentacontable.tipocuenta', CuentacontableArbolSupport::TIPO_TITULO);
        }
        if (str_contains($v, 'total')) {
            $query->orWhere('cuentacontable.tipocuenta', CuentacontableArbolSupport::TIPO_TOTALIZADORA);
        }
    }

    /**
     * @param  Builder<\App\Models\Contable\Cuentacontable>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        if (($def['type'] ?? 'texto') === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            $col = (string) $def['column'];
            match ($operador) {
                'mayor' => $query->where($col, '>', $id),
                'menor' => $query->where($col, '<', $id),
                default => $query->where($col, '=', $id),
            };

            return;
        }

        if ($campoKey === 'tipocuenta') {
            self::aplicarCoincidenciaTipo($query, $valor, $operador === 'contiene' ? 'contiene' : 'igual');
            if ($operador === 'igual' && in_array($valor, ['1', '2', '3'], true)) {
                $query->where('cuentacontable.tipocuenta', $valor);
            }

            return;
        }

        $col = (string) $def['column'];
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($col) {
                $q->whereNull($col)->orWhere($col, '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = self::patronLike($operador, $valor);
        match ($operador) {
            'igual' => $query->where($col, '=', $valor),
            'distinto' => $query->where($col, '!=', $valor),
            default => $query->where($col, 'like', $like),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $permitidos = array_keys(self::operadoresParaCampo($campoKey));

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    private static function normalizarTipo(mixed $valor): string
    {
        $v = trim((string) $valor);

        return in_array($v, ['1', '2', '3'], true) ? $v : '';
    }

    private static function normalizarNivel(mixed $valor): int
    {
        $n = (int) $valor;

        return $n >= 1 && $n <= 9 ? $n : 0;
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }
}
