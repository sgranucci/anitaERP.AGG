<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaTurnoNumeracionComprobanteSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el contexto de negocio para mapear una rendición ERP → rendgastro / rendvalor.
 */
final class RendicionGastronomiaAnitaContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    /**
     * @param  float|null  $totalZForzado  Si es null, rendg_total_z va en 0 (se asigna al presentar la jornada en caja).
     */
    public static function desdeRendicion(RendicionGastronomiaCaja $rendicion, ?float $totalZForzado = null): array
    {
        $rendicion->loadMissing([
            'movimientos.cuentacaja',
            'puntoventaCae',
            'puntoventaCaea',
            'turnoOperativo.turno',
            'turnoOperativo.jornada',
            'creousuario',
        ]);

        $turno = $rendicion->turnoOperativo;
        if ($turno === null) {
            throw new \InvalidArgumentException('La rendición no tiene turno operativo asociado.');
        }

        $fechaRend = Carbon::parse($rendicion->fecharendicion ?? now());
        // rendg_fecha / rendv_fecha = fecha de jornada (turno contable); fecharendicion = registro real en caja.
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $fechaRend->format('Y-m-d');
        $fechaJornadaCarbon = Carbon::parse($fechaJornada);

        $pvCaeId = (int) ($rendicion->puntoventa_cae_id ?? 0);
        $pvCaeaId = (int) ($rendicion->puntoventa_caea_id ?? 0);

        $numeracion = GastronomiaTurnoNumeracionComprobanteSupport::paraTurno(
            $turno,
            $turno->cierre_en ? Carbon::parse($turno->cierre_en) : null,
        );

        $ultimoTicketCae = self::ultimoTicketDesdeNumeracion($numeracion['filas'] ?? [], 'cae', $pvCaeId);

        $totalesCaea = $pvCaeaId > 0
            ? self::totalesFacturacionPuntoventaEnTurno($turno, $fechaJornada, $pvCaeaId)
            : ['total_fc' => 0.0, 'total_nc' => 0.0];

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionGastronomiaCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        $totalX = self::totalFacturacionSinNotasCreditoTurno($turno, $fechaJornada, $rendicion);
        $totalZ = $totalZForzado !== null
            ? round((float) $totalZForzado, 2)
            : 0.0;

        return [
            'nro_oper' => $nroOper,
            'tipo_oper' => substr((string) config('rendicion_gastronomia_anita.tipo_oper', 'F'), 0, 1),
            'empresa_id' => (int) $rendicion->empresa_id,
            'caja_id' => (int) $rendicion->caja_id,
            'usuario_id' => (int) ($rendicion->creousuario_id ?? 0),
            'fecha_rendicion' => $fechaRend,
            'fecha_jornada' => $fechaJornada,
            'fecha_entera' => (int) $fechaJornadaCarbon->format('Ymd'),
            'fecha_alfa' => $fechaJornadaCarbon->format('d/m/y'),
            'hora' => $fechaRend->format('H:i:s'),
            'hora_carga' => now()->format('H:i:s'),
            'fecha_carga' => (int) now()->format('Ymd'),
            'total_x' => $totalX,
            'total_z' => $totalZ,
            'invitacion' => round((float) $rendicion->totalinvitacion, 2),
            'tot_nc' => round((float) $rendicion->totalnotacredito, 2),
            'tot_redondeo' => round((float) $rendicion->totalredondeo + (float) $rendicion->totalredondeoinvitacion, 2),
            'dif_caja' => round((float) $rendicion->sobrantefaltante, 2),
            'ultimo_ticket' => $ultimoTicketCae ?? 0,
            'nro_z' => (int) ($turno->numero_cierre ?? 0),
            'turno_letra' => self::letraTurno($turno),
            'sucursal_cae' => self::codigoPuntoventaEntero($rendicion->puntoventaCae?->codigo),
            'suc_caea' => self::codigoPuntoventaEntero($rendicion->puntoventaCaea?->codigo),
            'nro_rend_vta' => (int) $rendicion->turno_operativo_gastronomia_id,
            'host' => substr((string) ($turno->identificador_pc ?? ''), 0, 15),
            'tot_fc_caea' => $totalesCaea['total_fc'],
            'tot_nc_caea' => $totalesCaea['total_nc'],
            'movimientos' => $rendicion->movimientos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function ultimoTicketDesdeNumeracion(array $filas, string $rol, int $puntoventaId): ?int
    {
        foreach ($filas as $fila) {
            if (($fila['rol'] ?? '') === $rol && (int) ($fila['puntoventa_id'] ?? 0) === $puntoventaId) {
                $ult = $fila['ultimo_ticket'] ?? null;

                return $ult !== null ? (int) $ult : null;
            }
        }

        foreach ($filas as $fila) {
            if (($fila['rol'] ?? '') === $rol && ! empty($fila['ultimo_ticket'])) {
                return (int) $fila['ultimo_ticket'];
            }
        }

        return null;
    }

    /**
     * rendg_total_x: facturación del turno sin NC (Anita descuenta rendg_tot_nc aparte).
     */
    private static function totalFacturacionSinNotasCreditoTurno(
        TurnoOperativoGastronomia $turno,
        string $fechaJornada,
        RendicionGastronomiaCaja $rendicion,
    ): float {
        if ($turno->habilitacion_en !== null && $turno->cierre_en !== null) {
            return GastronomiaTurnoOperativoTotalesSupport::totalFacturasSinNotasCredito(
                (string) $turno->identificador_pc,
                (int) $rendicion->empresa_id,
                $fechaJornada,
                Carbon::parse($turno->habilitacion_en),
                Carbon::parse($turno->cierre_en),
            );
        }

        return round(
            (float) ($rendicion->totalfactura ?? 0) + (float) ($rendicion->totalnotacredito ?? 0),
            2,
        );
    }

    private static function letraTurno(TurnoOperativoGastronomia $turno): string
    {
        $nombre = trim((string) ($turno->turno?->nombre ?? ''));
        if ($nombre === '') {
            return ' ';
        }

        return mb_strtoupper(mb_substr($nombre, 0, 1));
    }

    private static function codigoPuntoventaEntero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', $codigo);
    }

    /**
     * @return array{total_fc: float, total_nc: float}
     */
    public static function totalesFacturacionPuntoventaEnTurno(
        TurnoOperativoGastronomia $turno,
        string $fechaJornada,
        int $puntoventaId,
    ): array {
        if ($turno->habilitacion_en === null || $puntoventaId <= 0) {
            return ['total_fc' => 0.0, 'total_nc' => 0.0];
        }

        $hasta = $turno->cierre_en ? Carbon::parse($turno->cierre_en) : now();

        $filas = VentaGastronomiaEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->where('venta_gastronomia_emision.identificador_pc', (string) $turno->identificador_pc)
            ->where('venta.puntoventa_id', $puntoventaId)
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('venta.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('venta.fechajornada')
                            ->whereDate('venta.fecha', $fechaJornada);
                    });
            })
            ->where('venta.created_at', '>=', $turno->habilitacion_en)
            ->where('venta.created_at', '<=', $hasta)
            ->select([
                DB::raw('SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NULL THEN venta.total ELSE 0 END) AS total_fc'),
                DB::raw('SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NOT NULL THEN venta.total ELSE 0 END) AS total_nc'),
            ])
            ->first();

        $totalFc = round((float) ($filas->total_fc ?? 0), 2);
        $totalNc = round(abs((float) ($filas->total_nc ?? 0)), 2);

        return [
            'total_fc' => round($totalFc - $totalNc, 2),
            'total_nc' => $totalNc,
        ];
    }
}
