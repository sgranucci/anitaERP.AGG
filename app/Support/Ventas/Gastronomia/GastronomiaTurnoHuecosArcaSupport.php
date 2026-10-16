<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detección ERP (sin ARCA) de huecos fiscales en las fechas de comprobante de un turno.
 *
 * La serie es punto de venta + código AFIP. La jornada no participa: un comprobante
 * recuperado conserva su fecha original aunque se impute a la jornada del turno.
 */
final class GastronomiaTurnoHuecosArcaSupport
{
    /**
     * @return array{
     *   cantidad: int,
     *   puntoventa_id: ?int,
     *   puntoventa_codigo: ?string,
     *   fecha_jornada: ?string,
     *   huecos: list<array{desde:int,hasta:int,faltantes:string,cantidad:int,codigo_afip?:int,fecha?:string}>,
     *   numeros_faltantes: list<int>,
     *   faltantes: list<array{numero:int,codigo_afip:int,fecha:string}>,
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
            'faltantes' => [],
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

        $series = self::fechasEmitidasEnTurno($pc, $empresaId, $pvId, $desde, $hastaInclusive);
        $huecos = [];
        $faltantes = [];
        foreach ($series as $codigoAfip => $fechas) {
            foreach ($fechas as $fechaComprobante) {
                foreach (GastronomiaNumeracionHuecosSupport::huecosSeriePorFechaComprobante($pvId, (int) $codigoAfip, $fechaComprobante) as $row) {
                    $row['codigo_afip'] = (int) $codigoAfip;
                    $row['fecha'] = $fechaComprobante;
                    $huecos[] = $row;
                    $csv = trim((string) ($row['faltantes'] ?? ''));
                    if ($csv === '') {
                        continue;
                    }
                    foreach (explode(',', $csv) as $n) {
                        $n = (int) trim($n);
                        if ($n <= 0) {
                            continue;
                        }
                        $faltantes[$codigoAfip.'|'.$n] = [
                            'numero' => $n,
                            'codigo_afip' => (int) $codigoAfip,
                            'fecha' => $fechaComprobante,
                        ];
                    }
                }
            }
        }

        $faltantes = array_values($faltantes);
        usort($faltantes, static function (array $a, array $b): int {
            return [$a['codigo_afip'], $a['numero']] <=> [$b['codigo_afip'], $b['numero']];
        });

        $numeros = array_map(static fn (array $f): int => (int) $f['numero'], $faltantes);
        $preview = [];
        foreach (array_slice($faltantes, 0, 12) as $faltante) {
            $preview[] = [
                'numero' => (int) $faltante['numero'],
                'puntoventa_codigo' => $pvCodigo,
            ];
        }

        return [
            'cantidad' => count($faltantes),
            'puntoventa_id' => $pvId,
            'puntoventa_codigo' => $pvCodigo,
            'fecha_jornada' => $fechaJornada,
            'huecos' => $huecos,
            'numeros_faltantes' => $numeros,
            'faltantes' => $faltantes,
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
     * Fechas de comprobante emitidas por el turno, agrupadas por código AFIP.
     * La ventana es la del turno (habilitación a cierre), no la jornada.
     *
     * @return array<int, list<string>>
     */
    private static function fechasEmitidasEnTurno(
        string $identificadorPc,
        int $empresaId,
        int $puntoventaId,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
    ): array {
        $filas = self::queryEmisionesTickets($identificadorPc, $empresaId, $desdeHabilitacion, $hastaInclusive)
            ->where('venta.puntoventa_id', $puntoventaId)
            ->get();

        $series = [];
        foreach ($filas as $fila) {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (int) $fila->codigo_afip,
                (string) $fila->codigo,
            );
            $fecha = $fila->fecha instanceof \DateTimeInterface
                ? $fila->fecha->format('Y-m-d')
                : substr((string) $fila->fecha, 0, 10);
            if ($codigoAfip <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                continue;
            }
            $series[$codigoAfip][$fecha] = $fecha;
        }

        $salida = [];
        foreach ($series as $codigoAfip => $fechas) {
            $lista = array_values($fechas);
            sort($lista);
            $salida[(int) $codigoAfip] = $lista;
        }

        return $salida;
    }

    /**
     * @return Builder<VentaGastronomiaEmision>
     */
    private static function queryEmisionesTickets(
        string $identificadorPc,
        int $empresaId,
        Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
    ): Builder {
        return VentaGastronomiaEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta_gastronomia_emision.identificador_pc', $identificadorPc)
            ->whereNull('venta_gastronomia_emision.venta_factura_origen_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('venta.codigo_afip', '>', 0)
            ->where('venta.created_at', '>=', $desdeHabilitacion)
            ->when($hastaInclusive !== null, fn (Builder $q) => $q->where('venta.created_at', '<=', $hastaInclusive))
            ->select([
                'venta.codigo_afip as codigo_afip',
                'venta.codigo as codigo',
                'venta.fecha as fecha',
            ]);
    }
}
