<?php

namespace App\Support\Solicitudpago;

use Illuminate\Http\Request;

/**
 * Filtros del informe de solicitudes de pago (Anita l-solpagomae.c).
 */
final class SolicitudpagoMaeListadoFiltros
{
    public const TRAT_SIN_PLAN = 'SIN_PLAN';

    public const TRAT_CON_PLAN = 'CON_PLAN';

    public const TRAT_SOLO_HIJAS = 'SOLO_HIJAS';

    public const TRAT_FAMILIA = 'FAMILIA';

    public const TRAT_TODAS = 'TODAS';

    public const ESTADO_TODOS = 'TODOS';

    /**
     * @return list<array{valor: string, nombre: string}>
     */
    public static function opcionesTratamiento(): array
    {
        return [
            ['valor' => self::TRAT_TODAS, 'nombre' => 'Todas las solicitudes de pago'],
            ['valor' => self::TRAT_SIN_PLAN, 'nombre' => 'Sin SP automáticas (excluye madres de plan)'],
            ['valor' => self::TRAT_CON_PLAN, 'nombre' => 'Solo madres con plan / cuotas'],
            ['valor' => self::TRAT_SOLO_HIJAS, 'nombre' => 'Solo SP hijas (cuotas generadas)'],
            ['valor' => self::TRAT_FAMILIA, 'nombre' => 'Familia completa (madre + hijas del período)'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        $empresaScope = 'una';
        $empresaId = null;
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            $empresaScope = 'todas';
        } elseif ($request->filled('empresa_id')) {
            $empresaId = (int) $request->input('empresa_id');
        } elseif ($empresaDefault !== null && $empresaDefault > 0) {
            $empresaId = $empresaDefault;
        }

        $estado = self::normalizarEstado((string) $request->input('estado', self::ESTADO_TODOS));

        $tratamiento = strtoupper(trim((string) $request->input('filtro_tratamiento', self::TRAT_TODAS)));
        $validos = array_column(self::opcionesTratamiento(), 'valor');
        if (! in_array($tratamiento, $validos, true)) {
            $tratamiento = self::TRAT_TODAS;
        }

        $fechaDesde = self::normalizarFecha($request->input('fecha_desde'));
        $fechaHasta = self::normalizarFecha($request->input('fecha_hasta'));
        if ($fechaDesde === null) {
            $fechaDesde = date('Y-m-01');
        }
        if ($fechaHasta === null) {
            $fechaHasta = date('Y-m-d');
        }
        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $sectorDesde = $request->filled('sector_desde') ? (int) $request->input('sector_desde') : null;
        $sectorHasta = $request->filled('sector_hasta') ? (int) $request->input('sector_hasta') : null;
        if ($sectorDesde !== null && $sectorHasta !== null && $sectorDesde > $sectorHasta) {
            [$sectorDesde, $sectorHasta] = [$sectorHasta, $sectorDesde];
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'estado' => $estado,
            'filtro_tratamiento' => $tratamiento,
            'sector_desde' => $sectorDesde,
            'sector_hasta' => $sectorHasta,
            'incluir_conciliacion_mayor' => $request->boolean('incluir_conciliacion_mayor'),
            'consultar' => $request->boolean('consultar') || $request->input('consultar') === '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = ['consultar' => 1];

        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $estado = $filtros['estado'] ?? self::ESTADO_TODOS;
        if ($estado !== self::ESTADO_TODOS) {
            $params['estado'] = $estado;
        }

        $tratamiento = $filtros['filtro_tratamiento'] ?? self::TRAT_TODAS;
        if ($tratamiento !== self::TRAT_TODAS) {
            $params['filtro_tratamiento'] = $tratamiento;
        }

        if ($filtros['sector_desde'] !== null && $filtros['sector_desde'] !== '') {
            $params['sector_desde'] = (int) $filtros['sector_desde'];
        }
        if ($filtros['sector_hasta'] !== null && $filtros['sector_hasta'] !== '') {
            $params['sector_hasta'] = (int) $filtros['sector_hasta'];
        }

        if (! empty($filtros['incluir_conciliacion_mayor'])) {
            $params['incluir_conciliacion_mayor'] = 1;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function muestraColumnasCuota(array $filtros): bool
    {
        $tratamiento = $filtros['filtro_tratamiento'] ?? self::TRAT_TODAS;

        return in_array($tratamiento, [
            self::TRAT_CON_PLAN,
            self::TRAT_FAMILIA,
            self::TRAT_TODAS,
            self::TRAT_SOLO_HIJAS,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function expandirFamilia(array $filtros): bool
    {
        return ($filtros['filtro_tratamiento'] ?? '') === self::TRAT_FAMILIA;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        $partes[] = 'Período '.self::fechaUi($filtros['fecha_desde'] ?? null)
            .' a '.self::fechaUi($filtros['fecha_hasta'] ?? null);

        $estado = $filtros['estado'] ?? self::ESTADO_TODOS;
        $partes[] = $estado === self::ESTADO_TODOS
            ? 'Sin filtrar por estado'
            : 'Estado '.SolicitudpagoEstados::label($estado);

        $trat = $filtros['filtro_tratamiento'] ?? self::TRAT_TODAS;
        foreach (self::opcionesTratamiento() as $op) {
            if ($op['valor'] === $trat) {
                $partes[] = $op['nombre'];
                break;
            }
        }

        if (($filtros['sector_desde'] ?? null) !== null || ($filtros['sector_hasta'] ?? null) !== null) {
            $partes[] = 'Sector '
                .((string) ($filtros['sector_desde'] ?? '…'))
                .'–'
                .((string) ($filtros['sector_hasta'] ?? '…'));
        }

        if (! empty($filtros['incluir_conciliacion_mayor'])) {
            $partes[] = 'Con conciliación mayor (SP pagadas por ERP)';
        }

        return implode(' · ', $partes);
    }

    /** Acepta código ERP (AUTORIZADA) o etiqueta (Autorizada). */
    private static function normalizarEstado(string $valor): string
    {
        $trim = trim($valor);
        if ($trim === '') {
            return self::ESTADO_TODOS;
        }

        $upper = mb_strtoupper($trim, 'UTF-8');
        if ($upper === self::ESTADO_TODOS) {
            return self::ESTADO_TODOS;
        }

        $validos = array_column(SolicitudpagoEstados::opciones(), 'valor');
        if (in_array($upper, $validos, true)) {
            return $upper;
        }

        foreach (SolicitudpagoEstados::opciones() as $op) {
            if (mb_strtoupper((string) $op['nombre'], 'UTF-8') === $upper) {
                return (string) $op['valor'];
            }
        }

        return self::ESTADO_TODOS;
    }

    private static function normalizarFecha(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^\d{8}$/', $valor)) {
            return substr($valor, 0, 4).'-'.substr($valor, 4, 2).'-'.substr($valor, 6, 2);
        }

        try {
            return \Carbon\Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fechaUi(?string $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }
}
