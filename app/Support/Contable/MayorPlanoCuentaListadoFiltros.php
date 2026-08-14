<?php

namespace App\Support\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte mayor plano por cuenta contable.
 */
class MayorPlanoCuentaListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = trim((string) $request->input('modo_periodo', 'mes'));
        if (! in_array($modo, ['mes', 'rango'], true)) {
            $modo = 'mes';
        }

        $modoAsientos = trim((string) $request->input('modo_inclusion_asientos', 'sin_cierre_ni_inflacion'));
        if (! in_array($modoAsientos, ['todos', 'sin_cierre', 'sin_inflacion', 'sin_cierre_ni_inflacion'], true)) {
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

        $cuentaDesde = MayorPlanoCuenta\MayorPlanoCuentaSupport::parsearCodigoCuenta(
            (string) $request->input('cuenta_desde', ''),
        );
        $cuentaHasta = MayorPlanoCuenta\MayorPlanoCuentaSupport::parsearCodigoCuenta(
            (string) $request->input('cuenta_hasta', ''),
        );
        if ($cuentaDesde > 0 && $cuentaHasta <= 0) {
            $cuentaHasta = $cuentaDesde;
        }

        $cuentas = self::parsearCuentasCsv((string) $request->input('cuentas', ''));
        $centrocostos = MayorPlanoCuentaCentrocostoFiltroSupport::parsearCodigos(
            $request->input('centrocostos_codigo', ''),
        );

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'modo_periodo' => $modo,
            'mes' => max(1, min(12, (int) $request->input('mes', (int) date('n')))),
            'anio' => max(2000, min(2100, (int) $request->input('anio', (int) date('Y')))),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'incluye_subdiario' => $request->boolean('incluye_subdiario', true),
            'modo_inclusion_asientos' => $modoAsientos,
            'cuenta_desde' => $cuentaDesde,
            'cuenta_hasta' => $cuentaHasta,
            'cuentas' => $cuentas,
            'centrocostos_codigo' => implode(',', $centrocostos),
            'cc_desde' => trim((string) $request->input('cc_desde', '')),
            'cc_hasta' => trim((string) $request->input('cc_hasta', '')),
            'agrupar_por_cc' => $request->boolean('agrupar_por_cc'),
            // Sin decisión explícita del usuario el valor queda en null: el support lo resuelve
            // como "excluir sin CC" cuando hay filtro de centros de costo.
            'incluir_sin_cc' => $request->boolean('incluir_sin_cc_manual')
                ? $request->boolean('incluir_sin_cc')
                : null,
            'incluir_sin_cc_manual' => $request->boolean('incluir_sin_cc_manual'),
            'filtro_texto' => trim((string) $request->input('filtro_texto', '')),
        ];
    }

    /**
     * CSV de códigos de cuenta (con o sin guión) → lista de enteros únicos ordenados.
     *
     * @return list<int>
     */
    public static function parsearCuentasCsv(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [];
        }

        $codigos = [];
        foreach (preg_split('/[,\s;]+/', $valor) ?: [] as $token) {
            $codigo = MayorPlanoCuenta\MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $token);
            if ($codigo > 0) {
                $codigos[$codigo] = $codigo;
            }
        }

        $lista = array_values($codigos);
        sort($lista);

        return $lista;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['empresa_ids'] ?? []) === []) {
            return false;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            return (int) ($filtros['mes'] ?? 0) > 0 && (int) ($filtros['anio'] ?? 0) > 0;
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
            'modo_periodo' => (string) ($filtros['modo_periodo'] ?? 'mes'),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
            'modo_inclusion_asientos' => (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
        ];

        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $out['empresa_ids'][] = (int) $empresaId;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'rango') {
            $out['fecha_desde'] = trim((string) ($filtros['fecha_desde'] ?? ''));
            $out['fecha_hasta'] = trim((string) ($filtros['fecha_hasta'] ?? ''));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        if (($filtros['incluye_subdiario'] ?? true) === false) {
            $out['incluye_subdiario'] = 0;
        }

        if ((int) ($filtros['cuenta_desde'] ?? 0) > 0) {
            $out['cuenta_desde'] = (int) $filtros['cuenta_desde'];
        }

        if ((int) ($filtros['cuenta_hasta'] ?? 0) > 0) {
            $out['cuenta_hasta'] = (int) $filtros['cuenta_hasta'];
        }

        $cuentas = array_values(array_filter(array_map('intval', $filtros['cuentas'] ?? []), fn (int $c) => $c > 0));
        if ($cuentas !== []) {
            sort($cuentas);
            $out['cuentas'] = implode(',', $cuentas);
        }

        $centrocostos = MayorPlanoCuentaCentrocostoFiltroSupport::parsearCodigos(
            $filtros['centrocostos_codigo'] ?? '',
        );
        if ($centrocostos !== []) {
            $out['centrocostos_codigo'] = implode(',', $centrocostos);
        }

        foreach (['cc_desde', 'cc_hasta'] as $campoCc) {
            $valorCc = trim((string) ($filtros[$campoCc] ?? ''));
            if ($valorCc !== '') {
                $out[$campoCc] = $valorCc;
            }
        }

        if (! empty($filtros['agrupar_por_cc'])) {
            $out['agrupar_por_cc'] = 1;
        }

        if (array_key_exists('incluir_sin_cc', $filtros) && $filtros['incluir_sin_cc'] !== null) {
            $out['incluir_sin_cc'] = $filtros['incluir_sin_cc'] ? 1 : 0;
            $out['incluir_sin_cc_manual'] = 1;
        }

        $texto = trim((string) ($filtros['filtro_texto'] ?? ''));
        if ($texto !== '') {
            $out['filtro_texto'] = $texto;
        }

        return $out;
    }

    public static function firma(array $filtros): string
    {
        $base = self::paraQueryString($filtros);
        unset($base['filtro_texto']);

        return md5(json_encode($base));
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
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function aplicarFiltroTexto(array $filas, string $texto): array
    {
        $texto = mb_strtolower(trim($texto));
        if ($texto === '') {
            return $filas;
        }

        return array_values(array_filter($filas, function (array $fila) use ($texto) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                return true;
            }

            $campos = [
                $fila['cuenta_codigo'] ?? '',
                $fila['cuenta_nombre'] ?? '',
                $fila['fecha_fmt'] ?? '',
                $fila['nro_asiento_fmt'] ?? (string) ($fila['nro_asiento'] ?? ''),
                $fila['tipo_comp'] ?? '',
                $fila['comprobante'] ?? '',
                $fila['emisor'] ?? '',
                $fila['cuit'] ?? '',
                $fila['descripcion'] ?? '',
                (string) ($fila['nro_oc'] ?? ''),
                $fila['moneda_abrev'] ?? '',
                $fila['nombreempresa'] ?? '',
                $fila['centrocosto_codigo'] ?? '',
                $fila['centrocosto_nombre'] ?? '',
            ];

            $blob = mb_strtolower(implode(' ', $campos));

            return str_contains($blob, $texto);
        }));
    }
}
