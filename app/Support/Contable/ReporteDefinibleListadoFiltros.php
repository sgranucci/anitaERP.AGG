<?php

namespace App\Support\Contable;

use App\Support\Contable\ReporteDefinible\ReporteDefinibleDimensionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use Illuminate\Http\Request;

class ReporteDefinibleListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'filtro_valor' => '',
            'activo' => '',
            'tipo' => '',
            'origen' => '',
            'solo_publicado' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaLegacy = null): array
    {
        $filtros = self::filtrosVacios();
        $filtros['filtro_valor'] = trim((string) ($request->input('filtro_valor', $busquedaLegacy ?? '')));
        $filtros['activo'] = trim((string) $request->input('activo', ''));
        $filtros['tipo'] = trim((string) $request->input('tipo', ''));
        $filtros['origen'] = trim((string) $request->input('origen', ''));
        $filtros['solo_publicado'] = $request->boolean('solo_publicado') ? '1' : '';

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['filtro_valor'] ?? '') !== ''
            || ($filtros['activo'] ?? '') !== ''
            || ($filtros['tipo'] ?? '') !== ''
            || ($filtros['origen'] ?? '') !== ''
            || ($filtros['solo_publicado'] ?? '') === '1';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Contable\ReporteContable>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar($query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        if ($valor !== '') {
            $query->where(function ($q) use ($valor) {
                $q->where('nombre', 'like', '%'.$valor.'%')
                    ->orWhere('titulo1', 'like', '%'.$valor.'%')
                    ->orWhere('titulo2', 'like', '%'.$valor.'%')
                    ->orWhere('codigo', 'like', '%'.$valor.'%');
            });
        }

        if (($filtros['activo'] ?? '') === '1') {
            $query->where('activo', true);
        } elseif (($filtros['activo'] ?? '') === '0') {
            $query->where('activo', false);
        }

        if (($filtros['tipo'] ?? '') !== '' && isset(ReporteDefinibleSupport::tiposReporte()[$filtros['tipo']])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (($filtros['origen'] ?? '') !== '') {
            $query->where('origen', $filtros['origen']);
        }

        if (($filtros['solo_publicado'] ?? '') === '1') {
            $query->where('estado_publicacion', 'publicado');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['filtro_valor', 'activo', 'tipo', 'origen', 'solo_publicado'] as $k) {
            if (($filtros[$k] ?? '') !== '') {
                $out[$k] = $filtros[$k];
            }
        }

        return $out;
    }

    /**
     * Filtros de ejecución del informe.
     *
     * @return array<string, mixed>
     */
    public static function resolverEjecucionDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($empresaIds === [] && (int) $request->input('empresa_id', 0) > 0) {
            $empresaIds = [(int) $request->input('empresa_id')];
        }

        $modo = trim((string) $request->input('modo_periodo', SumasSaldosListadoFiltros::MODO_PERIODOS));
        if (! in_array($modo, [SumasSaldosListadoFiltros::MODO_PERIODOS, SumasSaldosListadoFiltros::MODO_RANGO], true)) {
            $modo = SumasSaldosListadoFiltros::MODO_PERIODOS;
        }

        $mesDesde = max(1, min(12, (int) $request->input('mes_desde', (int) date('n'))));
        $anioDesde = max(2000, min(2100, (int) $request->input('anio_desde', (int) date('Y'))));
        $mesHasta = max(1, min(12, (int) $request->input('mes_hasta', $mesDesde)));
        $anioHasta = max(2000, min(2100, (int) $request->input('anio_hasta', $anioDesde)));
        $periodoDesde = (int) sprintf('%04d%02d', $anioDesde, $mesDesde);
        $periodoHasta = (int) sprintf('%04d%02d', $anioHasta, $mesHasta);
        if ($periodoDesde > $periodoHasta) {
            [$periodoDesde, $periodoHasta] = [$periodoHasta, $periodoDesde];
            [$mesDesde, $mesHasta] = [$mesHasta, $mesDesde];
            [$anioDesde, $anioHasta] = [$anioHasta, $anioDesde];
        }

        $base = trim((string) $request->input('base_saldo', ReporteDefinibleSupport::BASE_SALDO_PERIODO));
        if (! in_array($base, [ReporteDefinibleSupport::BASE_SALDO_PERIODO, ReporteDefinibleSupport::BASE_SALDO_EJERCICIO], true)) {
            $base = ReporteDefinibleSupport::BASE_SALDO_PERIODO;
        }

        $modoAsientos = trim((string) $request->input('modo_inclusion_asientos', 'sin_cierre_ni_inflacion'));
        if (! in_array($modoAsientos, ['todos', 'sin_cierre', 'sin_inflacion', 'sin_cierre_ni_inflacion'], true)) {
            $modoAsientos = 'sin_cierre_ni_inflacion';
        }

        $layout = trim((string) $request->input('columnas_layout', ReporteDefinibleSupport::LAYOUT_PERIODOS));
        $layoutsOk = array_keys(ReporteDefinibleSupport::layoutsColumnas());
        if (! in_array($layout, $layoutsOk, true)) {
            $layout = ReporteDefinibleSupport::LAYOUT_PERIODOS;
        }

        $fuentePlan = trim((string) $request->input(
            'fuente_plan',
            ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO
        ));
        if (! isset(ReporteDefinibleDimensionSupport::fuentesPlan()[$fuentePlan])) {
            $fuentePlan = ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO;
        }

        return [
            'reporte_contable_id' => (int) $request->input('reporte_contable_id', $request->input('id', 0)),
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'modo_periodo' => $modo,
            'mes_desde' => $mesDesde,
            'anio_desde' => $anioDesde,
            'mes_hasta' => $mesHasta,
            'anio_hasta' => $anioHasta,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'base_saldo' => $base,
            'columnas_layout' => $layout,
            'fuente_plan' => $fuentePlan,
            'layout_id' => max(0, (int) $request->input('layout_id', 0)),
            'presupuesto_escenario_id' => max(0, (int) $request->input('presupuesto_escenario_id', 0)),
            'tipo_cambio_consolidacion' => in_array(
                trim((string) $request->input('tipo_cambio_consolidacion', 'asiento')),
                ['asiento', 'cierre'],
                true
            ) ? trim((string) $request->input('tipo_cambio_consolidacion', 'asiento')) : 'asiento',
            'mostrar_cuentas' => $request->boolean('mostrar_cuentas'),
            'nivel_max' => max(0, (int) $request->input('nivel_max', 0)),
            'incluir_presupuesto' => $request->boolean('incluir_presupuesto'),
            'modo_inclusion_asientos' => $modoAsientos,
            'ccosto_desde' => max(0, (int) $request->input('ccosto_desde', 0)),
            'ccosto_hasta' => max(0, (int) $request->input('ccosto_hasta', 0)),
            'incluir_sin_ccosto' => $request->boolean('incluir_sin_ccosto', true),
            'incluir_total_ccosto' => $request->boolean('incluir_total_ccosto', true),
            'ocultar_ceros' => $request->boolean('ocultar_ceros'),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function ejecucionParaQueryString(array $filtros): array
    {
        $out = [
            'consultar' => 1,
            'modo_periodo' => $filtros['modo_periodo'] ?? SumasSaldosListadoFiltros::MODO_PERIODOS,
            'mes_desde' => $filtros['mes_desde'] ?? null,
            'anio_desde' => $filtros['anio_desde'] ?? null,
            'mes_hasta' => $filtros['mes_hasta'] ?? null,
            'anio_hasta' => $filtros['anio_hasta'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'base_saldo' => $filtros['base_saldo'] ?? null,
            'columnas_layout' => $filtros['columnas_layout'] ?? null,
            'fuente_plan' => $filtros['fuente_plan'] ?? null,
            'layout_id' => ($filtros['layout_id'] ?? 0) ?: null,
            'presupuesto_escenario_id' => ($filtros['presupuesto_escenario_id'] ?? 0) ?: null,
            'tipo_cambio_consolidacion' => ($filtros['tipo_cambio_consolidacion'] ?? 'asiento') !== 'asiento'
                ? ($filtros['tipo_cambio_consolidacion'] ?? null)
                : null,
            'moneda_id' => $filtros['moneda_id'] ?? null,
            'modo_inclusion_asientos' => $filtros['modo_inclusion_asientos'] ?? null,
            'nivel_max' => $filtros['nivel_max'] ?? 0,
            'ccosto_desde' => ($filtros['ccosto_desde'] ?? 0) ?: null,
            'ccosto_hasta' => ($filtros['ccosto_hasta'] ?? 0) ?: null,
        ];
        if (! empty($filtros['mostrar_cuentas'])) {
            $out['mostrar_cuentas'] = 1;
        }
        if (! empty($filtros['incluir_presupuesto'])) {
            $out['incluir_presupuesto'] = 1;
        }
        if (! empty($filtros['ocultar_ceros'])) {
            $out['ocultar_ceros'] = 1;
        }
        if (array_key_exists('incluir_sin_ccosto', $filtros) && empty($filtros['incluir_sin_ccosto'])) {
            $out['incluir_sin_ccosto'] = 0;
        }
        if (array_key_exists('incluir_total_ccosto', $filtros) && empty($filtros['incluir_total_ccosto'])) {
            $out['incluir_total_ccosto'] = 0;
        }
        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }
        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }
        foreach ($filtros['empresa_ids'] ?? [] as $id) {
            $out['empresa_ids'][] = (int) $id;
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== '');
    }
}
