<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de remesas.
 */
final class RemesaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'numero' => ['column' => 'remesa.numero', 'type' => 'entero', 'label' => 'Número'],
        'fecha' => ['column' => 'remesa.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'tipo' => ['column' => 'remesa.tipo', 'type' => 'texto', 'label' => 'Tipo'],
        'estado' => ['column' => 'remesa.estado', 'type' => 'texto', 'label' => 'Estado'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'remito' => ['column' => 'remesa.remito', 'type' => 'texto', 'label' => 'Remito'],
        'observacion' => ['column' => 'remesa.observacion', 'type' => 'texto', 'label' => 'Observación'],
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'igual' => 'Igual a',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numero',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'tipo' => '',
            'estado' => '',
            'empresas_asignadas' => [],
        ];
    }

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
        $modo = $busquedaRapida ? self::MODO_TODOS : (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'numero');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numero';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => (string) $request->input('filtro_operador', 'contiene'),
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'fecha_desde' => (string) $request->input('fecha_desde', ''),
            'fecha_hasta' => (string) $request->input('fecha_hasta', ''),
            'tipo' => (string) $request->input('tipo', ''),
            'estado' => (string) $request->input('estado', ''),
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

    /**
     * Criterios del panel / búsqueda (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        foreach (['fecha_desde', 'fecha_hasta', 'tipo', 'estado', 'valor'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                return true;
            }
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = self::paraQueryStringEmpresa($filtros);

        foreach (['filtro_modo' => 'modo', 'filtro_campo' => 'campo', 'filtro_operador' => 'operador', 'filtro_valor' => 'valor'] as $q => $k) {
            if (($filtros[$k] ?? '') !== '' && ($filtros[$k] ?? null) !== null) {
                $out[$q] = $filtros[$k];
            }
        }
        if (($filtros['modo'] ?? '') === self::MODO_TODOS && trim((string) ($filtros['valor'] ?? '')) !== '') {
            $out['filtro_busqueda_rapida'] = 1;
        }
        foreach (['fecha_desde', 'fecha_hasta', 'tipo', 'estado'] as $k) {
            if (($filtros[$k] ?? '') !== '' && ($filtros[$k] ?? 0) !== 0) {
                $out[$k] = $filtros[$k];
            }
        }

        return $out;
    }

    /**
     * Solo el filtro externo de empresa (Limpiar texto sin perder empresa).
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

    public static function aplicar(Builder $query, array $filtros): void
    {
        if (($filtros['empresa_scope'] ?? 'una') !== 'todas' && ! empty($filtros['empresa_id'])) {
            $query->where('remesa.empresa_id', (int) $filtros['empresa_id']);
        } elseif (! empty($filtros['empresas_asignadas']) && is_array($filtros['empresas_asignadas'])) {
            $query->whereIn('remesa.empresa_id', $filtros['empresas_asignadas']);
        }

        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('remesa.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('remesa.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $query->where('remesa.tipo', $filtros['tipo']);
        }
        if (($filtros['estado'] ?? '') !== '') {
            $query->where('remesa.estado', $filtros['estado']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_TODOS) {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('remesa.numero', 'like', '%'.$valor.'%')
                    ->orWhere('remesa.remito', 'like', '%'.$valor.'%')
                    ->orWhere('remesa.observacion', 'like', '%'.$valor.'%')
                    ->orWhere('empresa.nombre', 'like', '%'.$valor.'%');
            });

            return;
        }

        $meta = self::CAMPOS[(string) ($filtros['campo'] ?? '')] ?? null;
        if ($meta === null) {
            return;
        }
        $col = $meta['column'];
        $op = (string) ($filtros['operador'] ?? 'contiene');
        if ($meta['type'] === 'entero') {
            $query->where($col, (int) $valor);

            return;
        }
        match ($op) {
            'igual' => $query->where($col, $valor),
            'empieza' => $query->where($col, 'like', $valor.'%'),
            'termina' => $query->where($col, 'like', '%'.$valor),
            default => $query->where($col, 'like', '%'.$valor.'%'),
        };
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if (($meta['type'] ?? '') === 'entero') {
            return self::OPERADORES_ENTERO;
        }

        return self::OPERADORES_TEXTO;
    }
}
