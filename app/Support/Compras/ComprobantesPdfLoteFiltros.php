<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class ComprobantesPdfLoteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $ids = self::idsDesdeTexto((string) $request->input('proveedor_ids', ''));

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'fecha_desde' => self::fecha($request->input('fecha_desde'), date('Y-m-01')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta'), date('Y-m-d')),
            'proveedor_ids' => $ids,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['proveedor_ids'] ?? []) !== []
            && self::fechaValida((string) ($filtros['fecha_desde'] ?? ''))
            && self::fechaValida((string) ($filtros['fecha_hasta'] ?? ''))
            && (string) $filtros['fecha_desde'] <= (string) $filtros['fecha_hasta'];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'proveedor_ids' => implode(',', $filtros['proveedor_ids'] ?? []),
        ];
    }

    /**
     * @return list<int>
     */
    public static function idsDesdeTexto(string $texto): array
    {
        $ids = [];
        foreach (preg_split('/[,\s]+/', trim($texto)) ?: [] as $parte) {
            $id = (int) $parte;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function fecha(mixed $valor, string $defecto): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return $defecto;
        }

        return self::fechaValida($texto) ? $texto : $defecto;
    }

    private static function fechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $dt instanceof \DateTime && $dt->format('Y-m-d') === $fecha;
    }
}
