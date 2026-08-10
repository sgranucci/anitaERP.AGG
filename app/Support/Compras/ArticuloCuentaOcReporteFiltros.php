<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de cuentas contables de artículos cruzado con OC Anita.
 */
final class ArticuloCuentaOcReporteFiltros
{
    public const MODO_RESUMEN = 'resumen';

    public const MODO_DETALLE = 'detalle';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO = [
        ['valor' => self::MODO_RESUMEN, 'etiqueta' => 'Resumen por artículo'],
        ['valor' => self::MODO_DETALLE, 'etiqueta' => 'Detalle artículo × proveedor'],
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

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'sku' => trim((string) $request->input('sku', '')),
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'cuenta_codigo' => trim((string) $request->input('cuenta_codigo', '')),
            'modo' => $modo,
            'solo_multi_proveedor' => $request->boolean('solo_multi_proveedor'),
            'sin_cuenta_erp' => $request->boolean('sin_cuenta_erp'),
            'solo_diferencia_cuenta' => $request->boolean('solo_diferencia_cuenta'),
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
            'sku' => ($filtros['sku'] ?? '') !== '' ? ($filtros['sku'] ?? null) : null,
            'proveedores' => ($filtros['proveedores'] ?? '') !== '' ? ($filtros['proveedores'] ?? null) : null,
            'cuenta_codigo' => ($filtros['cuenta_codigo'] ?? '') !== '' ? ($filtros['cuenta_codigo'] ?? null) : null,
            'modo' => ($filtros['modo'] ?? self::MODO_RESUMEN) !== self::MODO_RESUMEN
                ? ($filtros['modo'] ?? null)
                : null,
            'solo_multi_proveedor' => ! empty($filtros['solo_multi_proveedor']) ? 1 : null,
            'sin_cuenta_erp' => ! empty($filtros['sin_cuenta_erp']) ? 1 : null,
            'solo_diferencia_cuenta' => ! empty($filtros['solo_diferencia_cuenta']) ? 1 : null,
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
            'fecha_desde' => $hoy->copy()->subYear()->format('Y-m-d'),
            'fecha_hasta' => $hoy->format('Y-m-d'),
            'sku' => '',
            'proveedores' => '',
            'cuenta_codigo' => '',
            'modo' => self::MODO_RESUMEN,
            'solo_multi_proveedor' => false,
            'sin_cuenta_erp' => false,
            'solo_diferencia_cuenta' => false,
        ];
    }

    public static function modoValido(string $modo): bool
    {
        return in_array($modo, [self::MODO_RESUMEN, self::MODO_DETALLE], true);
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

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return $desde.' — '.$hasta;
        }

        if ($desde !== '') {
            return 'Desde '.$desde;
        }

        return '—';
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
