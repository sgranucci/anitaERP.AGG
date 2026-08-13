<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Support\Configuracion\EntornoEmpresaSupport;
use InvalidArgumentException;

/**
 * Clave de supervisor para anular en el POS gastronomía una cuenta abierta que no se facturó.
 * El cajero no puede borrar cuentas a su antojo: hace falta la clave.
 * Solo AGG + empresas configuradas (Kandiko). No aplica a saneamiento ni a otros POS.
 */
final class GastronomiaAnularCuentaPendienteClaveSupport
{
    /**
     * @return list<int>
     */
    public static function empresaIds(): array
    {
        $raw = config('gastronomia.anular_cuenta_pendiente_empresa_ids', [2]);
        if (! is_array($raw)) {
            return [2];
        }

        $ids = [];
        foreach ($raw as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        return $ids !== [] ? array_values(array_unique($ids)) : [2];
    }

    public static function activoParaEmpresa(?int $empresaId): bool
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return false;
        }
        if (! (bool) config('gastronomia.anular_cuenta_pendiente_exige_clave', false)) {
            return false;
        }
        if ($empresaId === null || $empresaId <= 0) {
            return false;
        }

        return in_array($empresaId, self::empresaIds(), true);
    }

    public static function cuentaTieneConsumos(CuentaGastronomia $cuenta): bool
    {
        if ($cuenta->relationLoaded('lineas')) {
            return $cuenta->lineas->isNotEmpty();
        }

        return $cuenta->lineas()->exists();
    }

    public static function exigeClave(CuentaGastronomia $cuenta): bool
    {
        return self::activoParaEmpresa((int) $cuenta->empresa_id);
    }

    public static function validar(CuentaGastronomia $cuenta, ?string $claveIngresada): void
    {
        if (! self::exigeClave($cuenta)) {
            return;
        }

        $esperada = (string) config('gastronomia.anular_cuenta_pendiente_clave', '');
        if ($esperada === '') {
            throw new InvalidArgumentException(
                'Falta configurar la clave de supervisor para anular cuentas (GASTRONOMIA_ANULAR_CUENTA_PENDIENTE_CLAVE).'
            );
        }

        $ingresada = (string) $claveIngresada;
        if ($ingresada === '' || ! hash_equals($esperada, $ingresada)) {
            throw new InvalidArgumentException(
                'Clave de supervisor incorrecta. No se puede anular la cuenta.'
            );
        }
    }
}
