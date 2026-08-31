<?php

namespace App\Support\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de clientes UIF (index / exportaciones).
 */
class ClienteUifListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: ?string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cliente_uif.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'cliente_uif.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'tipodocumento' => ['column' => 'tipodocumento.abreviatura', 'type' => 'texto', 'label' => 'Tipo documento'],
        'numerodocumento' => ['column' => 'cliente_uif.numerodocumento', 'type' => 'texto', 'label' => 'Nº documento'],
        'cuit' => ['column' => 'cliente_uif.cuit', 'type' => 'texto', 'label' => 'CUIT'],
        'domicilio' => ['column' => 'cliente_uif.domicilio', 'type' => 'texto', 'label' => 'Domicilio'],
        'localidad' => ['column' => 'localidad_uif.nombre', 'type' => 'texto', 'label' => 'Localidad'],
        'provincia' => ['column' => 'provincia_uif.nombre', 'type' => 'texto', 'label' => 'Provincia'],
        'pais' => ['column' => 'pais_uif.nombre', 'type' => 'texto', 'label' => 'País'],
        'telefono' => ['column' => 'cliente_uif.telefono', 'type' => 'telefono', 'label' => 'Teléfono'],
        'email' => ['column' => 'cliente_uif.email', 'type' => 'texto', 'label' => 'Email'],
        'fechanacimiento' => ['column' => 'cliente_uif.fechanacimiento', 'type' => 'fecha', 'label' => 'Fecha nacimiento'],
        'fechavencimientodni' => ['column' => 'cliente_uif.fechavencimientodni', 'type' => 'fecha', 'label' => 'Vencimiento DNI'],
        'ultimo_premio_fecha' => ['column' => null, 'type' => 'fecha_premio', 'label' => 'Fecha último premio'],
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
    public const OPERADORES_TELEFONO = [
        'contiene' => 'Contiene dígitos',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'vacio' => 'Vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    private const SQL_ULTIMO_PREMIO_FECHA = '(SELECT cp.fechaentrega FROM cliente_premio_uif cp WHERE cp.cliente_uif_id = cliente_uif.id ORDER BY cp.fechaentrega DESC, cp.id DESC LIMIT 1)';

    private const SQL_TELEFONO_NORMALIZADO = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cliente_uif.telefono,''),' ',''),'-',''),'(',''),')',''),'.',''),'+','')";

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $uifCtx = ClienteUifOrigenPcSupport::contexto($request);
        $origenesPermitidos = $uifCtx['origenes_permitidos'];
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $uifCtx);

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

        $conPremios = $request->boolean('filtro_con_premios')
            || self::esBusquedaSoloConPremios($valor);
        $sinPremios = $request->boolean('filtro_sin_premios');
        // Select unificado (prioridad sobre checkboxes legacy).
        $filtroPremios = strtolower(trim((string) $request->input('filtro_premios', '')));
        if ($filtroPremios === 'con') {
            $conPremios = true;
            $sinPremios = false;
        } elseif ($filtroPremios === 'sin') {
            $sinPremios = true;
            $conPremios = false;
        }
        if ($conPremios && $sinPremios) {
            // Mutuamente excluyentes: gana "sin premios".
            $conPremios = false;
        }

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
            // Filtro externo de empresa manda sobre sala del panel.
            $anitaOrigen = $origenDesdeEmpresa;
        } elseif ($anitaOrigenInput === 'todos' || $anitaOrigenInput === '') {
            $anitaOrigen = '';
        } else {
            $anitaOrigen = self::normalizarOrigenInput($anitaOrigenInput) ?? '';
        }

        // Búsqueda rápida "ksa"/"bsa"/"rsa" (o nombre de sala) → filtro de origen, no texto.
        if ($valor !== '' && self::normalizarOrigenInput($valor) !== null) {
            $origenDesdeTexto = self::normalizarOrigenInput($valor);
            if ($anitaOrigen === '') {
                $anitaOrigen = $origenDesdeTexto;
                $anitaOrigenExplicito = true;
                $anitaOrigenInput = (string) $origenDesdeTexto;
            }
            // Si ya hay empresa/origen, no buscar el alias como texto (daría 0 filas).
            $valor = '';
        }

        if ($anitaOrigen === '' && ! $anitaOrigenExplicito && $uifCtx['origen_fijo'] && $uifCtx['origen']) {
            $anitaOrigen = (string) $uifCtx['origen'];
        }

        if ($anitaOrigen !== '' && $origenesPermitidos !== [] && ! in_array($anitaOrigen, $origenesPermitidos, true)) {
            $anitaOrigen = '';
        }

        $estado = strtoupper(trim((string) $request->input('filtro_estado', '')));
        $estadoValor = '';
        if ($estado !== '') {
            foreach (Cliente_Uif::$enumEstado as $item) {
                if (strcasecmp((string) ($item['valor'] ?? ''), $estado) === 0
                    || strcasecmp((string) ($item['nombre'] ?? ''), $estado) === 0) {
                    $estadoValor = (string) ($item['valor'] ?? '');
                    break;
                }
            }
            if ($estadoValor === '') {
                $estado = '';
            }
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'con_premios' => $conPremios,
            'sin_premios' => $sinPremios,
            'anita_origen' => $anitaOrigen,
            'anita_origen_explicito' => $anitaOrigenExplicito,
            'anita_origen_todos' => ($empresaScope === 'todas' && ! $anitaOrigenExplicito)
                || ($anitaOrigenExplicito && $anitaOrigenInput === 'todos')
                || ($anitaOrigen === '' && $empresaScope === 'todas'),
            // empresa_id solo si el filtro externo eligió una; no inferir desde origen del panel.
            'empresa_id' => $empresaScope === 'una' && $empresaId > 0 ? $empresaId : 0,
            'empresa_scope' => $empresaScope,
            'origenes_permitidos' => $origenesPermitidos,
            'estado' => $estadoValor,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    /**
     * Filtro externo del index: empresa (default todas si hay varias) o una (`empresa_id`).
     * Compartido con listado de premios UIF.
     *
     * @param  array<string, mixed>  $uifCtx
     * @return array{0:int,1:string}  [empresa_id, empresa_scope]
     */
    public static function resolverEmpresaExterna(Request $request, array $uifCtx): array
    {
        if (! empty($uifCtx['origen_fijo']) && ! empty($uifCtx['empresa_id'])) {
            return [(int) $uifCtx['empresa_id'], 'una'];
        }

        $empresas = $uifCtx['empresas_uif'] ?? collect();
        $permitidas = $empresas instanceof \Illuminate\Support\Collection
            ? $empresas->map(fn ($e) => (int) $e->id)->all()
            : [];

        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [0, 'todas'];
        }

        // Compat: select del panel (filtro_empresa_id) o botón externo (empresa_id).
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            $empresaId = (int) $request->input('filtro_empresa_id', 0);
        }

        if ($empresaId > 0) {
            if ($permitidas === [] || in_array($empresaId, $permitidas, true)) {
                if (ClienteUifOrigenPcSupport::origenDesdeEmpresaId($empresaId) !== null) {
                    return [$empresaId, 'una'];
                }
            }

            return [0, 'todas'];
        }

        // Sin parámetro: default = todas las salas permitidas (encargadas/operadores).
        if (count($permitidas) === 1) {
            return [(int) $permitidas[0], 'una'];
        }

        return [0, 'todas'];
    }

    /**
     * Criterios del panel / búsqueda (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (! empty($filtros['con_premios'])) {
            return true;
        }

        if (! empty($filtros['sin_premios'])) {
            return true;
        }

        if (! empty($filtros['anita_origen_explicito'])) {
            return true;
        }

        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            return true;
        }

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

    /**
     * Hay criterios de texto/panel (muestra «Limpiar filtros»). La empresa externa no cuenta.
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /** @deprecated Use tieneCriteriosAplicados() */
    public static function tieneFiltrosActivos(array $filtros): bool
    {
        return self::tieneCriteriosAplicados($filtros);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, con_premios: bool, busqueda: string, empresa_id: int, empresa_scope: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'con_premios' => false,
            'sin_premios' => false,
            'anita_origen' => '',
            'anita_origen_explicito' => false,
            'anita_origen_todos' => true,
            'empresa_id' => 0,
            'empresa_scope' => 'todas',
            'origenes_permitidos' => ClienteUifOrigenPcSupport::origenesDesdeEmpresas(
                ClienteUifOrigenPcSupport::empresasUifAsignadas()
            ),
            'estado' => '',
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
        if (! empty($filtros['valor_hasta'])) {
            $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
        }
        if (! empty($filtros['con_premios'])) {
            $params['filtro_con_premios'] = '1';
            $params['filtro_premios'] = 'con';
        }
        if (! empty($filtros['sin_premios'])) {
            $params['filtro_sin_premios'] = '1';
            $params['filtro_premios'] = 'sin';
        }
        // Con empresa externa activa el origen ya queda implícito.
        if (($filtros['empresa_scope'] ?? 'todas') === 'todas'
            && ($filtros['anita_origen'] ?? '') !== '') {
            $params['filtro_anita_origen'] = $filtros['anita_origen'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $params['filtro_estado'] = $filtros['estado'];
        }

        return $params;
    }

    /**
     * Solo el filtro externo de empresa (Limpiar texto sin perder empresa).
     *
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

        if (($filtros['estado'] ?? '') !== '') {
            $estadoLabel = (string) $filtros['estado'];
            foreach (\App\Models\Uif\Cliente_Uif::$enumEstado as $est) {
                if ((string) ($est['valor'] ?? '') === $estadoLabel) {
                    $estadoLabel = (string) ($est['nombre'] ?? $estadoLabel);
                    break;
                }
            }
            $partes[] = 'Estado: '.$estadoLabel;
        }

        if (! empty($filtros['con_premios'])) {
            $partes[] = 'Solo con premio';
        }
        if (! empty($filtros['sin_premios'])) {
            $partes[] = 'Solo sin premio';
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
            if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
                $partes[] = 'Hasta: '.$filtros['valor_hasta'];
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        // Multi-sala: premios en la sala filtrada; sin premios → origen de carga.
        ClienteUifSalaFiltroSupport::aplicarEnClientes($query, $filtros);

        if (($filtros['estado'] ?? '') !== '') {
            $query->where('cliente_uif.estado', $filtros['estado']);
        }

        if (! empty($filtros['con_premios'])) {
            $query->whereRaw('(SELECT COUNT(*) FROM cliente_premio_uif WHERE cliente_premio_uif.cliente_uif_id = cliente_uif.id) > 0');
        } elseif (! empty($filtros['sin_premios'])) {
            $query->whereRaw('(SELECT COUNT(*) FROM cliente_premio_uif WHERE cliente_premio_uif.cliente_uif_id = cliente_uif.id) = 0');
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        if (self::esBusquedaSoloConPremios($valor) && ($filtros['modo'] ?? '') === self::MODO_TODOS) {
            $query->whereRaw('(SELECT COUNT(*) FROM cliente_premio_uif WHERE cliente_premio_uif.cliente_uif_id = cliente_uif.id) > 0');

            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            $campo = $filtros['campo'] ?? 'nombre';
            self::aplicarEnCampo($query, $campo, $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cliente_uif.nombre', 'cliente_uif.numerodocumento', 'cliente_uif.telefono', 'cliente_uif.email'] as $col) {
                    $q->where(function ($w) use ($col) {
                        $w->whereNull($col)->orWhere($col, '');
                    });
                }
            });

            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);
        $digitos = self::soloDigitosTelefono($valor);

        $query->where(function ($q) use ($valor, $like, $id, $digitos, $operador) {
            if ($id !== false) {
                $q->orWhere('cliente_uif.id', (int) $id);
            }
            $textCols = [
                'cliente_uif.nombre',
                'tipodocumento.abreviatura',
                'cliente_uif.numerodocumento',
                'cliente_uif.cuit',
                'cliente_uif.domicilio',
                'localidad_uif.nombre',
                'provincia_uif.nombre',
                'pais_uif.nombre',
                'cliente_uif.email',
            ];
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
            if ($digitos !== '') {
                self::aplicarTelefonoEnQuery($q, $operador, $digitos, true);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        $type = $def['type'];

        if ($type === 'fecha_premio') {
            self::aplicarFechaPremio($query, $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'telefono') {
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->whereNull('cliente_uif.telefono')->orWhere('cliente_uif.telefono', '');
                });

                return;
            }
            $digitos = self::soloDigitosTelefono($valor);
            if ($digitos === '') {
                return;
            }
            self::aplicarTelefonoEnQuery($query, $operador, $digitos, false);

            return;
        }

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
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

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $id);
                break;
            case 'menor':
                $query->where($column, '<', $id);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $id);
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarFechaColumna(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        $desde = self::parsearFecha($valor);
        $hasta = self::parsearFecha($valorHasta);

        switch ($operador) {
            case 'desde':
                if ($desde) {
                    $query->whereDate($column, '>=', $desde);
                }
                break;
            case 'hasta':
                if ($desde) {
                    $query->whereDate($column, '<=', $desde);
                }
                break;
            case 'entre':
                if ($desde && $hasta) {
                    $query->whereDate($column, '>=', $desde)->whereDate($column, '<=', $hasta);
                } elseif ($desde) {
                    $query->whereDate($column, '>=', $desde);
                } elseif ($hasta) {
                    $query->whereDate($column, '<=', $hasta);
                }
                break;
            case 'igual':
            default:
                if ($desde) {
                    $query->whereDate($column, '=', $desde);
                }
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    private static function aplicarFechaPremio(Builder $query, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->whereRaw(self::SQL_ULTIMO_PREMIO_FECHA.' IS NULL');

            return;
        }

        $desde = self::parsearFecha($valor);
        $hasta = self::parsearFecha($valorHasta);
        $fecha = SqlDialectSupport::fecha(self::SQL_ULTIMO_PREMIO_FECHA);

        switch ($operador) {
            case 'desde':
                if ($desde) {
                    $query->whereRaw("{$fecha} >= ?", [$desde]);
                }
                break;
            case 'hasta':
                if ($desde) {
                    $query->whereRaw("{$fecha} <= ?", [$desde]);
                }
                break;
            case 'entre':
                if ($desde && $hasta) {
                    $query->whereRaw("{$fecha} >= ? AND {$fecha} <= ?", [$desde, $hasta]);
                } elseif ($desde) {
                    $query->whereRaw("{$fecha} >= ?", [$desde]);
                } elseif ($hasta) {
                    $query->whereRaw("{$fecha} <= ?", [$hasta]);
                }
                break;
            case 'igual':
            default:
                if ($desde) {
                    $query->whereRaw("{$fecha} = ?", [$desde]);
                }
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $q  Subquery wrapper o builder principal
     */
    private static function aplicarTelefonoEnQuery(Builder $q, string $operador, string $digitos, bool $orWhere): void
    {
        $patron = match ($operador) {
            'empieza' => self::escapeLike($digitos).'%',
            'termina' => '%'.self::escapeLike($digitos),
            'igual' => self::escapeLike($digitos),
            default => '%'.self::escapeLike($digitos).'%',
        };

        if ($orWhere) {
            $q->orWhere(function ($w) use ($patron) {
                $w->whereRaw(self::SQL_TELEFONO_NORMALIZADO.' LIKE ?', [$patron]);
            });
        } else {
            $q->whereRaw(self::SQL_TELEFONO_NORMALIZADO.' LIKE ?', [$patron]);
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

    private static function soloDigitosTelefono(?string $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private static function parsearFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $valor);

                return $d->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'telefono' => array_keys(self::OPERADORES_TELEFONO),
            'fecha', 'fecha_premio' => array_keys(self::OPERADORES_FECHA),
            'entero' => array_keys(self::OPERADORES_ENTERO),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    public static function esBusquedaSoloConPremios(?string $busqueda): bool
    {
        if (! is_string($busqueda)) {
            return false;
        }

        return in_array(mb_strtolower(trim($busqueda)), [
            'premio',
            'premios',
            'con premio',
            'con premios',
        ], true);
    }

    /**
     * Acepta claves canónicas (biyemas|kandiko|rebisco) y alias de sala (bsa|ksa|rsa).
     */
    public static function normalizarOrigenInput(?string $valor): ?string
    {
        $v = strtolower(trim((string) $valor));
        if ($v === '' || $v === 'todos') {
            return null;
        }

        $alias = [
            'biyemas' => 'biyemas',
            'bsa' => 'biyemas',
            'kandiko' => 'kandiko',
            'ksa' => 'kandiko',
            'rebisco' => 'rebisco',
            'rsa' => 'rebisco',
        ];

        if (isset($alias[$v])) {
            return $alias[$v];
        }

        // "BSA (Biyemas)", "KSA (Kandiko)", etc.
        foreach ($alias as $token => $origen) {
            if ($token === $origen) {
                continue;
            }
            if (str_starts_with($v, $token) || str_contains($v, '('.$origen.')')) {
                return $origen;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'telefono' => self::OPERADORES_TELEFONO,
            'fecha', 'fecha_premio' => self::OPERADORES_FECHA,
            'entero' => self::OPERADORES_ENTERO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
