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
        $venta->loadMissing(['remitos.puntoventas']);
        if ($venta->remitos) {
            return self::paraRemito($venta->remitos);
        }

        return null;
    }

    public static function paraRemito(Remito $remito): ?Cai
    {
        $remito->loadMissing(['puntoventas']);
        $sucursal = (int) ($remito->puntoventas->codigo ?? 0);
        if ($sucursal <= 0) {
            return null;
        }

        return self::vigente($sucursal, self::fechaYmd($remito->fecha));
    }

    public static function vigente(int $sucursal, string $fechaYmd): ?Cai
    {
        if ($sucursal <= 0 || $fechaYmd === '') {
            return null;
        }

        return Cai::query()
            ->where('letra', 'R')
            ->where('sucursal', $sucursal)
            ->whereDate('fecha_vencimiento', '>=', $fechaYmd)
            ->where('numero_cai', '!=', '')
            ->orderBy('fecha_vencimiento')
            ->first();
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
