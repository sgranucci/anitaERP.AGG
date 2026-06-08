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
        ];
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
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['valor_hasta'] ?? '')) !== ''
            || (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
                && in_array($filtros['operador'] ?? '', ['vacio'], true));
    }

    public static function paraQueryString(array $filtros): array
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return [];
        }
        $params = [
            'filtro_modo' => $filtros['modo'] ?? self::MODO_TODOS,
            'filtro_valor' => $filtros['valor'] ?? '',
        ];
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerorequisicion';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
            if (($filtros['operador'] ?? '') === 'entre') {
                $params['filtro_valor_hasta'] = $filtros['valor_hasta'] ?? '';
            }
        }

        return $params;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
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
        $query->where(function ($q) use ($valor) {
            if (is_numeric($valor)) {
                $id = (int) $valor;
                $q->where('requisicion_sala.id', $id)
                    ->orWhere('requisicion_sala.numerorequisicion', $id);
            }
            CoincidenciaFlexibleTexto::aplicar($q, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, $valor, 'contiene');
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
            if ($valor !== '' && is_numeric($valor)) {
                $query->where($column, (int) $valor);
            }

            return;
        }
        if ($valor === '') {
            return;
        }
        if ($operador === 'contiene' && in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
            CoincidenciaFlexibleTexto::aplicar($query, [$column], $valor, 'contiene');

            return;
        }
        match ($operador) {
            'empieza' => $query->where($column, 'like', $valor.'%'),
            'termina' => $query->where($column, 'like', '%'.$valor),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where($column, 'like', '%'.$valor.'%'),
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
