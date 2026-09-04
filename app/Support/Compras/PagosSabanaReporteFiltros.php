<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de pagos tipo sábana (equivalente Anita l-movim.c formato completo).
 */
final class PagosSabanaReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $defaults = self::defaults();

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')) ?? $defaults['fecha_desde'],
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')) ?? $defaults['fecha_hasta'],
            'incluir_anita' => $request->boolean(
                'incluir_anita',
                (bool) config('compras.pagos_sabana_anita_habilitada', false)
            ),
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $hoy = Carbon::now()->format('Y-m-d');

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy,
            'fecha_hasta' => $hoy,
            'incluir_anita' => (bool) config('compras.pagos_sabana_anita_habilitada', false),
            'consultar' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = [
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'consultar' => 1,
        ];

        $query = array_filter($query, fn ($valor) => $valor !== null && $valor !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['incluir_anita'])) {
            $query['incluir_anita'] = 1;
        } else {
            $query['incluir_anita'] = 0;
        }

        return $query;
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde'])
            && ! empty($filtros['fecha_hasta']);
    }

    /** @param array<string, mixed> $filtros */
    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros), JSON_UNESCAPED_UNICODE) ?: '');
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
