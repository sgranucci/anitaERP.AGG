<?php

namespace App\Support\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros de «Totales por centro de costo» (Anita l-movccosto).
 *
 * Filas: plan de cuentas. Columnas: grupos de centros de costo o centros de costo.
 */
class MovCcostoListadoFiltros
{
    public const AGRUPAR_GRUPO = 'grupo';

    public const AGRUPAR_CCOSTO = 'ccosto';

    public const FORMATO_SALDO = 'saldo';

    public const FORMATO_DEBE_HABER = 'debe_haber';

    public const MODOS_ASIENTOS = ['todos', 'sin_cierre', 'sin_inflacion', 'sin_cierre_ni_inflacion'];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $agrupar = trim((string) $request->input('agrupar', self::AGRUPAR_GRUPO));
        if (! in_array($agrupar, [self::AGRUPAR_GRUPO, self::AGRUPAR_CCOSTO], true)) {
            $agrupar = self::AGRUPAR_GRUPO;
        }

        $formato = trim((string) $request->input('formato', self::FORMATO_SALDO));
        if (! in_array($formato, [self::FORMATO_SALDO, self::FORMATO_DEBE_HABER], true)) {
            $formato = self::FORMATO_SALDO;
        }

        $modoAsientos = trim((string) $request->input('modo_inclusion_asientos', 'sin_cierre_ni_inflacion'));
        if (! in_array($modoAsientos, self::MODOS_ASIENTOS, true)) {
            $modoAsientos = 'sin_cierre_ni_inflacion';
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

        $cuentaDesde = MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $request->input('cuenta_desde', ''));
        $cuentaHasta = MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $request->input('cuenta_hasta', ''));
        if ($cuentaDesde > 0 && $cuentaHasta <= 0) {
            $cuentaHasta = $cuentaDesde;
        }

        $centrocostos = MayorPlanoCuentaCentrocostoFiltroSupport::parsearCodigos(
            $request->input('centrocostos_codigo', ''),
        );

        // Por defecto el mes en curso (como el resto de los reportes contables por rango).
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        if ($fechaDesde === '' && $fechaHasta === '' && ! $request->has('consultar')) {
            $fechaDesde = Carbon::now()->startOfMonth()->format('Y-m-d');
            $fechaHasta = Carbon::now()->format('Y-m-d');
        }

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'modo_inclusion_asientos' => $modoAsientos,
            'agrupar' => $agrupar,
            'formato' => $formato,
            'con_totales' => $request->boolean('con_totales', true),
            'cuenta_desde' => $cuentaDesde,
            'cuenta_hasta' => $cuentaHasta,
            'centrocostos_codigo' => implode(',', $centrocostos),
            'cc_desde' => trim((string) $request->input('cc_desde', '')),
            'cc_hasta' => trim((string) $request->input('cc_hasta', '')),
            'grupos_codigo' => implode(',', self::parsearGrupos($request->input('grupos_codigo', ''))),
            'incluir_sin_cc' => $request->boolean('incluir_sin_cc'),
        ];
    }

    /**
     * @param  string|array<int, string|int>|null  $valor
     * @return list<string>
     */
    public static function parsearGrupos(string|array|null $valor): array
    {
        $tokens = is_array($valor)
            ? $valor
            : (preg_split('/[,;\s]+/', trim((string) $valor)) ?: []);
        $codigos = [];

        foreach ($tokens as $token) {
            // Un token vacío no es el grupo «0» (sin grupo): se ignora.
            if (trim((string) $token) === '') {
                continue;
            }
            $codigo = self::normalizarGrupo((string) $token);
            if ($codigo !== '') {
                $codigos[$codigo] = $codigo;
            }
        }

        $lista = array_values($codigos);
        usort($lista, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $lista;
    }

    /**
     * Código de grupo canónico: sin ceros a la izquierda; vacío = «0» (centros sin grupo).
     */
    public static function normalizarGrupo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '0';
        }

        return ctype_digit($codigo) ? (string) ((int) $codigo) : $codigo;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['empresa_ids'] ?? []) === []) {
            return false;
        }

        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        return $desde !== '' && $hasta !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'fecha_desde' => trim((string) ($filtros['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($filtros['fecha_hasta'] ?? '')),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_inclusion_asientos' => (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
            'agrupar' => (string) ($filtros['agrupar'] ?? self::AGRUPAR_GRUPO),
            'formato' => (string) ($filtros['formato'] ?? self::FORMATO_SALDO),
        ];

        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $out['empresa_ids'][] = (int) $empresaId;
        }

        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }
        if (($filtros['con_totales'] ?? true) === false) {
            $out['con_totales'] = 0;
        }
        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }
        if (! empty($filtros['incluir_sin_cc'])) {
            $out['incluir_sin_cc'] = 1;
        }
        if ((int) ($filtros['cuenta_desde'] ?? 0) > 0) {
            $out['cuenta_desde'] = (int) $filtros['cuenta_desde'];
        }
        if ((int) ($filtros['cuenta_hasta'] ?? 0) > 0) {
            $out['cuenta_hasta'] = (int) $filtros['cuenta_hasta'];
        }
        foreach (['centrocostos_codigo', 'cc_desde', 'cc_hasta', 'grupos_codigo'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $out[$clave] = $valor;
            }
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

        if ($desde === '' && $hasta === '') {
            return ['', ''];
        }

        try {
            // Desde vacío = hasta ese día; hasta vacío = desde ese día.
            $d = Carbon::parse($desde !== '' ? $desde : $hasta)->format('Y-m-d');
            $h = Carbon::parse($hasta !== '' ? $hasta : $desde)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }

    public static function maxColumnas(array $filtros): int
    {
        return ($filtros['formato'] ?? self::FORMATO_SALDO) === self::FORMATO_DEBE_HABER
            ? max(1, (int) config('contable.movccosto.max_columnas_debe_haber', 14))
            : max(1, (int) config('contable.movccosto.max_columnas_saldo', 28));
    }
}
