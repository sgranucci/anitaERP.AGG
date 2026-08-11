<?php

namespace App\Support\Compras;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Condicionpagocuota;
use App\Models\Ventas\Formapago;
use Illuminate\Support\Facades\DB;

/**
 * Defaults para precargar comprobante a venir en OC cuando el proveedor
 * no tiene condición / forma de pago: asume «Contado» (crea el maestro si falta).
 */
final class OrdencompraCondicionPagoDefaultSupport
{
    public const NOMBRE_CONTADO = 'Contado';

    /**
     * ID de condición de pago Contado (busca por nombre/código; crea si no hay).
     */
    public static function idCondicionpagoContado(): int
    {
        $existente = Condicionpago::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(nombre) = ?', ['contado'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['contado %'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['% contado'])
                    ->orWhere('codigo', 1)
                    ->orWhere('codigo', '1');
            })
            ->orderBy('id')
            ->first();

        if ($existente) {
            self::asegurarCuotaContado((int) $existente->id);

            return (int) $existente->id;
        }

        return (int) DB::transaction(function () {
            $codigo = Condicionpago::query()->where('codigo', 1)->exists()
                ? ((int) Condicionpago::query()->max('codigo') + 1)
                : 1;

            $cp = Condicionpago::query()->create([
                'nombre' => self::NOMBRE_CONTADO,
                'codigo' => (string) $codigo,
                'aplicacion' => 'C',
            ]);
            self::asegurarCuotaContado((int) $cp->id);

            return (int) $cp->id;
        });
    }

    /**
     * ID de forma de pago Contado / Efectivo (crea Contado si el maestro está vacío).
     */
    public static function idFormapagoContado(): int
    {
        $existente = Formapago::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(nombre) = ?', ['contado'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%contado%'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%efectivo%'])
                    ->orWhereRaw('LOWER(abreviatura) = ?', ['c'])
                    ->orWhereRaw('LOWER(abreviatura) = ?', ['e']);
            })
            ->orderBy('id')
            ->first();

        if ($existente) {
            return (int) $existente->id;
        }

        $fp = Formapago::query()->create([
            'nombre' => self::NOMBRE_CONTADO,
            'abreviatura' => 'C',
        ]);

        return (int) $fp->id;
    }

    /**
     * Preferido del payload/proveedor, o Contado.
     */
    public static function resolverCondicionpagoId(int $preferido = 0): int
    {
        if ($preferido > 0 && Condicionpago::query()->whereKey($preferido)->exists()) {
            return $preferido;
        }

        return self::idCondicionpagoContado();
    }

    /**
     * Preferido del proveedor, o Contado/Efectivo.
     */
    public static function resolverFormapagoId(int $preferido = 0): int
    {
        if ($preferido > 0 && Formapago::query()->whereKey($preferido)->exists()) {
            return $preferido;
        }

        return self::idFormapagoContado();
    }

    private static function asegurarCuotaContado(int $condicionpagoId): void
    {
        if ($condicionpagoId <= 0) {
            return;
        }
        if (Condicionpagocuota::query()->where('condicionpago_id', $condicionpagoId)->exists()) {
            return;
        }

        Condicionpagocuota::query()->create([
            'condicionpago_id' => $condicionpagoId,
            'cuota' => 1,
            'tipoplazo' => 'D',
            'plazo' => 0,
            'fechavencimiento' => null,
            'porcentaje' => 100,
            'interes' => null,
        ]);
    }
}
