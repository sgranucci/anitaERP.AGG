<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detección ERP (sin ARCA) de huecos de numeración FAC en la ventana de un turno operativo.
 */
final class GastronomiaTurnoHuecosArcaSupport
{
    /**
     * @return array{
     *   cantidad: int,
     *   puntoventa_id: ?int,
     *   puntoventa_codigo: ?string,
     *   fecha_jornada: ?string,
     *   huecos: list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>,
     *   numeros_faltantes: list<int>,
     *   preview: list<array{numero:int,puntoventa_codigo:string}>
     * }
     */
    public static function detectarParaTurno(
        TurnoOperativoGastronomia $turno,
        ?Carbon $hastaInclusive = null,
    ): array {
        $vacio = [
            'cantidad' => 0,
            'puntoventa_id' => null,
            'puntoventa_codigo' => null,
            'fecha_jornada' => null,
            'huecos' => [],
            'numeros_faltantes' => [],
            'preview' => [],
        ];

        $turno->loadMissing(['jornada', 'configuracionPuntoventa.puntoventaCae']);

        if ($turno->habilitacion_en === null || trim((string) $turno->identificador_pc) === '') {
            return $vacio;
        }

        $cfg = $turno->configuracionPuntoventa;
        if ($cfg === null) {
            $cfg = ConfiguracionPuntoventaGastronomia::query()
                ->with('puntoventaCae')
                ->where('identificador_pc', $turno->identificador_pc)
                ->where('empresa_id', $turno->empresa_id)
                ->first();
        }

        $pvCae = $cfg?->puntoventaCae;
        $pvId = (int) ($cfg->puntoventa_cae_id ?? 0);
        if ($pvCae === null && $pvId > 0) {
            $pvCae = Puntoventa::query()->find($pvId);
        }
        if ($pvCae === null || $pvId <= 0) {
            return $vacio;
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? ($hastaInclusive ?? now())->format('Y-m-d');
        $desde = Carbon::parse($turno->habilitacion_en);
        $pc = (string) $turno->identificador_pc;
        $empresaId = (int) $turno->empresa_id;
        $pvCodigo = str_pad(trim((string) ($pvCae->codigo ?? '')), 5, '0', STR_PAD_LEFT);

        $numerosCircuito = self::numerosTicketsEnVentana(
            $pc,
            $empresaId,
            $fechaJornada,
            $desde,
            $hastaInclusive,
            $pvId,
        );

        if (count($numerosCircuito) < 2) {
            return array_merge($vacio, [
                'puntoventa_id' => $pvId,
                'puntoventa_codigo' => $pvCodigo,
                'fecha_jornada' => $fechaJornada,
            ]);
        }

        $numerosCompartidos = self::numerosTicketsDiaEmpresaPv($empresaId, $fechaJornada, $pvId);
        $huecos = GastronomiaNumeracionHuecosSupport::detectarHuecosSecuenciaCompartida(
            $numerosCircuito,
            $numerosCompartidos,
        );

        $faltantes = [];
        foreach ($huecos as $hueco) {
            $csv = trim((string) ($hueco['faltantes'] ?? ''));
            if ($csv === '') {
                continue;
            }
            foreach (explode(',', $csv) as $n) {
                $n = (int) trim($n);
                if ($n > 0) {
                    $faltantes[$n] = $n;
                }
            }
        }
        $faltantes = array_values($faltantes);
        sort($faltantes, SORT_NUMERIC);

        $preview = [];
        foreach (array_slice($faltantes, 0, 12) as $numero) {
            $preview[] = [
                'numero' => $numero,
                'puntoventa_codigo' => $pvCodigo,
            ];
        }

        return [
            'cantidad' => count($faltantes),
            'puntoventa_id' => $pvId,
            'puntoventa_codigo' => $pvCodigo,
            'fecha_jornada' => $fechaJornada,
            'huecos' => $huecos,
            'numeros_faltantes' => $faltantes,
            'preview' => $preview,
        ];
    }

    /**
     * Resumen liviano para poll de estado (sin listas largas).
     *
     * @return array{cantidad:int,puntoventa_codigo:?string,preview:list<array{numero:int,puntoventa_codigo:string}>}
     */
    public static function resumenParaEstado(TurnoOperativoGastronomia $turno): array
    {
        $det = self::detectarParaTurno($turno);

        return [
            'cantidad' => (int) $det['cantidad'],
            'puntoventa_codigo' => $det['puntoventa_codigo'],
            'preview' => $det['preview'],
        ];
    }

    /**
     * @return list<int>
     */
    public static function numerosTicketsEnVentana(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
        int $puntoventaId,
    ): array {
        $rows = self::queryEmisionesTickets(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $desdeHabilitacion,
            $hastaInclusive,
        )
            ->where('venta.puntoventa_id', $puntoventaId)
            ->pluck('venta.numerocomprobante');

        return GastronomiaNumeracionHuecosSupport::normalizarNumeros($rows);
    }

    /**
     * Números FAC del día en el PV (todos los circuitos / PCs) para secuencia compartida.
     *
     * @return list<int>
     */
    public static function numerosTicketsDiaEmpresaPv(int $empresaId, string $fechaJornada, int $puntoventaId): array
    {
        $rows = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereNull('deleted_at')
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('fechajornada')
                            ->whereDate('fecha', $fechaJornada);
                    });
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('venta_gastronomia_emision as vge')
                    ->whereColumn('vge.venta_id', 'venta.id')
                    ->whereNull('vge.venta_factura_origen_id');
            })
            ->pluck('numerocomprobante');

        return GastronomiaNumeracionHuecosSupport::normalizarNumeros($rows);
    }

    /**
     * @return Builder<VentaGastronomiaEmision>
     */
    private static function queryEmisionesTickets(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
    ): Builder {
        return VentaGastronomiaEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta_gastronomia_emision.identificador_pc', $identificadorPc)
            ->whereNull('venta_gastronomia_emision.venta_factura_origen_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereNull('venta.deleted_at')
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
}
