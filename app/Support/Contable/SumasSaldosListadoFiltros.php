<?php

namespace App\Support\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del Balance de Sumas y Saldos (SyS / l-sumsal).
 *
 * - modo períodos: lee cuentacontable_saldo_mes (rápido).
 * - modo rango: agrega asiento_movimiento (preciso a día).
 */
class SumasSaldosListadoFiltros
{
    public const MODO_PERIODOS = 'periodos';

    public const MODO_RANGO = 'rango';

    public const CUENTAS_CON_MOVIMIENTO = 'con_movimiento';

    public const CUENTAS_TODAS = 'todas';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = trim((string) $request->input('modo_periodo', self::MODO_PERIODOS));
        if (! in_array($modo, [self::MODO_PERIODOS, self::MODO_RANGO], true)) {
            $modo = self::MODO_PERIODOS;
        }

        $modoAsientos = trim((string) $request->input('modo_inclusion_asientos', 'sin_cierre_ni_inflacion'));
        if (! in_array($modoAsientos, ['todos', 'sin_cierre', 'sin_inflacion', 'sin_cierre_ni_inflacion'], true)) {
            $modoAsientos = 'sin_cierre_ni_inflacion';
        }

        $filtroCuentas = trim((string) $request->input('filtro_cuentas', self::CUENTAS_CON_MOVIMIENTO));
        if (! in_array($filtroCuentas, [self::CUENTAS_CON_MOVIMIENTO, self::CUENTAS_TODAS], true)) {
            $filtroCuentas = self::CUENTAS_CON_MOVIMIENTO;
        }

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($empresaIds === [] && (int) $request->input('empresa_id', 0) > 0) {
            $empresaIds = [(int) $request->input('empresa_id')];
        }

        $cuentaDesde = MayorPlanoCuentaSupport::parsearCodigoCuenta(
            (string) $request->input('cuenta_desde', ''),
        );
        $cuentaHasta = MayorPlanoCuentaSupport::parsearCodigoCuenta(
            (string) $request->input('cuenta_hasta', ''),
        );
        if ($cuentaDesde > 0 && $cuentaHasta <= 0) {
            $cuentaHasta = $cuentaDesde;
        }

        $mesDesde = max(1, min(12, (int) $request->input('mes_desde', (int) date('n'))));
        $anioDesde = max(2000, min(2100, (int) $request->input('anio_desde', (int) date('Y'))));
        $mesHasta = max(1, min(12, (int) $request->input('mes_hasta', $mesDesde)));
        $anioHasta = max(2000, min(2100, (int) $request->input('anio_hasta', $anioDesde)));

        // Compat: mes/anio únicos (como mayor plano) → mismo desde/hasta.
        if ($request->filled('mes') && ! $request->filled('mes_desde')) {
            $mesDesde = $mesHasta = max(1, min(12, (int) $request->input('mes')));
        }
        if ($request->filled('anio') && ! $request->filled('anio_desde')) {
            $anioDesde = $anioHasta = max(2000, min(2100, (int) $request->input('anio')));
        }

        $periodoDesde = (int) sprintf('%04d%02d', $anioDesde, $mesDesde);
        $periodoHasta = (int) sprintf('%04d%02d', $anioHasta, $mesHasta);
        if ($periodoDesde > $periodoHasta) {
            [$periodoDesde, $periodoHasta] = [$periodoHasta, $periodoDesde];
            [$mesDesde, $mesHasta] = [$mesHasta, $mesDesde];
            [$anioDesde, $anioHasta] = [$anioHasta, $anioDesde];
        }

        return [
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
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'modo_inclusion_asientos' => $modoAsientos,
            'filtro_cuentas' => $filtroCuentas,
            'cuenta_desde' => $cuentaDesde,
            'cuenta_hasta' => $cuentaHasta,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['empresa_ids'] ?? []) === []) {
            return false;
        }

        if (($filtros['modo_periodo'] ?? self::MODO_PERIODOS) === self::MODO_PERIODOS) {
            return (int) ($filtros['periodo_desde'] ?? 0) > 0
                && (int) ($filtros['periodo_hasta'] ?? 0) > 0;
        }

        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => (string) ($filtros['modo_periodo'] ?? self::MODO_PERIODOS),
            'modo_inclusion_asientos' => (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
            'filtro_cuentas' => (string) ($filtros['filtro_cuentas'] ?? self::CUENTAS_CON_MOVIMIENTO),
        ];

        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $out['empresa_ids'][] = (int) $empresaId;
        }

        if (($filtros['modo_periodo'] ?? '') === self::MODO_RANGO) {
            $out['fecha_desde'] = trim((string) ($filtros['fecha_desde'] ?? ''));
            $out['fecha_hasta'] = trim((string) ($filtros['fecha_hasta'] ?? ''));
        } else {
            $out['mes_desde'] = (int) ($filtros['mes_desde'] ?? 0);
            $out['anio_desde'] = (int) ($filtros['anio_desde'] ?? 0);
            $out['mes_hasta'] = (int) ($filtros['mes_hasta'] ?? 0);
            $out['anio_hasta'] = (int) ($filtros['anio_hasta'] ?? 0);
        }

        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        if ((int) ($filtros['cuenta_desde'] ?? 0) > 0) {
            $out['cuenta_desde'] = (int) $filtros['cuenta_desde'];
        }

        if ((int) ($filtros['cuenta_hasta'] ?? 0) > 0) {
            $out['cuenta_hasta'] = (int) $filtros['cuenta_hasta'];
        }

        return $out;
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' || $hasta === '') {
            return ['', ''];
        }

        try {
            $d = Carbon::parse($desde)->format('Y-m-d');
            $h = Carbon::parse($hasta)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }

    /**
     * Primer día del período YYYYMM y último día del período hasta.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function fechasDesdePeriodos(int $periodoDesde, int $periodoHasta): ?array
    {
        if ($periodoDesde <= 0 || $periodoHasta <= 0) {
            return null;
        }

        $anioD = intdiv($periodoDesde, 100);
        $mesD = $periodoDesde % 100;
        $anioH = intdiv($periodoHasta, 100);
        $mesH = $periodoHasta % 100;

        if ($mesD < 1 || $mesD > 12 || $mesH < 1 || $mesH > 12) {
            return null;
        }

        $desde = sprintf('%04d-%02d-01', $anioD, $mesD);
        $hasta = Carbon::create($anioH, $mesH, 1)->endOfMonth()->format('Y-m-d');

        return [$desde, $hasta];
    }

    /**
     * Inicio de ejercicio contable (YYYYMM) para acumular saldo mes ant. / ejercicio.
     * Equivalente simplificado a EMPM_extrae_ejercicio (01/01 del año del desde).
     *
     * No aplica el piso Anita {@see MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD}:
     * SyS / mayor ERP leen cuentacontable_saldo_mes local (p. ej. 2025 importado).
     */
    public static function periodoInicioEjercicio(int $periodoDesde): int
    {
        $anio = intdiv(max(0, $periodoDesde), 100);
        if ($anio < 2000) {
            $anio = (int) date('Y');
        }

        return (int) ($anio.'01');
    }

    public static function periodoDesdeFecha(string $fechaYmd): int
    {
        try {
            return (int) Carbon::parse($fechaYmd)->format('Ym');
        } catch (\Throwable) {
            return 0;
        }
    }
}
