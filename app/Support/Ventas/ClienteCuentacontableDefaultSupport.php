<?php

namespace App\Support\Ventas;

use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Cliente;

/**
 * Cuenta deudores por ventas por defecto en el ABM cliente (empresa + código de config).
 */
final class ClienteCuentacontableDefaultSupport
{
    public const PERMISO_MODIFICAR = 'modificar-cuenta-contable-cliente';

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

    public static function puedeModificarEnAbm(): bool
    {
        return auth()->check() && can(self::PERMISO_MODIFICAR, false);
    }

    /**
     * Id de cuenta a grabar en ABM: sin permiso fuerza default (alta) o la existente (edición).
     * Con permiso respeta el request; si viene vacío en alta usa default.
     */
    public static function idParaGrabadoAbm(?int $clienteId, $cuentaIdRequest): ?int
    {
        if (self::puedeModificarEnAbm()) {
            $id = (int) ($cuentaIdRequest ?: 0);
            if ($id > 0) {
                return $id;
            }
            if ($clienteId !== null && $clienteId > 0) {
                $existente = (int) (Cliente::query()->where('id', $clienteId)->value('cuentacontable_id') ?? 0);

                return $existente > 0 ? $existente : self::find()?->id;
            }

            return self::find()?->id;
        }

        if (! auth()->check()) {
            $id = (int) ($cuentaIdRequest ?: 0);

            return $id > 0 ? $id : self::find()?->id;
        }

        if ($clienteId !== null && $clienteId > 0) {
            $existente = (int) (Cliente::query()->where('id', $clienteId)->value('cuentacontable_id') ?? 0);

            return $existente > 0 ? $existente : self::find()?->id;
        }

        return self::find()?->id;
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
