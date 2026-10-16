<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class ProveedorControlGananciasReporteFiltros
{
    public const SEGMENTO_TODOS = 'todos';

    public const SEGMENTO_RI = 'ri';

    public const SEGMENTO_MONO = 'mono';

    public const SEGMENTOS = [
        self::SEGMENTO_TODOS => 'RI y Monotributistas',
        self::SEGMENTO_RI => 'Solo Responsables Inscriptos',
        self::SEGMENTO_MONO => 'Solo Monotributistas',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'empresa_id' => 0,
            'estado' => 'Activo',
            'segmento' => self::SEGMENTO_TODOS,
            'proveedor_id' => 0,
            'proveedor_codigo' => '',
            'proveedor_nombre' => '',
            'solo_discrepancias' => true,
            'consultar_arca' => true,
            'limite' => (int) config('compras_control_ganancias.limite_default', 100),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $defaults = self::defaults();
        $segmento = trim((string) $request->input('segmento', $defaults['segmento']));
        if (! array_key_exists($segmento, self::SEGMENTOS)) {
            $segmento = self::SEGMENTO_TODOS;
        }

        $consultando = $request->boolean('consultar');

        $limiteMax = max(1, (int) config('compras_control_ganancias.limite_max', 20000));
        // 0 o vacío al consultar = todos. Sin consultar, el default es un lote chico.
        $limiteRaw = $request->input('limite', null);
        if ($limiteRaw === null || $limiteRaw === '') {
            $limite = $consultando ? 0 : (int) $defaults['limite'];
        } else {
            $limite = (int) $limiteRaw;
        }
        if ($limite > 0) {
            $limite = min($limiteMax, $limite);
        } else {
            $limite = 0;
        }

        $estado = trim((string) $request->input('estado', $defaults['estado']));
        if ($estado === 'todos') {
            $estado = '';
        }

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'estado' => $estado,
            'segmento' => $segmento,
            'proveedor_id' => max(0, (int) $request->input('proveedor_id', 0)),
            'proveedor_codigo' => '',
            'proveedor_nombre' => '',
            // Checkbox HTML: ausente = false al consultar.
            'solo_discrepancias' => $consultando
                ? $request->boolean('solo_discrepancias')
                : (bool) $defaults['solo_discrepancias'],
            'consultar_arca' => $consultando
                ? $request->boolean('consultar_arca')
                : (bool) $defaults['consultar_arca'],
            'limite' => $limite,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            || (int) ($filtros['proveedor_id'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'estado' => (string) ($filtros['estado'] ?? ''),
            'segmento' => (string) ($filtros['segmento'] ?? self::SEGMENTO_TODOS),
            'limite' => (int) ($filtros['limite'] ?? 100),
        ];

        if ((int) ($filtros['proveedor_id'] ?? 0) > 0) {
            $out['proveedor_id'] = (int) $filtros['proveedor_id'];
        }
        if (! empty($filtros['solo_discrepancias'])) {
            $out['solo_discrepancias'] = 1;
        }
        if (! empty($filtros['consultar_arca'])) {
            $out['consultar_arca'] = 1;
        }

        return $out;
    }

    public static function formatearSubtitulo(array $filtros): string
    {
        $partes = [];
        $segmento = (string) ($filtros['segmento'] ?? self::SEGMENTO_TODOS);
        $partes[] = self::SEGMENTOS[$segmento] ?? $segmento;

        $estado = trim((string) ($filtros['estado'] ?? ''));
        $partes[] = $estado !== '' ? 'Estado: '.$estado : 'Estado: todos';

        if (! empty($filtros['solo_discrepancias'])) {
            $partes[] = 'Solo discrepancias';
        }
        if (! empty($filtros['consultar_arca'])) {
            $partes[] = 'Con consulta ARCA';
        } else {
            $partes[] = 'Sin consulta ARCA (solo ABM)';
        }

        $limite = (int) ($filtros['limite'] ?? 0);
        $partes[] = $limite > 0 ? 'Límite: '.$limite : 'Límite: todos';

        return implode(' · ', $partes);
    }
}
