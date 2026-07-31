<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cuentas de caja (index).
 */
class CuentacajaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cuentacaja.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'cuentacaja.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'descripcion_operaciones' => ['column' => 'cuentacaja.descripcion_operaciones', 'type' => 'texto', 'label' => 'Desc. operaciones'],
        'codigo' => ['column' => 'cuentacaja.codigo', 'type' => 'texto', 'label' => 'Código'],
        'tipocuenta' => ['column' => 'cuentacaja.tipocuenta', 'type' => 'texto', 'label' => 'Tipo cuenta'],
        'banco' => ['column' => 'banco.nombre', 'type' => 'texto', 'label' => 'Banco'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'cuentacontable' => ['column' => 'cuentacontable.nombre', 'type' => 'texto', 'label' => 'Cuenta contable'],
        'cuentacontable_codigo' => ['column' => 'cuentacontable.codigo', 'type' => 'texto', 'label' => 'Código cuenta contable'],
        'moneda' => ['column' => 'moneda.nombre', 'type' => 'texto', 'label' => 'Moneda'],
        'cbu' => ['column' => 'cuentacaja.cbu', 'type' => 'texto', 'label' => 'CBU'],
        'cuenta_interbanking' => ['column' => 'cuentacaja.cuenta_interbanking', 'type' => 'texto', 'label' => 'Cuenta Interbanking'],
        'usos' => ['column' => 'usocuentacaja.nombre', 'type' => 'usos', 'label' => 'Usos'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cuentacaja.nombre',
        'cuentacaja.descripcion_operaciones',
        'banco.nombre',
        'empresa.nombre',
        'cuentacontable.nombre',
        'cuentacaja.cbu',
        'cuentacaja.cuenta_interbanking',
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
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
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

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
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

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cuentacaja.nombre', 'cuentacaja.codigo', 'cuentacaja.cbu'] as $col) {
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
                $q->orWhere('cuentacaja.id', (int) $id);
            }
            $textCols = [
                'cuentacaja.nombre',
                'cuentacaja.descripcion_operaciones',
                'cuentacaja.codigo',
                'cuentacaja.tipocuenta',
                'cuentacaja.cbu',
                'cuentacaja.cuenta_interbanking',
                'banco.nombre',
                'empresa.nombre',
                'cuentacontable.codigo',
                'cuentacontable.nombre',
                'moneda.nombre',
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
            self::aplicarCoincidenciaTipoCuenta($q, $valor, $operador);
            $q->orWhereHas('usocuentacajas', function ($r) use ($like, $valor, $operador) {
                $r->where('usocuentacaja.nombre', 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar(
                        $r,
                        'usocuentacaja.nombre',
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    private static function aplicarCoincidenciaTipoCuenta(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }

        $valorNorm = mb_strtolower($valor);
        if (str_contains($valorNorm, 'valor')) {
            $query->orWhere('cuentacaja.tipocuenta', 'V');
        }
        if (str_contains($valorNorm, 'reten')) {
            $query->orWhere('cuentacaja.tipocuenta', 'R');
        }
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        $type = $def['type'];

        if ($type === 'usos') {
            self::aplicarUsos($query, $operador, $valor);

            return;
        }

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($campoKey === 'tipocuenta') {
            self::aplicarTipoCuenta($query, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    private static function aplicarUsos(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereDoesntHave('usocuentacajas');

            return;
        }
        if ($valor === '') {
            return;
        }

        $query->whereHas('usocuentacajas', function ($q) use ($operador, $valor) {
            self::aplicarTexto($q, 'usocuentacaja.nombre', $operador, $valor);
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
     */
    private static function aplicarTipoCuenta(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cuentacaja.tipocuenta')->orWhere('cuentacaja.tipocuenta', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $codigo = match (true) {
            str_contains($valorNorm, 'reten') => 'R',
            str_contains($valorNorm, 'valor') => 'V',
            in_array(mb_strtoupper($valor), ['V', 'R'], true) => mb_strtoupper($valor),
            default => null,
        };

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where('cuentacaja.tipocuenta', $codigo);

            return;
        }

        self::aplicarTexto($query, 'cuentacaja.tipocuenta', $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
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
     * @param  Builder<\App\Models\Caja\Cuentacaja>  $query
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
            default => self::OPERADORES_TEXTO,
        };
    }
}
