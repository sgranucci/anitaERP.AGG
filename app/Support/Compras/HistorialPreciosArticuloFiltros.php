<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de historial de precios de compra por artículo/proveedor.
 */
final class HistorialPreciosArticuloFiltros
{
    public const MODO_RESUMEN = 'resumen';

    public const MODO_DETALLE = 'detalle';

    public const AGRUPACION_ARTICULO = 'articulo';

    public const AGRUPACION_ARTICULO_PROVEEDOR = 'articulo_proveedor';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO = [
        ['valor' => self::MODO_RESUMEN, 'etiqueta' => 'Resumen (último / anterior / variación)'],
        ['valor' => self::MODO_DETALLE, 'etiqueta' => 'Detalle de compras (todas las variaciones)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_ARTICULO, 'etiqueta' => 'Por artículo'],
        ['valor' => self::AGRUPACION_ARTICULO_PROVEEDOR, 'etiqueta' => 'Por artículo y proveedor'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $modo = trim((string) $request->input('modo', self::MODO_RESUMEN));
        if (! self::modoValido($modo)) {
            $modo = self::MODO_RESUMEN;
        }

        $agrupacion = trim((string) $request->input('agrupacion', self::AGRUPACION_ARTICULO));
        if (! self::agrupacionValida($agrupacion)) {
            $agrupacion = self::AGRUPACION_ARTICULO;
        }

        $articuloId = (int) $request->input('articulo_id', 0);

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'articulo_id' => $articuloId > 0 ? $articuloId : null,
            'sku' => trim((string) $request->input('sku', '')),
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'modo' => $modo,
            'agrupacion' => $agrupacion,
            'solo_con_variacion' => $request->boolean('solo_con_variacion'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'articulo_id' => ! empty($filtros['articulo_id']) ? (int) $filtros['articulo_id'] : null,
            'sku' => ($filtros['sku'] ?? '') !== '' ? ($filtros['sku'] ?? null) : null,
            'proveedores' => ($filtros['proveedores'] ?? '') !== '' ? ($filtros['proveedores'] ?? null) : null,
            'modo' => ($filtros['modo'] ?? self::MODO_RESUMEN) !== self::MODO_RESUMEN
                ? ($filtros['modo'] ?? null)
                : null,
            'agrupacion' => ($filtros['agrupacion'] ?? self::AGRUPACION_ARTICULO) !== self::AGRUPACION_ARTICULO
                ? ($filtros['agrupacion'] ?? null)
                : null,
            'solo_con_variacion' => ! empty($filtros['solo_con_variacion']) ? 1 : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy->copy()->subYear()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->copy()->endOfMonth()->format('Y-m-d'),
            'articulo_id' => null,
            'sku' => '',
            'proveedores' => '',
            'modo' => self::MODO_RESUMEN,
            'agrupacion' => self::AGRUPACION_ARTICULO,
            'solo_con_variacion' => false,
        ];
    }

    public static function modoValido(string $modo): bool
    {
        return in_array($modo, [self::MODO_RESUMEN, self::MODO_DETALLE], true);
    }

    public static function agrupacionValida(string $agrupacion): bool
    {
        return in_array($agrupacion, [self::AGRUPACION_ARTICULO, self::AGRUPACION_ARTICULO_PROVEEDOR], true);
    }

    public static function etiquetaModo(string $modo): string
    {
        foreach (self::OPCIONES_MODO as $opcion) {
            if ($opcion['valor'] === $modo) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_MODO[0]['etiqueta'];
    }

    public static function etiquetaAgrupacion(string $agrupacion): string
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_AGRUPACION[0]['etiqueta'];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = $filtros['fecha_desde'] ?? '';
        $hasta = $filtros['fecha_hasta'] ?? '';

        $fmt = static function (?string $fecha): string {
            if ($fecha === null || $fecha === '') {
                return '';
            }
            try {
                return Carbon::parse($fecha)->format('d/m/Y');
            } catch (\Throwable) {
                return $fecha;
            }
        };

        if ($desde !== '' && $hasta !== '') {
            return $fmt($desde).' — '.$fmt($hasta);
        }
        if ($desde !== '') {
            return 'Desde '.$fmt($desde);
        }
        if ($hasta !== '') {
            return 'Hasta '.$fmt($hasta);
        }

        return 'Sin período';
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
