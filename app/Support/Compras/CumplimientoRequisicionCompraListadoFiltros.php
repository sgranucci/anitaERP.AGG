<?php

namespace App\Support\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CumplimientoRequisicionCompraListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const CAMPOS = [
        'id' => ['column' => 'cumplimiento_requisicion_compra.id', 'type' => 'entero', 'label' => 'ID'],
        'numero' => ['column' => 'cumplimiento_requisicion_compra.numero', 'type' => 'entero', 'label' => 'Número'],
        'fecha' => ['column' => 'cumplimiento_requisicion_compra.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'nombreusuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Usuario'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'estado' => ['column' => 'cumplimiento_requisicion_compra.estado', 'type' => 'texto', 'label' => 'Estado'],
        'numerorequisicion' => ['column' => 'requisicion.numerorequisicion', 'type' => 'entero', 'label' => 'Req. Nº'],
        'leyenda' => ['column' => 'cumplimiento_requisicion_compra.leyenda', 'type' => 'texto', 'label' => 'Leyenda'],
    ];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'usuario.nombre',
        'empresa.nombre',
        'cumplimiento_requisicion_compra.leyenda',
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
        $campo = (string) $request->input('filtro_campo', 'numero');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numero';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');
        $valor2 = trim((string) $request->input('filtro_valor2', ''));
        $requisicionId = (int) $request->input('requisicion_id', 0);

        return [
            'valor' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor2' => $valor2,
            'requisicion_id' => $requisicionId > 0 ? $requisicionId : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'busqueda_rapida' => false,
            'modo' => self::MODO_TODOS,
            'campo' => 'numero',
            'operador' => 'contiene',
            'valor2' => '',
            'requisicion_id' => null,
        ];
    }

    /** @param  array<string, mixed>  $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['requisicion_id'])) {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || (($filtros['modo'] ?? '') === self::MODO_CAMPO && self::operadorRequiereValor($filtros));
    }

    /** @param  array<string, mixed>  $filtros */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (array_key_exists('valor', $filtros)) {
            $out['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = '1';
        }
        foreach (['modo' => 'filtro_modo', 'campo' => 'filtro_campo', 'operador' => 'filtro_operador', 'valor2' => 'filtro_valor2'] as $src => $dst) {
            if (isset($filtros[$src]) && $filtros[$src] !== '' && $filtros[$src] !== null) {
                $out[$dst] = $filtros[$src];
            }
        }
        if (! empty($filtros['requisicion_id'])) {
            $out['requisicion_id'] = $filtros['requisicion_id'];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['requisicion_id'])) {
            $reqId = (int) $filtros['requisicion_id'];
            $query->whereHas('articulos', fn ($q) => $q->where('requisicion_id', $reqId));
        }

        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $query->leftJoin('usuario', 'usuario.id', '=', 'cumplimiento_requisicion_compra.usuario_id');
        $query->leftJoin('empresa', 'empresa.id', '=', 'cumplimiento_requisicion_compra.empresa_id');

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $campo = $filtros['campo'] ?? 'numero';
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO && isset(self::CAMPOS[$campo])) {
            if ($campo === 'numerorequisicion') {
                $query->whereHas('articulos.requisicion', function ($q) use ($operador, $valor, $filtros) {
                    self::aplicarOperador($q, 'requisicion.numerorequisicion', 'entero', $operador, $valor, $filtros['valor2'] ?? '');
                });

                return;
            }
            $def = self::CAMPOS[$campo];
            self::aplicarOperador($query, $def['column'], $def['type'], $operador, $valor, $filtros['valor2'] ?? '');

            return;
        }

        if ($valor === '') {
            return;
        }

        $query->where(function ($q) use ($valor) {
            $q->where('cumplimiento_requisicion_compra.numero', 'like', '%'.$valor.'%')
                ->orWhere('cumplimiento_requisicion_compra.id', 'like', '%'.$valor.'%')
                ->orWhere('usuario.nombre', 'like', '%'.$valor.'%')
                ->orWhere('empresa.nombre', 'like', '%'.$valor.'%')
                ->orWhere('cumplimiento_requisicion_compra.leyenda', 'like', '%'.$valor.'%');
            foreach (self::COLUMNAS_COINCIDENCIA_FLEXIBLE as $columnaFlexible) {
                CoincidenciaFlexibleTexto::aplicar($q, $columnaFlexible, $valor);
            }
            $q->orWhereHas('articulos.requisicion', fn ($sq) => $sq->where('numerorequisicion', 'like', '%'.$valor.'%'));
        });
    }

    /** @param  array<string, mixed>  $filtros */
    private static function operadorRequiereValor(array $filtros): bool
    {
        $operador = $filtros['operador'] ?? 'contiene';

        return ! in_array($operador, ['vacio'], true);
    }

    private static function aplicarOperador(Builder $query, string $column, string $type, string $operador, string $valor, string $valor2): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($type === 'fecha') {
            self::aplicarOperadorFecha($query, $column, $operador, $valor, $valor2);

            return;
        }

        if ($type === 'entero') {
            if ($valor === '' && $operador !== 'vacio') {
                return;
            }
            $num = (int) $valor;
            match ($operador) {
                'igual' => $query->where($column, $num),
                'mayor' => $query->where($column, '>', $num),
                'menor' => $query->where($column, '<', $num),
                'distinto' => $query->where($column, '!=', $num),
                default => $query->where($column, 'like', '%'.$valor.'%'),
            };

            return;
        }

        if ($valor === '') {
            return;
        }

        match ($operador) {
            'contiene' => $query->where(function ($q) use ($column, $valor) {
                $q->where($column, 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, $column, $valor);
            }),
            'empieza' => $query->where($column, 'like', $valor.'%'),
            'termina' => $query->where($column, 'like', '%'.$valor),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where($column, 'like', '%'.$valor.'%'),
        };
    }

    private static function aplicarOperadorFecha(Builder $query, string $column, string $operador, string $valor, string $valor2): void
    {
        $parse = static function (string $v): ?string {
            if ($v === '') {
                return null;
            }
            try {
                return Carbon::parse($v)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        };
        $f1 = $parse($valor);
        $f2 = $parse($valor2);
        match ($operador) {
            'igual' => $f1 && $query->whereDate($column, $f1),
            'desde' => $f1 && $query->whereDate($column, '>=', $f1),
            'hasta' => $f1 && $query->whereDate($column, '<=', $f1),
            'entre' => ($f1 && $f2) && $query->whereBetween($column, [$f1.' 00:00:00', $f2.' 23:59:59']),
            default => null,
        };
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $type = self::CAMPOS[$campo]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            CumplimientoRequisicionCompra::ESTADO_ACTIVO => 'ACTIVO',
            CumplimientoRequisicionCompra::ESTADO_REVERTIDO => 'REVERTIDO',
            default => $estado,
        };
    }
}
