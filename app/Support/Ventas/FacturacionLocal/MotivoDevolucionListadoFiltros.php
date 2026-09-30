<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MotivoDevolucionListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'codigo' => ['column' => 'motivo_devolucion.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'motivo_devolucion.nombre', 'type' => 'texto', 'label' => 'Nombre'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'motivo_devolucion.nombre',
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

    /**
     * @return array<string, mixed>
     */
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
        if (! isset(self::OPERADORES_TEXTO[$operador])) {
            $operador = 'contiene';
        }

        $activo = trim((string) $request->input('filtro_activo', ''));
        if (! in_array($activo, ['', '1', '0'], true)) {
            $activo = '';
        }
        $stock = trim((string) $request->input('filtro_vuelve_stock', ''));
        if (! in_array($stock, ['', '1', '0'], true)) {
            $stock = '';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'activo' => $activo,
            'vuelve_stock' => $stock,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'activo' => '',
            'vuelve_stock' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['activo'] ?? '')) !== ''
            || trim((string) ($filtros['vuelve_stock'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $out['filtro_valor'] = $valor;
            $out['filtro_modo'] = (string) ($filtros['modo'] ?? self::MODO_TODOS);
            $out['filtro_campo'] = (string) ($filtros['campo'] ?? 'nombre');
            $out['filtro_operador'] = (string) ($filtros['operador'] ?? 'contiene');
            if (! empty($filtros['busqueda_rapida'])) {
                $out['filtro_busqueda_rapida'] = 1;
            }
        }
        if (trim((string) ($filtros['activo'] ?? '')) !== '') {
            $out['filtro_activo'] = (string) $filtros['activo'];
        }
        if (trim((string) ($filtros['vuelve_stock'] ?? '')) !== '') {
            $out['filtro_vuelve_stock'] = (string) $filtros['vuelve_stock'];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        unset($campo);

        return self::OPERADORES_TEXTO;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return $query;
        }
        if (($filtros['activo'] ?? '') === '1') {
            $query->where('motivo_devolucion.activo', true);
        } elseif (($filtros['activo'] ?? '') === '0') {
            $query->where('motivo_devolucion.activo', false);
        }
        if (($filtros['vuelve_stock'] ?? '') === '1') {
            $query->where('motivo_devolucion.vuelve_stock', true);
        } elseif (($filtros['vuelve_stock'] ?? '') === '0') {
            $query->where('motivo_devolucion.vuelve_stock', false);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        if ($modo === self::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'nombre');
            $column = self::CAMPOS[$campo]['column'] ?? self::CAMPOS['nombre']['column'];
            self::aplicarOperador($query, $column, $operador, $valor);

            return $query;
        }

        $query->where(function (Builder $q) use ($valor) {
            foreach (self::CAMPOS as $meta) {
                $q->orWhere(function (Builder $sub) use ($meta, $valor) {
                    self::aplicarOperador($sub, $meta['column'], 'contiene', $valor);
                });
            }
        });

        return $query;
    }

    private static function aplicarOperador(Builder $query, string $column, string $operador, string $valor): void
    {
        match ($operador) {
            'empieza' => $query->where($column, 'like', $valor.'%'),
            'termina' => $query->where($column, 'like', '%'.$valor),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '<>', $valor),
            'vacio' => $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            }),
            default => $query->where(function (Builder $q) use ($column, $valor) {
                $q->where($column, 'like', '%'.$valor.'%');
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor);
                }
            }),
        };
    }
}
