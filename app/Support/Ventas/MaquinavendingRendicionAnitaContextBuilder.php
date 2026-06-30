<?php

namespace App\Support\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;
use App\Support\Caja\AnitaSync\MaquinavendingRendicionCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\MaquinavendingRendicionMvartAnitaMapper;
use Carbon\Carbon;

final class MaquinavendingRendicionAnitaContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function desdeRendicion(MaquinavendingRendicion $rendicion): array
    {
        $rendicion->loadMissing([
            'mediosPago.cuentacaja',
            'maquinavending.puntoventa',
            'usuario',
            'articulos.articulo',
            'rendicionCaja',
        ]);

        $maquina = $rendicion->maquinavending;
        $pv = $maquina?->puntoventa;
        $fechaRend = Carbon::parse($rendicion->fecha_rendicion ?? now());
        $fechaJornada = $rendicion->fecha_jornada
            ? Carbon::parse($rendicion->fecha_jornada)->startOfDay()
            : $fechaRend->copy()->startOfDay();

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? MaquinavendingRendicionCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        $totalVentas = round((float) $rendicion->total_ventas, 2);
        $totalCobrado = round((float) $rendicion->total_cobrado, 2);
        $empresaId = (int) $rendicion->empresa_id;
        $cajaDefault = (int) (config('rendicion_maquinavending_anita.caja_id_default_por_empresa')[$empresaId] ?? 1);

        $numeroCierre = (int) $rendicion->numero_cierre;
        if ($numeroCierre <= 0) {
            $numeroCierre = (int) $rendicion->id;
        }

        $presentacionCaja = $rendicion->rendicionCaja;
        $totalZ = 0.0;
        $cajaId = $cajaDefault > 0 ? $cajaDefault : 1;
        if ($presentacionCaja !== null) {
            $totalZ = round((float) ($presentacionCaja->totalfactura ?? 0), 2);
            if ($totalZ <= 0) {
                $totalZ = round((float) ($presentacionCaja->totalcobrado ?? 0), 2);
            }
            $cajaInformix = (int) ($presentacionCaja->caja_id ?? 0);
            if ($cajaInformix > 0) {
                $cajaId = $cajaInformix;
            }
        }

        return [
            'nro_oper' => $nroOper,
            'nro_ticket' => $numeroCierre,
            'tipo_oper' => substr((string) config('rendicion_maquinavending_anita.tipo_oper', 'F'), 0, 1),
            'empresa_id' => $empresaId,
            'caja_id' => $cajaId,
            'usuario_id' => (int) ($rendicion->usuario_id ?? 0),
            'fecha_rendicion' => $fechaRend,
            'fecha_jornada' => $fechaJornada->format('Y-m-d'),
            'fecha_entera' => (int) $fechaJornada->format('Ymd'),
            'fecha_alfa' => $fechaJornada->format('d/m/y'),
            'hora' => $fechaRend->format('H:i:s'),
            'hora_carga' => now()->format('H:i:s'),
            'fecha_carga' => (int) now()->format('Ymd'),
            'total_x' => $totalVentas,
            'total_z' => $totalZ,
            'invitacion' => 0.0,
            'tot_nc' => 0.0,
            'tot_redondeo' => 0.0,
            'dif_caja' => round($totalCobrado - $totalVentas, 2),
            'ultimo_ticket' => $numeroCierre,
            'nro_z' => $numeroCierre,
            'turno_letra' => ' ',
            'sucursal_cae' => self::codigoPuntoventaEntero($pv?->codigo),
            'suc_caea' => 0,
            'nro_rend_vta' => (int) $rendicion->id,
            'host' => self::hostDesdeRendicion($rendicion),
            'tot_fc_caea' => 0.0,
            'tot_nc_caea' => 0.0,
            'movimientos' => $rendicion->mediosPago,
            'articulos_mvart' => MaquinavendingRendicionMvartAnitaMapper::lineasDesdeRendicion($rendicion),
        ];
    }

    public static function codigoPuntoventaEntero(?string $codigo): int
    {
        $digits = preg_replace('/\D/', '', (string) $codigo);

        return (int) ($digits !== '' ? $digits : 0);
    }

    /**
     * Identificador en rendg_host (máx. 15 caracteres Informix).
     * Ej.: "VENDING NRO.42" (correlativo de cierre por empresa).
     */
    public static function hostDesdeRendicion(MaquinavendingRendicion $rendicion): string
    {
        $nroCierre = (int) $rendicion->numero_cierre;
        if ($nroCierre <= 0) {
            $nroCierre = (int) $rendicion->id;
        }

        $host = sprintf('VENDING NRO.%d', $nroCierre);

        return MaquinavendingRendicionCabeceraAnitaMapper::texto($host, 15);
    }

    public static function esHostVending(?string $host): bool
    {
        $host = mb_strtoupper(trim((string) $host));
        if ($host === '') {
            return true;
        }

        return str_starts_with($host, 'VENDING NRO')
            || str_starts_with($host, 'VEND NRO');
    }
}
