<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Models\Contable\Cuentacontable;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Cuenta de retención de ganancias en pagos a proveedores (RGP).
 * La misma que el asiento de la orden de pago: catálogo pago.retencion_ganancias.
 */
final class SicoreCuentaRgpSupport
{
    /**
     * @return array{id: int, codigo: string, codigo_anita: int, nombre: string, tipocuenta: ?string}|null
     */
    public static function ganancias(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $cuentaId = CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::PAGO_RETENCION_GANANCIAS,
        );
        if ($cuentaId === null || $cuentaId <= 0) {
            return null;
        }

        $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre', 'tipocuenta']);
        if ($cuenta === null) {
            return null;
        }

        $codigoAnita = (int) preg_replace('/\D/', '', (string) $cuenta->codigo);
        if ($codigoAnita <= 0) {
            return null;
        }

        return [
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'codigo_anita' => $codigoAnita,
            'nombre' => (string) $cuenta->nombre,
            'tipocuenta' => $cuenta->tipocuenta !== null ? (string) $cuenta->tipocuenta : null,
        ];
    }
}
