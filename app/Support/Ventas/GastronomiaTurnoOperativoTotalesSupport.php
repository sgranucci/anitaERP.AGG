<?php

namespace App\Support\Ventas;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Totales de facturación gastronomía por PC, mozo y medio de pago.
 */
final class GastronomiaTurnoOperativoTotalesSupport
{
    /**
     * @return array{
     *   total_general: float,
     *   cantidad_comprobantes: int,
     *   redondeo_invitaciones_sugerido: float,
     *   por_mozo: list<array{mozo_id:?int, mozo_codigo:?string, mozo_nombre:string, total:float, cantidad:int}>,
     *   por_medio_pago: list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     * }
     */
    public static function calcular(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
    ): array {
        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion);

        $porMozo = [];
        $totalGeneral = 0.0;
        $redondeoInvitaciones = 0.0;
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta) {
                continue;
            }

            $total = (float) $venta->total;
            $totalGeneral += $total;

            if (abs($total - $importeMinimo) < 0.001) {
                $redondeoInvitaciones += $importeMinimo;
            }

            $mozoId = $em->cuenta?->mozo_gastronomia_id;
            $key = $mozoId !== null ? (string) $mozoId : '0';
            if (! isset($porMozo[$key])) {
                $mozo = $em->cuenta?->mozo;
                $porMozo[$key] = [
                    'mozo_id' => $mozoId ? (int) $mozoId : null,
                    'mozo_codigo' => $mozo?->codigo,
                    'mozo_nombre' => $mozo?->nombre ?? 'Sin mozo',
                    'total' => 0.0,
                    'cantidad' => 0,
                ];
            }
            $porMozo[$key]['total'] += $total;
            $porMozo[$key]['cantidad']++;
        }

        $porMedio = self::agregarMediosPago($emisiones);

        usort($porMozo, fn ($a, $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));
        usort($porMedio, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'total_general' => round($totalGeneral, 2),
            'cantidad_comprobantes' => $emisiones->count(),
            'redondeo_invitaciones_sugerido' => round($redondeoInvitaciones, 2),
            'por_mozo' => array_values($porMozo),
            'por_medio_pago' => $porMedio,
        ];
    }

    /**
     * @return Collection<int, VentaGastronomiaEmision>
     */
    private static function emisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
    ): Collection {
        $q = VentaGastronomiaEmision::query()
            ->where('identificador_pc', $identificadorPc)
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada, $desdeHabilitacion) {
                $v->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
                if ($desdeHabilitacion !== null) {
                    $v->where('created_at', '>=', $desdeHabilitacion);
                }
            })
            ->with([
                'venta',
                'cuenta.mozo',
            ]);

        return $q->get();
    }

    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     */
    private static function agregarMediosPago(Collection $emisiones): array
    {
        $acum = [];

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta) {
                continue;
            }

            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);

            foreach ($medios as $lineas) {
                foreach ($lineas as $linea) {
                    $ccId = (int) ($linea->cuentacaja_id ?? 0);
                    if ($ccId <= 0) {
                        continue;
                    }
                    if (! isset($acum[$ccId])) {
                        $acum[$ccId] = [
                            'cuentacaja_id' => $ccId,
                            'codigo' => (string) ($linea->codigo ?? ''),
                            'nombre' => trim((string) ($linea->nombre ?? $linea->cuenta ?? '')),
                            'total' => 0.0,
                        ];
                    }
                    $acum[$ccId]['total'] += (float) $linea->monto;
                }
            }
        }

        foreach ($acum as &$row) {
            $row['total'] = round($row['total'], 2);
        }

        return array_values($acum);
    }
}
