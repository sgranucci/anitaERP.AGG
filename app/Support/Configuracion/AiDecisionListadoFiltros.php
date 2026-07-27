<?php

namespace App\Support\Configuracion;

use App\Models\Ai\AiDecision;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Filtros del reporte de gobernanza IA (ai_decision).
 */
final class AiDecisionListadoFiltros
{
    /**
     * @return array{
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   skill: string,
     *   accion: string,
     *   consultar: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = trim((string) $request->input('fecha_desde', ''));
        $hasta = trim((string) $request->input('fecha_hasta', ''));

        if ($desde === '' && $hasta === '' && $request->boolean('consultar')) {
            $hasta = Carbon::today()->toDateString();
            $desde = Carbon::today()->subDays(30)->toDateString();
        }

        $skill = trim((string) $request->input('skill', ''));
        $accion = trim((string) $request->input('accion', ''));
        $accionesValidas = array_keys(self::accionesEtiquetas());
        if ($accion !== '' && ! in_array($accion, $accionesValidas, true)) {
            $accion = '';
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'skill' => $skill,
            'accion' => $accion,
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['fecha_desde', 'fecha_hasta', 'skill', 'accion'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $out[$clave] = $valor;
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== ''
            || trim((string) ($filtros['skill'] ?? '')) !== ''
            || trim((string) ($filtros['accion'] ?? '')) !== '';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ai\AiDecision>  $query
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Ai\AiDecision>
     */
    public static function aplicar($query, array $filtros)
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '') {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $skill = trim((string) ($filtros['skill'] ?? ''));
        if ($skill !== '') {
            $query->where('skill', $skill);
        }

        $accion = trim((string) ($filtros['accion'] ?? ''));
        if ($accion !== '') {
            $query->where('accion', $accion);
        }

        return $query;
    }

    /** @return array<string, string> */
    public static function accionesEtiquetas(): array
    {
        return [
            AiDecision::ACCION_SUGERIDA => 'Sugerida (pendiente)',
            AiDecision::ACCION_CONFIRMADA => 'Confirmada',
            AiDecision::ACCION_EDITADA => 'Editada y confirmada',
            AiDecision::ACCION_DESCARTADA => 'Descartada',
            AiDecision::ACCION_AUTO_APLICADA => 'Auto-aplicada',
            AiDecision::ACCION_ERROR => 'Error',
        ];
    }

    /** @return array<string, string> */
    public static function skillsEtiquetas(): array
    {
        return [
            'extraer_factura_proveedor' => 'Extraer factura proveedor',
            'extraer_comprobante_iva_caja' => 'Extraer comprobante IVA de Caja',
            'emparejar_remito_recepcion' => 'Emparejar remito / recepción',
            'sugerir_pares_conciliacion_bancaria' => 'Sugerir pares conciliación bancaria',
            'explicar_contexto_arbol_aprobacion' => 'Explicar contexto árbol de aprobación',
            'explicar_contexto_arbol_aprobacion_oc' => 'Explicar contexto árbol aprobación OC (legacy)',
            'explicar_diferencias_conciliacion_turno_gastronomia' => 'Explicar diferencias conciliación turno gastronomía',
            'consultar_contexto_operativo' => 'Consulta operativa (diálogo)',
        ];
    }

    public static function etiquetaAccion(?string $accion): string
    {
        $mapa = self::accionesEtiquetas();

        return $mapa[$accion] ?? (string) $accion;
    }

    public static function etiquetaSkill(?string $skill): string
    {
        $mapa = self::skillsEtiquetas();

        return $mapa[$skill] ?? (string) $skill;
    }
}
