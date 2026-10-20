<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class ComprobantesPdfLoteFiltros
{
    /** @var array<string, string> */
    public const TIPOS = [
        'fac' => 'Facturas',
        'oc' => 'OC',
        'com' => 'COM',
        'opp' => 'OPP',
        'opa' => 'OPA',
    ];

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
            'tipos' => self::tiposDesdeRequest($request),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['proveedor_ids'] ?? []) !== []
            && ($filtros['tipos'] ?? []) !== []
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
            'tipos' => array_values($filtros['tipos'] ?? []),
            'tipos_enviados' => 1,
        ];
    }

    /**
     * Sin tildes en el request se listan solo facturas, como hasta ahora.
     * Si el formulario viajó (tipos_enviados) y no hay ninguna, la lista queda vacía.
     *
     * @return list<string>
     */
    public static function tiposDesdeRequest(Request $request): array
    {
        if (! $request->has('tipos') && ! $request->boolean('tipos_enviados')) {
            return ['fac'];
        }

        $raw = $request->input('tipos', []);
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', $raw) ?: [];
        }

        $ok = [];
        foreach ((array) $raw as $tipo) {
            $tipo = strtolower(trim((string) $tipo));
            if (isset(self::TIPOS[$tipo])) {
                $ok[$tipo] = $tipo;
            }
        }

        return array_values($ok);
    }

    /**
     * @return list<string> claves tipo:id
     */
    public static function clavesDesdeRequest(Request $request): array
    {
        $raw = $request->input('claves', []);
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', trim($raw)) ?: [];
        }

        $ok = [];
        foreach ((array) $raw as $clave) {
            $clave = strtolower(trim((string) $clave));
            if (preg_match('/^(fac|oc|com|opp|opa):(\d+)$/', $clave, $m) === 1 && (int) $m[2] > 0) {
                $ok[$clave] = $clave;
            }
        }

        return array_values($ok);
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
