<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cai;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use Carbon\Carbon;

final class CaiRemitoVigenteSupport
{
    public static function paraVenta(Venta $venta): ?Cai
    {
        $venta->loadMissing(['remitos.puntoventas', 'puntoventaremito']);
        if ($venta->remitos) {
            return self::paraRemito($venta->remitos);
        }

        return self::vigenteEnSucursales(
            self::sucursalesCandidatas(
                (int) ($venta->puntoventaremito_id ?? 0),
                (string) ($venta->puntoventaremito?->codigo ?? ''),
            ),
            self::fechaYmd($venta->fecha ?? null),
        );
    }

    public static function paraRemito(Remito $remito): ?Cai
    {
        $remito->loadMissing(['puntoventas']);

        return self::vigenteEnSucursales(
            self::sucursalesCandidatas(
                (int) ($remito->puntoventa_id ?? 0),
                (string) ($remito->puntoventas?->codigo ?? ''),
            ),
            self::fechaYmd($remito->fecha),
        );
    }

    public static function vigente(int $sucursal, string $fechaYmd): ?Cai
    {
        return self::vigenteEnSucursales([$sucursal], $fechaYmd);
    }

    /**
     * En Anita/CAI, sucursal a veces es el id del PV y a veces el código numérico.
     *
     * @param  list<int>  $sucursales
     */
    public static function vigenteEnSucursales(array $sucursales, string $fechaYmd): ?Cai
    {
        $sucursales = array_values(array_unique(array_filter(
            array_map('intval', $sucursales),
            static fn (int $s): bool => $s > 0
        )));
        if ($sucursales === [] || $fechaYmd === '') {
            return null;
        }

        return Cai::query()
            ->where('letra', 'R')
            ->whereIn('sucursal', $sucursales)
            ->whereDate('fecha_vencimiento', '>=', $fechaYmd)
            ->where('numero_cai', '!=', '')
            ->orderBy('fecha_vencimiento')
            ->first();
    }

    /**
     * @return list<int>
     */
    public static function sucursalesCandidatas(int $puntoventaId, string $codigo): array
    {
        $candidatas = [];
        if ($puntoventaId > 0) {
            $candidatas[] = $puntoventaId;
        }
        $codigoEntero = (int) preg_replace('/\D+/', '', $codigo);
        if ($codigoEntero > 0) {
            $candidatas[] = $codigoEntero;
        }

        return array_values(array_unique($candidatas));
    }

    private static function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->format('Y-m-d');
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texto)) {
            return substr($texto, 0, 10);
        }
        $ts = strtotime($texto);

        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
