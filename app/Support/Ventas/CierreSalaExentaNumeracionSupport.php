<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;

/**
 * Numeración FBI/FSL: serie Anita (tipo + letra + sucursal), no el correlativo ERP del PV.
 *
 * p-vtamaquina.c / p-vtabingo.c: lee_numerador(in_tcomp, 'B', sucursal).
 * En AGG el unique de venta es (puntoventa_id, numerocomprobante): hay que
 * saltar un número ya usado en ese PV (p. ej. FBI 1–7 de prueba), pero el
 * comprobante oficial sigue la serie Informix (FSL Biyemas ~7000).
 */
final class CierreSalaExentaNumeracionSupport
{
    public static function siguienteNumero(
        string $tipoAbrev,
        string $letra,
        int $sucursal,
        int $empresaId,
        string $fechaYmd,
        int $puntoventaId,
        int $mayorQue = 0,
    ): int {
        $tipoAbrev = strtoupper(trim($tipoAbrev));
        $letra = strtoupper(trim($letra));
        $sucursalTxt = (string) max(0, $sucursal);

        $delDia = self::numeroAnitaDelDia($tipoAbrev, $letra, $sucursalTxt, $empresaId, $fechaYmd);
        if (
            $delDia > 0
            && $delDia > $mayorQue
            && ! self::ocupadoEnErp($puntoventaId, $delDia, $empresaId)
        ) {
            return $delDia;
        }

        $repo = app(VentaRepositoryInterface::class);
        $anitaMax = $repo->maxNumeroComprobanteAnitaBridge($tipoAbrev, $letra, $sucursalTxt);
        $erpMax = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpPorPuntoventa(
            $puntoventaId,
            $empresaId,
        );

        return max($anitaMax, $erpMax, $mayorQue) + 1;
    }

    public static function numeroAnitaDelDia(
        string $tipoAbrev,
        string $letra,
        string $sucursal,
        int $empresaId,
        string $fechaYmd,
    ): int {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);

        return app(VentaRepositoryInterface::class)->numeroComprobanteAnitaDelDia(
            $tipoAbrev,
            $letra,
            $sucursal,
            $fechaYmd,
            $empresaAnita > 0 ? $empresaAnita : null,
        );
    }

    public static function ocupadoEnErp(int $puntoventaId, int $numero, ?int $empresaId = null): bool
    {
        if ($puntoventaId <= 0 || $numero <= 0) {
            return false;
        }

        $query = Venta::query()
            ->where('venta.puntoventa_id', $puntoventaId)
            ->where('venta.numerocomprobante', $numero);

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        return $query->exists();
    }
}
