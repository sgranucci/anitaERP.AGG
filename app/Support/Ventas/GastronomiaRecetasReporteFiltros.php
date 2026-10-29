<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Illuminate\Http\Request;

final class GastronomiaRecetasReporteFiltros
{
    public const VISTA_RECETAS = 'recetas';

    public const VISTA_LINEAS = 'lineas';

    public const VISTA_RESUMEN = 'resumen';

    public const VISTA_ALERTAS = 'alertas';

    /**
     * @return array{empresa_id:int,categoria_id:int,subcategoria_id:int,activos:bool,inactivos:bool,sin_asignar:bool,vista:string}
     */
    public static function porDefecto(): array
    {
        return [
            'empresa_id' => 0,
            'categoria_id' => 0,
            'subcategoria_id' => 0,
            'activos' => true,
            'inactivos' => false,
            'sin_asignar' => false,
            'vista' => self::VISTA_RECETAS,
        ];
    }

    /**
     * @return array{empresa_id:int,categoria_id:int,subcategoria_id:int,activos:bool,inactivos:bool,sin_asignar:bool,vista:string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $consulta = $request->boolean('consultar')
            || $request->has('activos')
            || $request->has('inactivos')
            || $request->has('sin_asignar');

        if (! $consulta) {
            $filtros = self::porDefecto();
            $filtros['empresa_id'] = max(0, (int) $request->input('empresa_id', 0));
            $filtros['categoria_id'] = max(0, (int) $request->input('categoria_id', 0));
            $filtros['subcategoria_id'] = max(0, (int) $request->input('subcategoria_id', 0));

            return $filtros;
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'categoria_id' => max(0, (int) $request->input('categoria_id', 0)),
            'subcategoria_id' => max(0, (int) $request->input('subcategoria_id', 0)),
            'activos' => $request->boolean('activos'),
            'inactivos' => $request->boolean('inactivos'),
            'sin_asignar' => $request->boolean('sin_asignar'),
            'vista' => self::normalizarVista($request->input('vista')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriterios(array $filtros): bool
    {
        return ! empty($filtros['activos']) || ! empty($filtros['inactivos']) || ! empty($filtros['sin_asignar']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'categoria_id' => (int) ($filtros['categoria_id'] ?? 0),
            'subcategoria_id' => (int) ($filtros['subcategoria_id'] ?? 0),
            'vista' => self::normalizarVista($filtros['vista'] ?? null),
        ];
        if (! empty($filtros['activos'])) {
            $params['activos'] = 1;
        }
        if (! empty($filtros['inactivos'])) {
            $params['inactivos'] = 1;
        }
        if (! empty($filtros['sin_asignar'])) {
            $params['sin_asignar'] = 1;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function textoInclusion(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['activos'])) {
            $partes[] = 'artículos de venta activos';
        }
        if (! empty($filtros['inactivos'])) {
            $partes[] = 'artículos de venta inactivos';
        }
        if (! empty($filtros['sin_asignar'])) {
            $partes[] = 'recetas sin artículo';
        }

        return $partes === [] ? 'sin criterio de inclusión' : implode(', ', $partes);
    }

    public static function normalizarVista(mixed $vista): string
    {
        $vista = strtolower(trim((string) $vista));

        return in_array($vista, [
            self::VISTA_RECETAS,
            self::VISTA_LINEAS,
            self::VISTA_RESUMEN,
            self::VISTA_ALERTAS,
        ], true) ? $vista : self::VISTA_RECETAS;
    }
}
