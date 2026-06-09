<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Ventas\Puntoventa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Últimos números de ticket y nota de crédito emitidos en un turno operativo estacionamiento.
 */
final class EstacionamientoTurnoNumeracionComprobanteSupport
{
    /**
     * @return array{filas: list<array<string, mixed>>}
     */
    public static function paraTurno(TurnoOperativoEstacionamiento $turno, ?Carbon $hastaInclusive = null): array
    {
        $turno->loadMissing(['jornada', 'configuracionPuntoventa.puntoventaCae', 'configuracionPuntoventa.puntoventaCaea']);

        if ($turno->habilitacion_en === null || trim((string) $turno->identificador_pc) === '') {
            return ['filas' => []];
        }

        $cfg = $turno->configuracionPuntoventa;
        if ($cfg === null) {
            $cfg = ConfiguracionPuntoventaEstacionamiento::query()
                ->with(['puntoventaCae', 'puntoventaCaea'])
                ->where('identificador_pc', $turno->identificador_pc)
                ->where('empresa_id', $turno->empresa_id)
                ->first();
        }

        if ($cfg === null) {
            return ['filas' => []];
        }

        $roles = self::rolesPuntoventa($cfg);
        if ($roles === []) {
            return ['filas' => []];
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? ($hastaInclusive ?? now())->format('Y-m-d');

        $pvIds = array_values(array_unique(array_column($roles, 'puntoventa_id')));
        $agregados = self::agregadosPorPuntoventa(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            Carbon::parse($turno->habilitacion_en),
            $hastaInclusive,
            $pvIds,
        );

        $filas = [];
        foreach ($roles as $rol) {
            $pvId = (int) $rol['puntoventa_id'];
            $agg = $agregados[$pvId] ?? null;
            $filas[] = [
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

        return ['filas' => $filas];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rolesPuntoventa(ConfiguracionPuntoventaEstacionamiento $cfg): array
    {
        $roles = [];

        $caeId = (int) ($cfg->puntoventa_cae_id ?? 0);
        if ($caeId > 0) {
            $pv = $cfg->puntoventaCae ?? Puntoventa::query()->find($caeId);
            if ($pv) {
                $roles[] = self::filaRol('cae', 'CAE', $pv);
            }
        }

        $caeaId = (int) ($cfg->puntoventa_caea_id ?? 0);
        if ($caeaId > 0 && $caeaId !== $caeId) {
            $pv = $cfg->puntoventaCaea ?? Puntoventa::query()->find($caeaId);
            if ($pv) {
                $roles[] = self::filaRol('caea', 'CAEA', $pv);
            }
        } elseif ($caeaId > 0 && $caeId > 0 && $caeaId === $caeId) {
            $pv = $cfg->puntoventaCaea ?? Puntoventa::query()->find($caeaId);
            if ($pv) {
                $roles[] = self::filaRol('caea', 'CAEA', $pv);
            }
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaRol(string $rol, string $etiqueta, Puntoventa $pv): array
    {
        return [
            'rol' => $rol,
            'rol_etiqueta' => $etiqueta,
            'puntoventa_id' => (int) $pv->id,
            'puntoventa_codigo' => (string) ($pv->codigo ?? ''),
            'puntoventa_nombre' => (string) ($pv->nombre ?? ''),
        ];
    }

    /**
     * @param  list<int>  $puntoventaIds
     * @return array<int, array<string, mixed>>
     */
    private static function agregadosPorPuntoventa(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
        array $puntoventaIds,
    ): array {
        $filas = self::queryEmisionesEnAlcance(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $desdeHabilitacion,
            $hastaInclusive,
        )
            ->whereIn('venta.puntoventa_id', $puntoventaIds)
            ->select([
                'venta.puntoventa_id',
                DB::raw('MAX(CASE WHEN venta_estacionamiento_emision.venta_factura_origen_id IS NULL THEN venta.numerocomprobante END) AS ultimo_ticket'),
                DB::raw('SUM(CASE WHEN venta_estacionamiento_emision.venta_factura_origen_id IS NULL THEN 1 ELSE 0 END) AS cantidad_tickets'),
                DB::raw('MAX(CASE WHEN venta_estacionamiento_emision.venta_factura_origen_id IS NOT NULL THEN venta.numerocomprobante END) AS ultimo_nota_credito'),
                DB::raw('SUM(CASE WHEN venta_estacionamiento_emision.venta_factura_origen_id IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_notas_credito'),
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

    /**
     * @return Builder<VentaEstacionamientoEmision>
     */
    private static function queryEmisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
    ): Builder {
        return VentaEstacionamientoEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_estacionamiento_emision.venta_id')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta_estacionamiento_emision.identificador_pc', $identificadorPc)
            ->where('puntoventa.empresa_id', $empresaId)
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('venta.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('venta.fechajornada')
                            ->whereDate('venta.fecha', $fechaJornada);
                    });
            })
            ->where('venta.created_at', '>=', $desdeHabilitacion)
            ->when($hastaInclusive !== null, fn (Builder $q) => $q->where('venta.created_at', '<=', $hastaInclusive));
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
