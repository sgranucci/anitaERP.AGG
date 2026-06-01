<?php

namespace App\Support\Ventas;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Últimos números de ticket y nota de crédito por punto de venta en el alcance de una jornada cerrada.
 */
final class GastronomiaJornadaNumeracionComprobanteSupport
{
    /**
     * @return array{
     *   filas: list<array{
     *     configuracion_puntoventa_gastronomia_id: int,
     *     terminal_pc: string,
     *     terminal_descripcion: string,
     *     rol: string,
     *     rol_etiqueta: string,
     *     puntoventa_id: int,
     *     puntoventa_codigo: string,
     *     puntoventa_nombre: string,
     *     ultimo_ticket: ?int,
     *     cantidad_tickets: int,
     *     ultimo_nota_credito: ?int,
     *     cantidad_notas_credito: int
     *   }>,
     *   resumen_etiqueta: string
     * }
     */
    public static function paraJornada(JornadaGastronomia $jornada): array
    {
        $jornada->loadMissing('empresa');

        if ($jornada->apertura_en === null || $jornada->cierre_en === null) {
            return ['filas' => [], 'resumen_etiqueta' => 'Jornada sin ventana de apertura/cierre.'];
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en->format('Y-m-d');

        $roles = self::rolesPuntoventaEmpresa($empresaId);
        if ($roles === []) {
            return ['filas' => [], 'resumen_etiqueta' => 'Sin puntos de venta configurados en gastronomía.'];
        }

        $pvIds = array_values(array_unique(array_map(
            static fn (array $r) => (int) $r['puntoventa_id'],
            $roles,
        )));
        $agregados = self::agregadosPorPuntoventa(
            $empresaId,
            $fechaJornada,
            Carbon::parse($jornada->apertura_en),
            Carbon::parse($jornada->cierre_en),
            $pvIds,
        );

        $filas = [];
        foreach ($roles as $rol) {
            $pvId = (int) $rol['puntoventa_id'];
            $agg = $agregados[$pvId] ?? null;
            $filas[] = [
                'configuracion_puntoventa_gastronomia_id' => (int) ($rol['configuracion_puntoventa_gastronomia_id'] ?? 0),
                'terminal_pc' => (string) ($rol['terminal_pc'] ?? ''),
                'terminal_descripcion' => (string) ($rol['terminal_descripcion'] ?? ''),
                'rol' => (string) $rol['rol'],
                'rol_etiqueta' => (string) $rol['rol_etiqueta'],
                'puntoventa_id' => $pvId,
                'puntoventa_codigo' => (string) $rol['puntoventa_codigo'],
                'puntoventa_nombre' => (string) $rol['puntoventa_nombre'],
                'ultimo_ticket' => self::enteroONulo($agg['ultimo_ticket'] ?? null),
                'cantidad_tickets' => (int) ($agg['cantidad_tickets'] ?? 0),
                'ultimo_nota_credito' => self::enteroONulo($agg['ultimo_nota_credito'] ?? null),
                'cantidad_notas_credito' => (int) ($agg['cantidad_notas_credito'] ?? 0),
            ];
        }

        $partes = [];
        foreach ($filas as $f) {
            $pv = trim($f['puntoventa_codigo'].' '.$f['puntoventa_nombre']);
            $ultT = $f['ultimo_ticket'] ?? '—';
            $ultNc = $f['ultimo_nota_credito'] ?? '—';
            $rol = $f['rol_etiqueta'] ?? '';
            $term = trim($f['terminal_pc'] ?? '');
            $pref = $rol !== '' ? $rol.($term !== '' ? ' '.$term : '').' ' : '';
            $partes[] = $pref.$pv.': ticket '.$ultT.', NC '.$ultNc;
        }

        return [
            'filas' => $filas,
            'resumen_etiqueta' => $partes !== []
                ? implode(' · ', $partes)
                : 'Sin comprobantes en la jornada.',
        ];
    }

    /**
     * Todos los PV CAE y CAEA de cada terminal gastronómica configurada en la empresa (sin colapsar por PV).
     *
     * @return list<array{
     *   configuracion_puntoventa_gastronomia_id: int,
     *   terminal_pc: string,
     *   terminal_descripcion: string,
     *   rol: string,
     *   rol_etiqueta: string,
     *   puntoventa_id: int,
     *   puntoventa_codigo: string,
     *   puntoventa_nombre: string
     * }>
     */
    private static function rolesPuntoventaEmpresa(int $empresaId): array
    {
        $configs = ConfiguracionPuntoventaGastronomia::query()
            ->with(['puntoventaCae', 'puntoventaCaea'])
            ->where('empresa_id', $empresaId)
            ->orderBy('identificador_pc')
            ->orderBy('id')
            ->get();

        $roles = [];

        foreach ($configs as $cfg) {
            foreach (['cae' => $cfg->puntoventa_cae_id, 'caea' => $cfg->puntoventa_caea_id] as $rolKey => $pvIdRaw) {
                $pvId = (int) $pvIdRaw;
                if ($pvId <= 0) {
                    continue;
                }
                $pv = $rolKey === 'cae'
                    ? ($cfg->puntoventaCae ?? Puntoventa::query()->find($pvId))
                    : ($cfg->puntoventaCaea ?? Puntoventa::query()->find($pvId));
                if ($pv === null) {
                    continue;
                }
                $roles[] = [
                    'configuracion_puntoventa_gastronomia_id' => (int) $cfg->id,
                    'terminal_pc' => (string) ($cfg->identificador_pc ?? ''),
                    'terminal_descripcion' => (string) ($cfg->descripcion ?? ''),
                    'rol' => $rolKey,
                    'rol_etiqueta' => strtoupper($rolKey),
                    'puntoventa_id' => $pvId,
                    'puntoventa_codigo' => (string) ($pv->codigo ?? ''),
                    'puntoventa_nombre' => (string) ($pv->nombre ?? ''),
                ];
            }
        }

        usort($roles, static fn (array $a, array $b): int => strcmp(
            $a['terminal_pc'].$a['rol_etiqueta'].$a['puntoventa_codigo'],
            $b['terminal_pc'].$b['rol_etiqueta'].$b['puntoventa_codigo'],
        ));

        return $roles;
    }

    /**
     * @param  list<int>  $puntoventaIds
     * @return array<int, array{
     *   ultimo_ticket: ?int,
     *   cantidad_tickets: int,
     *   ultimo_nota_credito: ?int,
     *   cantidad_notas_credito: int
     * }>
     */
    private static function agregadosPorPuntoventa(
        int $empresaId,
        string $fechaJornada,
        Carbon $desde,
        Carbon $hastaInclusive,
        array $puntoventaIds,
    ): array {
        if ($puntoventaIds === []) {
            return [];
        }

        $filas = GastronomiaTurnoOperativoTotalesSupport::queryEmisionesJornadaEmpresa(
            $empresaId,
            $fechaJornada,
            $desde,
            $hastaInclusive,
        )
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->whereIn('venta.puntoventa_id', $puntoventaIds)
            ->select([
                'venta.puntoventa_id',
                DB::raw('MAX(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NULL THEN venta.numerocomprobante END) AS ultimo_ticket'),
                DB::raw('SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NULL THEN 1 ELSE 0 END) AS cantidad_tickets'),
                DB::raw('MAX(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NOT NULL THEN venta.numerocomprobante END) AS ultimo_nota_credito'),
                DB::raw('SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_notas_credito'),
            ])
            ->groupBy('venta.puntoventa_id')
            ->get();

        $map = [];
        foreach ($filas as $fila) {
            $pvId = (int) $fila->puntoventa_id;
            $map[$pvId] = [
                'ultimo_ticket' => self::enteroONulo($fila->ultimo_ticket),
                'cantidad_tickets' => (int) $fila->cantidad_tickets,
                'ultimo_nota_credito' => self::enteroONulo($fila->ultimo_nota_credito),
                'cantidad_notas_credito' => (int) $fila->cantidad_notas_credito,
            ];
        }

        return $map;
    }

    private static function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $n = (int) $valor;

        return $n > 0 ? $n : null;
    }
}
