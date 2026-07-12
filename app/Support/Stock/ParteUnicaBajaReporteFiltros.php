<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

final class ParteUnicaBajaReporteFiltros
{
    /** @var array<string, string> */
    public const ESTADOS = [
        'B' => 'Solo dados de baja',
        'T' => 'Todos (activos y baja)',
        'A' => 'Solo activos',
    ];

    /**
     * @return array{
     *     numeroparte: ?int,
     *     articulo_id: ?int,
     *     sku: string,
     *     fecha_desde: ?string,
     *     fecha_hasta: ?string,
     *     estado: string
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $estado = (string) $request->input('estado', 'B');
        if (! array_key_exists($estado, self::ESTADOS)) {
            $estado = 'B';
        }

        $npu = filter_var($request->input('numeroparte'), FILTER_VALIDATE_INT);

        return [
            'numeroparte' => $npu !== false && (int) $npu > 0 ? (int) $npu : null,
            'articulo_id' => self::enteroOpcional($request->input('articulo_id')),
            'sku' => trim((string) $request->input('sku', '')),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'estado' => $estado,
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'numeroparte' => $filtros['numeroparte'] ?? null,
            'articulo_id' => $filtros['articulo_id'] ?? null,
            'sku' => ($filtros['sku'] ?? '') !== '' ? $filtros['sku'] : null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'estado' => ($filtros['estado'] ?? 'B') !== 'B' ? $filtros['estado'] : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['numeroparte'])
            || ! empty($filtros['articulo_id'])
            || ($filtros['sku'] ?? '') !== ''
            || ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || ($filtros['estado'] ?? 'B') !== 'B';
    }

    private static function enteroOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
