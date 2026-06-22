<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

final class TransferenciaPendienteListadoFiltros
{
    /**
     * @return array{
     *     empresa_id: ?int,
     *     deposito_origen_id: ?int,
     *     deposito_destino_id: ?int,
     *     bien_uso_destino_id: ?int,
     *     fecha_desde: ?string,
     *     fecha_hasta: ?string,
     *     solo_requiere_aprobacion: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        return [
            'empresa_id' => self::enteroOpcional($request->input('empresa_id')),
            'deposito_origen_id' => self::enteroOpcional($request->input('deposito_origen_id')),
            'deposito_destino_id' => self::enteroOpcional($request->input('deposito_destino_id')),
            'bien_uso_destino_id' => self::enteroOpcional($request->input('bien_uso_destino_id')),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'solo_requiere_aprobacion' => $request->input('solo_requiere_aprobacion', '1') !== '0',
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'deposito_origen_id' => $filtros['deposito_origen_id'] ?? null,
            'deposito_destino_id' => $filtros['deposito_destino_id'] ?? null,
            'bien_uso_destino_id' => $filtros['bien_uso_destino_id'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'solo_requiere_aprobacion' => ($filtros['solo_requiere_aprobacion'] ?? true) ? '1' : '0',
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['empresa_id'])
            || ! empty($filtros['deposito_origen_id'])
            || ! empty($filtros['deposito_destino_id'])
            || ! empty($filtros['bien_uso_destino_id'])
            || ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || ! ($filtros['solo_requiere_aprobacion'] ?? true);
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
