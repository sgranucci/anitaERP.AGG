<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

final class BienUsoMovimientoListadoFiltros
{
    public const EFECTOS = [
      '' => 'Todos',
      BienUsoAsignacionSupport::EFECTO_ASIGNACION => 'Solo asignaciones',
      BienUsoAsignacionSupport::EFECTO_DESASIGNACION => 'Solo desasignaciones',
  ];

    /**
     * @return array{bien_uso_id: ?int, fecha_desde: ?string, fecha_hasta: ?string, articulo_id: ?int, efecto: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $efecto = (string) $request->input('efecto', '');
        if (! array_key_exists($efecto, self::EFECTOS)) {
            $efecto = '';
        }

        return [
            'bien_uso_id' => self::enteroOpcional($request->input('bien_uso_id')),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'articulo_id' => self::enteroOpcional($request->input('articulo_id')),
            'efecto' => $efecto,
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'bien_uso_id' => $filtros['bien_uso_id'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'articulo_id' => $filtros['articulo_id'] ?? null,
            'efecto' => ($filtros['efecto'] ?? '') !== '' ? $filtros['efecto'] : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['bien_uso_id'])
            || ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || ! empty($filtros['articulo_id'])
            || ($filtros['efecto'] ?? '') !== '';
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
