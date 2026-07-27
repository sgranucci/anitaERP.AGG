<?php

namespace App\Support\Ventas;

use Illuminate\Http\Request;

/**
 * Filtros del reporte «Auditoría de notas CRM» (SuiteCRM).
 */
final class SuitecrmNotaAuditoriaListadoFiltros
{
    public const TIPOS = [
        '' => 'Todos',
        'Accounts' => 'Cuenta',
        'Leads' => 'Cliente potencial',
        'Contacts' => 'Contacto',
    ];

    /**
     * @return array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }
     */
    public static function filtrosVacios(): array
    {
        return [
            'vendedor_crm_id' => null,
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'parent_type' => '',
            'texto' => '',
            'solo_vinculo_erp' => false,
        ];
    }

    /**
     * @return array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $vendedor = trim((string) $request->input('vendedor_crm_id', ''));
        $fechaDesde = self::normalizarFecha($request->input('fecha_desde'));
        $fechaHasta = self::normalizarFecha($request->input('fecha_hasta'));
        $parentType = trim((string) $request->input('parent_type', ''));
        if (! array_key_exists($parentType, self::TIPOS)) {
            $parentType = '';
        }
        $texto = trim((string) $request->input('texto', ''));

        return [
            'vendedor_crm_id' => $vendedor !== '' ? $vendedor : null,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'parent_type' => $parentType,
            'texto' => $texto,
            'solo_vinculo_erp' => $request->boolean('solo_vinculo_erp'),
        ];
    }

    /**
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['vendedor_crm_id'] ?? null) !== null
            || ($filtros['fecha_desde'] ?? null) !== null
            || ($filtros['fecha_hasta'] ?? null) !== null
            || ($filtros['parent_type'] ?? '') !== ''
            || trim((string) ($filtros['texto'] ?? '')) !== ''
            || ! empty($filtros['solo_vinculo_erp']);
    }

    /**
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp:bool
     * }  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];

        if (($filtros['vendedor_crm_id'] ?? null) !== null && $filtros['vendedor_crm_id'] !== '') {
            $out['vendedor_crm_id'] = (string) $filtros['vendedor_crm_id'];
        }
        if (($filtros['fecha_desde'] ?? null) !== null) {
            $out['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? null) !== null) {
            $out['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        if (($filtros['parent_type'] ?? '') !== '') {
            $out['parent_type'] = (string) $filtros['parent_type'];
        }
        if (trim((string) ($filtros['texto'] ?? '')) !== '') {
            $out['texto'] = trim((string) $filtros['texto']);
        }
        if (! empty($filtros['solo_vinculo_erp'])) {
            $out['solo_vinculo_erp'] = 1;
        }

        return $out;
    }

    private static function normalizarFecha(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            return $valor;
        }

        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $valor, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return null;
    }
}
