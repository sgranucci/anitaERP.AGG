<?php

declare(strict_types=1);

namespace App\Support\Contable;

use Illuminate\Http\Request;

/**
 * Filtros del informe de revalúo contable (Anita l-biemae.c).
 */
final class BienUsoRevaluoListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        $hoy = now()->format('Y-m-d');

        return [
            'empresa_id' => null,
            'empresa_ids' => [],
            'codigo_bien_desde' => null,
            'codigo_bien_hasta' => null,
            'bien_uso_rubro_id' => null,
            'centrocosto_id' => null,
            'fecha_alta_desde' => null,
            'fecha_alta_hasta' => null,
            'fecha_revaluo' => $hoy,
            'indice_id' => null,
            'periodo_base' => now()->format('Ym'),
            'moneda_expresion_id' => null,
            'fl_mon_origen' => true,
            'filtrar_bajas' => true,
            'persistir' => true,
            'agrupar' => 'bien',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $base = self::filtrosVacios();

        $empresaIds = $request->input('empresa_ids', []);
        if (! is_array($empresaIds)) {
            $empresaIds = $empresaIds !== null && $empresaIds !== '' ? [(int) $empresaIds] : [];
        }
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds)));

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId > 0 && $empresaIds === []) {
            $empresaIds = [$empresaId];
        }

        return array_merge($base, [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'empresa_ids' => $empresaIds,
            'codigo_bien_desde' => self::intOrNull($request->input('codigo_bien_desde')),
            'codigo_bien_hasta' => self::intOrNull($request->input('codigo_bien_hasta')),
            'bien_uso_rubro_id' => self::intOrNull($request->input('bien_uso_rubro_id')),
            'centrocosto_id' => self::intOrNull($request->input('centrocosto_id')),
            'fecha_alta_desde' => self::fechaOrNull($request->input('fecha_alta_desde')),
            'fecha_alta_hasta' => self::fechaOrNull($request->input('fecha_alta_hasta')),
            'fecha_revaluo' => self::fechaOrNull($request->input('fecha_revaluo')) ?? $base['fecha_revaluo'],
            'indice_id' => self::intOrNull($request->input('indice_id')),
            'periodo_base' => preg_replace('/\D/', '', (string) $request->input('periodo_base', $base['periodo_base'])) ?: $base['periodo_base'],
            'moneda_expresion_id' => self::intOrNull($request->input('moneda_expresion_id')),
            'fl_mon_origen' => $request->boolean('fl_mon_origen', true),
            'filtrar_bajas' => $request->boolean('filtrar_bajas', true),
            'persistir' => $request->boolean('persistir', true),
            'agrupar' => in_array($request->input('agrupar'), ['bien', 'rubro', 'subrubro', 'ccosto'], true)
                ? (string) $request->input('agrupar')
                : 'bien',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['fecha_revaluo']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach ($filtros as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_bool($v)) {
                $out[$k] = $v ? 1 : 0;
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    private static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }

    private static function fechaOrNull(mixed $v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = explode('/', $s);

            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
        }

        return $s;
    }
}
