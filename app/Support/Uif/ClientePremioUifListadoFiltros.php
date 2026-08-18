<?php

namespace App\Support\Uif;

use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de premios UIF (index / exportaciones).
 * Misma empresa externa / origen que clientes ({@see ClienteUifListadoFiltros}).
 */
class ClientePremioUifListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: ?string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cliente_premio_uif.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'cliente_uif.nombre', 'type' => 'texto', 'label' => 'Nombre cliente'],
        'sala' => ['column' => 'sala.nombre', 'type' => 'texto', 'label' => 'Sala'],
        'juego' => ['column' => 'juego_uif.nombre', 'type' => 'texto', 'label' => 'Juego'],
        'fechaentrega' => ['column' => 'cliente_premio_uif.fechaentrega', 'type' => 'texto', 'label' => 'Fecha entrega'],
        'monto' => ['column' => 'cliente_premio_uif.monto', 'type' => 'texto', 'label' => 'Monto'],
        'posicion' => ['column' => 'cliente_premio_uif.posicion', 'type' => 'texto', 'label' => 'Posición'],
        'numerotito' => ['column' => 'cliente_premio_uif.numerotito', 'type' => 'texto', 'label' => 'Número TITO'],
        'formapago' => ['column' => 'formapago.nombre', 'type' => 'texto', 'label' => 'Forma de pago'],
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $uifCtx = ClienteUifOrigenPcSupport::contexto($request);
        $origenesPermitidos = $uifCtx['origenes_permitidos'];
        [$empresaId, $empresaScope] = ClienteUifListadoFiltros::resolverEmpresaExterna($request, $uifCtx);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'anita_origen' => $empresaScope === 'una' && $empresaId > 0
                    ? (ClienteUifOrigenPcSupport::origenDesdeEmpresaId($empresaId) ?? '')
                    : ($uifCtx['origen_fijo'] ? (string) ($uifCtx['origen'] ?? '') : ''),
                'anita_origen_todos' => $empresaScope === 'todas',
                'origenes_permitidos' => $origenesPermitidos,
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

        $origenDesdeEmpresa = null;
        if ($empresaScope === 'una' && $empresaId > 0) {
            $origenDesdeEmpresa = ClienteUifOrigenPcSupport::origenDesdeEmpresaId($empresaId);
            if ($origenDesdeEmpresa !== null) {
                ClienteUifOrigenPcSupport::persistirEmpresaSesion($empresaId);
            } else {
                $empresaId = 0;
                $empresaScope = 'todas';
            }
        }

        $anitaOrigenInput = strtolower(trim((string) $request->input('filtro_anita_origen', '')));
        $anitaOrigenExplicito = $request->has('filtro_anita_origen');
        $anitaOrigen = '';
        if ($origenDesdeEmpresa !== null) {
            $anitaOrigen = $origenDesdeEmpresa;
        } elseif ($anitaOrigenInput === 'todos' || $anitaOrigenInput === '') {
            $anitaOrigen = '';
        } else {
            $anitaOrigen = ClienteUifListadoFiltros::normalizarOrigenInput($anitaOrigenInput) ?? '';
        }

        if ($valor !== '' && ClienteUifListadoFiltros::normalizarOrigenInput($valor) !== null) {
            $origenDesdeTexto = ClienteUifListadoFiltros::normalizarOrigenInput($valor);
            if ($anitaOrigen === '') {
                $anitaOrigen = (string) $origenDesdeTexto;
                $anitaOrigenExplicito = true;
                $anitaOrigenInput = (string) $origenDesdeTexto;
            }
            $valor = '';
        }

        if ($anitaOrigen === '' && ! $anitaOrigenExplicito && $uifCtx['origen_fijo'] && $uifCtx['origen']) {
            $anitaOrigen = (string) $uifCtx['origen'];
        }

        if ($anitaOrigen !== '' && $origenesPermitidos !== [] && ! in_array($anitaOrigen, $origenesPermitidos, true)) {
            $anitaOrigen = '';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'anita_origen' => $anitaOrigen,
            'anita_origen_explicito' => $anitaOrigenExplicito,
            'anita_origen_todos' => ($empresaScope === 'todas' && ! $anitaOrigenExplicito)
                || ($anitaOrigenExplicito && $anitaOrigenInput === 'todos')
                || ($anitaOrigen === '' && $empresaScope === 'todas'),
            'empresa_id' => $empresaScope === 'una' && $empresaId > 0 ? $empresaId : 0,
            'empresa_scope' => $empresaScope,
            'origenes_permitidos' => $origenesPermitidos,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (! empty($filtros['anita_origen_explicito']) && ($filtros['anita_origen'] ?? '') !== '') {
            return true;
        }

        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
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
        return self::tieneCriteriosTexto($filtros);
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'anita_origen' => '',
            'anita_origen_explicito' => false,
            'anita_origen_todos' => true,
            'empresa_id' => 0,
            'empresa_scope' => 'todas',
            'origenes_permitidos' => ClienteUifOrigenPcSupport::origenesDesdeEmpresas(
                ClienteUifOrigenPcSupport::empresasUifAsignadas()
            ),
            'busqueda' => '',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

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
        if (($filtros['empresa_scope'] ?? 'todas') === 'todas'
            && ($filtros['anita_origen'] ?? '') !== '') {
            $params['filtro_anita_origen'] = $filtros['anita_origen'];
        }

        return $params;
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'todas') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /**
     * Texto de filtros activos para cabecera Excel/PDF.
     */
    public static function subtituloFiltros(array $filtros): string
    {
        $partes = [];

        if (($filtros['empresa_scope'] ?? 'todas') === 'una' && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            $origen = ClienteUifOrigenPcSupport::origenDesdeEmpresaId((int) $filtros['empresa_id']);
            $partes[] = 'Empresa/sala: '.($origen
                ? ClienteUifOrigenPcSupport::labelOrigen($origen)
                : ('#'.(int) $filtros['empresa_id']));
        } elseif (($filtros['anita_origen'] ?? '') !== '') {
            $partes[] = 'Origen: '.ClienteUifOrigenPcSupport::labelOrigen((string) $filtros['anita_origen']);
        } else {
            $partes[] = 'Empresa/sala: Todas las salas';
        }

        if (self::tieneCriteriosTexto($filtros) && (
            trim((string) ($filtros['valor'] ?? '')) !== ''
            || ($filtros['operador'] ?? '') === 'vacio'
            || ($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
            || (($filtros['operador'] ?? 'contiene') !== 'contiene')
        )) {
            $modo = $filtros['modo'] ?? self::MODO_TODOS;
            if ($modo === self::MODO_CAMPO) {
                $campo = $filtros['campo'] ?? 'nombre';
                $partes[] = 'Campo: '.(self::CAMPOS[$campo]['label'] ?? $campo);
            } else {
                $partes[] = 'Búsqueda: cualquier campo';
            }
            $op = (string) ($filtros['operador'] ?? 'contiene');
            $ops = self::operadoresParaCampo($modo === self::MODO_CAMPO ? ($filtros['campo'] ?? 'nombre') : 'nombre');
            $partes[] = 'Condición: '.($ops[$op] ?? $op);
            if (trim((string) ($filtros['valor'] ?? '')) !== '') {
                $partes[] = 'Valor: '.$filtros['valor'];
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Premio_Uif>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (($filtros['anita_origen'] ?? '') !== '') {
            $query->where('cliente_uif.anita_origen', $filtros['anita_origen']);
        } elseif (! empty($filtros['origenes_permitidos']) && is_array($filtros['origenes_permitidos'])) {
            $todosOrigenes = array_map('strval', array_keys(config('uif.anita_origenes', [])));
            $permitidos = array_values(array_intersect($filtros['origenes_permitidos'], $todosOrigenes));
            if ($permitidos !== [] && count($permitidos) < count($todosOrigenes)) {
                $query->whereIn('cliente_uif.anita_origen', $permitidos);
            }
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            $campo = $filtros['campo'] ?? 'nombre';
            self::aplicarEnCampo($query, $campo, $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Premio_Uif>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('cliente_premio_uif.id', (int) $id)
                    ->orWhere('cliente_uif.id', (int) $id);
            }
            foreach ([
                'cliente_uif.nombre',
                'sala.nombre',
                'juego_uif.nombre',
                'formapago.nombre',
                'cliente_premio_uif.posicion',
                'cliente_premio_uif.numerotito',
            ] as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
            $q->orWhere('cliente_premio_uif.monto', 'like', $like)
                ->orWhereRaw(SqlDialectSupport::castTexto('cliente_premio_uif.fechaentrega').' LIKE ?', [$like]);
        });
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Premio_Uif>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        $column = (string) $def['column'];

        if (($def['type'] ?? '') === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            match ($operador) {
                'mayor' => $query->where($column, '>', $id),
                'menor' => $query->where($column, '<', $id),
                default => $query->where($column, '=', $id),
            };

            return;
        }

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        switch ($operador) {
            case 'empieza':
                $query->where($column, 'like', self::escapeLike($valor).'%');
                break;
            case 'termina':
                $query->where($column, 'like', '%'.self::escapeLike($valor));
                break;
            case 'igual':
                $query->where($column, '=', $valor);
                break;
            case 'distinto':
                $query->where($column, '!=', $valor);
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->where($column, 'like', $like);
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, true);
                });
                break;
        }
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

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
