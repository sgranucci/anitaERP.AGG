<?php

namespace App\Support\Ventas;

use App\Models\Contable\Cuentacontable;

/**
 * Cuenta deudores por ventas por defecto en el ABM cliente (empresa + código de config).
 */
final class ClienteCuentacontableDefaultSupport
{
    public static function find(): ?Cuentacontable
    {
        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $codigoRaw = trim((string) config('cliente.DEUDORES_POR_VENTAS', ''));
        if ($empresaId <= 0 || $codigoRaw === '') {
            return null;
        }

        $variantes = self::variantesCodigo($codigoRaw);
        if ($variantes === []) {
            return null;
        }

        return Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $variantes)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $out = [$codigo];
        if (ctype_digit($codigo)) {
            $out[] = (string) (int) $codigo;
            $out[] = str_pad((string) (int) $codigo, strlen($codigo), '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($out, static fn ($v) => $v !== '')));
    }
}
