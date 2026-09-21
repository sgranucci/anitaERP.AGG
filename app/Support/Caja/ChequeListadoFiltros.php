<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cheques (index).
 */
class ChequeListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cheque.id', 'type' => 'entero', 'label' => 'ID'],
        'numerocheque' => ['column' => 'cheque.numerocheque', 'type' => 'texto', 'label' => 'Número'],
        'nro_interno_anita' => ['column' => 'cheque.nro_interno_anita', 'type' => 'entero', 'label' => 'Int. Anita'],
        'origen' => ['column' => 'cheque.origen', 'type' => 'texto', 'label' => 'Origen'],
        'estado' => ['column' => 'cheque.estado', 'type' => 'texto', 'label' => 'Estado'],
        'banco' => ['column' => 'banco.nombre', 'type' => 'texto', 'label' => 'Banco'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'cliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente'],
        'monto' => ['column' => 'cheque.monto', 'type' => 'decimal', 'label' => 'Monto'],
        'entregado' => ['column' => 'cheque.entregado', 'type' => 'texto', 'label' => 'Entregado'],
        'anombrede' => ['column' => 'cheque.anombrede', 'type' => 'texto', 'label' => 'A nombre de'],
    ];

    /** @var array<string, array{column: string, label: string}> */
    public const ORDENES = [
        'fechapago' => ['column' => 'cheque.fechapago', 'label' => 'Fecha de cheque'],
        'fechaemision' => ['column' => 'cheque.fechaemision', 'label' => 'Fecha de emisión'],
        'numerocheque' => ['column' => 'cheque.numerocheque', 'label' => 'Número'],
        'monto' => ['column' => 'cheque.monto', 'label' => 'Monto'],
        'id' => ['column' => 'cheque.id', 'label' => 'ID'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cheque.numerocheque',
        'cheque.entregado',
        'cheque.anombrede',
        'banco.nombre',
        'empresa.nombre',
        'cliente.nombre',
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

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = [
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

        $campo = (string) $request->input('filtro_campo', 'numerocheque');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numerocheque';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numerocheque');

        $cartera = $request->boolean('cartera');
        $paraDepositar = $request->boolean('para_depositar');
        $paraDepositarHasta = trim((string) $request->input('para_depositar_hasta', ''));
        if ($paraDepositarHasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paraDepositarHasta)) {
            $paraDepositarHasta = '';
        }
        if ($paraDepositar && $paraDepositarHasta === '') {
            $paraDepositarHasta = date('Y-m-d');
        }
        [$orden, $ordenDir] = self::resolverOrden($request);

        $origen = strtoupper(trim((string) $request->input('origen', '')));
        if (! in_array($origen, ['E', 'R'], true)) {
            $origen = '';
        }
        // Conserva espacio (DIFERIDO) si viene en el query.
        $estadoRaw = $request->input('estado');
        $estado = $estadoRaw === null ? '' : (string) $estadoRaw;
        if ($cartera || $paraDepositar) {
            $origen = '';
            $estado = '';
        }
        if ($paraDepositar) {
            $cartera = false;
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
            'cartera' => $cartera,
            'para_depositar' => $paraDepositar,
            'para_depositar_hasta' => $paraDepositarHasta,
            'origen' => $origen,
            'estado' => $estado,
            'orden' => $orden,
            'orden_dir' => $ordenDir,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function resolverOrden(Request $request): array
    {
        $orden = (string) $request->input('orden', 'fechapago');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        $dir = strtolower((string) $request->input('orden_dir', 'desc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [$orden, $dir];
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

    /**
     * Criterios del panel / búsqueda rápida (sin filtros externos de empresa / cartera / origen).
     */
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
        if (self::tieneCriteriosTexto($filtros)) {
            return true;
        }

        if (! empty($filtros['cartera'])) {
            return true;
        }

        if (! empty($filtros['para_depositar'])) {
            return true;
        }

        if (trim((string) ($filtros['origen'] ?? '')) !== '') {
            return true;
        }

        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *   modo: string,
     *   campo: string,
     *   operador: string,
     *   valor: string,
     *   valor_hasta: string,
     *   busqueda: string,
     *   empresa_id: ?int,
     *   empresa_scope: string,
     *   cartera: bool,
     *   para_depositar: bool,
     *   origen: string,
     *   estado: string,
     *   orden: string,
     *   orden_dir: string
     * }
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numerocheque',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'cartera' => false,
            'para_depositar' => false,
            'para_depositar_hasta' => '',
            'origen' => '',
            'estado' => '',
            'orden' => 'fechapago',
            'orden_dir' => 'desc',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (! empty($filtros['cartera'])) {
            $params['cartera'] = 1;
        }
        if (! empty($filtros['para_depositar'])) {
            $params['para_depositar'] = 1;
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $params['para_depositar_hasta'] = $hasta;
            }
        }
        if (($filtros['origen'] ?? '') !== '') {
            $params['origen'] = $filtros['origen'];
        }
        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            $params['estado'] = $filtros['estado'];
        }
        $params = array_merge($params, self::paraQueryStringOrden($filtros));

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerocheque';
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

    /**
     * Empresa + quick filters (cartera / origen / estado) para limpiar solo el texto.
     *
     * @return array<string, string|int|bool>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (! empty($filtros['cartera'])) {
            $params['cartera'] = 1;
        }
        if (! empty($filtros['para_depositar'])) {
            $params['para_depositar'] = 1;
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $params['para_depositar_hasta'] = $hasta;
            }
        }
        if (($filtros['origen'] ?? '') !== '') {
            $params['origen'] = $filtros['origen'];
        }
        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            $params['estado'] = $filtros['estado'];
        }
        $params = array_merge($params, self::paraQueryStringOrden($filtros));

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public static function paraQueryStringOrden(array $filtros): array
    {
        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        $dir = (string) ($filtros['orden_dir'] ?? 'desc');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }
        if ($orden === 'fechapago' && $dir === 'desc') {
            return [];
        }

        return [
            'orden' => $orden,
            'orden_dir' => $dir,
        ];
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        $dir = (string) ($filtros['orden_dir'] ?? 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $query->orderBy(self::ORDENES[$orden]['column'], $dir);
        if ($orden !== 'id') {
            $query->orderBy('cheque.id', 'desc');
        }
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('cheque.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! empty($filtros['para_depositar'])) {
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $hasta = date('Y-m-d');
            }
            self::aplicarSoloParaDepositar($query, $hasta);
        } elseif (! empty($filtros['cartera'])) {
            $query->where('cheque.origen', 'R')
                ->whereNull('cheque.pagoproveedor_id')
                ->where(function ($q) {
                    $q->whereIn('cheque.estado', [' ', 'N'])
                        ->orWhereNull('cheque.estado')
                        ->orWhere('cheque.estado', '');
                });
        } else {
            if (($filtros['origen'] ?? '') !== '') {
                $query->where('cheque.origen', $filtros['origen']);
            }
            if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('cheque.estado', $filtros['estado']);
            }
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numerocheque', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * CHT en cartera con fechapago ≤ fecha de corte (default hoy).
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicarSoloParaDepositar(Builder $query, ?string $hastaYmd = null): void
    {
        $hasta = $hastaYmd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaYmd)
            ? $hastaYmd
            : date('Y-m-d');

        $query->where('cheque.origen', 'R')
            ->whereNull('cheque.pagoproveedor_id')
            ->whereNull('cheque.fecha_deposito')
            ->whereDate('cheque.fechapago', '<=', $hasta)
            ->where(function ($q) {
                $q->whereNull('cheque.estado')
                    ->orWhereNotIn('cheque.estado', ['R', 'A', '*']);
            })
            ->where(function ($q) {
                $q->whereNull('cheque.nro_caucion')
                    ->orWhere('cheque.nro_caucion', '')
                    ->orWhere('cheque.nro_caucion', '0');
            });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cheque.numerocheque', 'cheque.entregado', 'cheque.anombrede'] as $col) {
                    $q->where(function ($w) use ($col) {
                        $w->whereNull($col)->orWhere($col, '');
                    });
                }
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
                $q->orWhere('cheque.id', (int) $id)
                    ->orWhere('cheque.nro_interno_anita', (int) $id);
            }
            $textCols = [
                'cheque.numerocheque',
                'cheque.origen',
                'cheque.estado',
                'cheque.entregado',
                'cheque.anombrede',
                'banco.nombre',
                'empresa.nombre',
                'cliente.nombre',
                'cliente.codigo',
                'moneda.nombre',
                'moneda.abreviatura',
            ];
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
            self::aplicarCoincidenciaOrigenEstado($q, $valor, $operador);
            if (is_numeric(str_replace([',', '.'], ['', '.'], $valor))) {
                $monto = (float) str_replace(',', '.', $valor);
                $q->orWhere('cheque.monto', $monto);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarCoincidenciaOrigenEstado(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }

        $valorNorm = mb_strtolower($valor);
        if (str_contains($valorNorm, 'emit')) {
            $query->orWhere('cheque.origen', 'E');
        }
        if (str_contains($valorNorm, 'recib') || str_contains($valorNorm, 'cartera')) {
            $query->orWhere('cheque.origen', 'R');
        }
        if (str_contains($valorNorm, 'rechaz')) {
            $query->orWhere('cheque.estado', 'R');
        }
        if (str_contains($valorNorm, 'anul')) {
            $query->orWhere('cheque.estado', 'A');
        }
        if (str_contains($valorNorm, 'debit')) {
            $query->orWhere('cheque.estado', '*');
        }
        if (str_contains($valorNorm, 'difer')) {
            $query->orWhere('cheque.estado', ' ');
        }
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numerocheque'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'decimal') {
            self::aplicarDecimal($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($campoKey === 'origen') {
            self::aplicarOrigen($query, $operador, $valor);

            return;
        }

        if ($campoKey === 'estado') {
            self::aplicarEstado($query, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarOrigen(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cheque.origen')->orWhere('cheque.origen', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $codigo = match (true) {
            str_contains($valorNorm, 'emit') => 'E',
            str_contains($valorNorm, 'recib') => 'R',
            in_array(mb_strtoupper($valor), ['E', 'R'], true) => mb_strtoupper($valor),
            default => null,
        };

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where('cheque.origen', $codigo);

            return;
        }

        self::aplicarTexto($query, 'cheque.origen', $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cheque.estado')->orWhere('cheque.estado', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $codigo = match (true) {
            str_contains($valorNorm, 'difer') => ' ',
            str_contains($valorNorm, 'debit') => '*',
            str_contains($valorNorm, 'cierr') => 'C',
            str_contains($valorNorm, 'anul') => 'A',
            str_contains($valorNorm, 'rechaz') => 'R',
            str_contains($valorNorm, 'no_pres') || str_contains($valorNorm, 'no present') => 'N',
            in_array(mb_strtoupper($valor), [' ', '*', 'C', 'A', 'R', 'N'], true) => mb_strtoupper($valor),
            default => null,
        };

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where('cheque.estado', $codigo);

            return;
        }

        self::aplicarTexto($query, 'cheque.estado', $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
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
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
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
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarDecimal(Builder $query, string $column, string $operador, string $valor): void
    {
        $normalizado = str_replace(',', '.', trim($valor));
        if ($normalizado === '' || ! is_numeric($normalizado)) {
            return;
        }
        $num = (float) $normalizado;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $num);
                break;
            case 'menor':
                $query->where($column, '<', $num);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $num);
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
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO),
        };

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

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'decimal' => self::OPERADORES_DECIMAL,
            default => self::OPERADORES_TEXTO,
        };
    }
}
