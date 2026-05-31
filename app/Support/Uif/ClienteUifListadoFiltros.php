<?php

namespace App\Support\Uif;

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

    private const SQL_ULTIMO_PREMIO_FECHA = '(SELECT cp.fechaentrega FROM cliente_premio_uif cp WHERE cp.cliente_uif_id = cliente_uif.id AND cp.deleted_at IS NULL ORDER BY cp.fechaentrega DESC, cp.id DESC LIMIT 1)';

    private const SQL_TELEFONO_NORMALIZADO = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cliente_uif.telefono,''),' ',''),'-',''),'(',''),')',''),'.',''),'+','')";

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

        $conPremios = $request->boolean('filtro_con_premios')
            || self::esBusquedaSoloConPremios($valor);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'con_premios' => $conPremios,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    /**
     * Hay criterios que acotan el listado (muestra «Limpiar filtros»).
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['con_premios'])) {
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

    /** @deprecated Use tieneCriteriosAplicados() */
    public static function tieneFiltrosActivos(array $filtros): bool
    {
        return self::tieneCriteriosAplicados($filtros);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, con_premios: bool, busqueda: string}
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
        if (! empty($filtros['con_premios'])) {
            $params['filtro_con_premios'] = '1';
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['con_premios'])) {
            $query->whereRaw('(SELECT COUNT(*) FROM cliente_premio_uif WHERE cliente_premio_uif.cliente_uif_id = cliente_uif.id AND cliente_premio_uif.deleted_at IS NULL) > 0');
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        if (self::esBusquedaSoloConPremios($valor) && ($filtros['modo'] ?? '') === self::MODO_TODOS) {
            $query->whereRaw('(SELECT COUNT(*) FROM cliente_premio_uif WHERE cliente_premio_uif.cliente_uif_id = cliente_uif.id AND cliente_premio_uif.deleted_at IS NULL) > 0');

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
        $sub = self::SQL_ULTIMO_PREMIO_FECHA;

        switch ($operador) {
            case 'desde':
                if ($desde) {
                    $query->whereRaw("DATE({$sub}) >= ?", [$desde]);
                }
                break;
            case 'hasta':
                if ($desde) {
                    $query->whereRaw("DATE({$sub}) <= ?", [$desde]);
                }
                break;
            case 'entre':
                if ($desde && $hasta) {
                    $query->whereRaw("DATE({$sub}) >= ? AND DATE({$sub}) <= ?", [$desde, $hasta]);
                } elseif ($desde) {
                    $query->whereRaw("DATE({$sub}) >= ?", [$desde]);
                } elseif ($hasta) {
                    $query->whereRaw("DATE({$sub}) <= ?", [$hasta]);
                }
                break;
            case 'igual':
            default:
                if ($desde) {
                    $query->whereRaw("DATE({$sub}) = ?", [$desde]);
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
