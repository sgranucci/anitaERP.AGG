<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\EmpresaWhatsappCuenta;

/**
 * Gate: WhatsApp solo opera si hay cuenta activa asociada a la empresa.
 */
final class WhatsappCanalSupport
{
    public static function masterHabilitado(): bool
    {
        return (bool) config('whatsapp.habilitado', true);
    }

    public static function cuentaActivaParaEmpresa(?int $empresaId): ?EmpresaWhatsappCuenta
    {
        if (! self::masterHabilitado() || ! $empresaId || $empresaId <= 0) {
            return null;
        }

        $cuenta = EmpresaWhatsappCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if (! $cuenta || ! $cuenta->estaOperativa()) {
            return null;
        }

        return $cuenta;
    }

    public static function empresaPuedeEnviar(?int $empresaId): bool
    {
        return self::cuentaActivaParaEmpresa($empresaId) !== null;
    }

    public static function normalizarCelular(?string $celular): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $celular);
        if ($digitos === null || strlen($digitos) < 10) {
            return null;
        }
        // Argentina: si viene 15… sin 54, anteponer 549
        if (str_starts_with($digitos, '15') && strlen($digitos) >= 10 && ! str_starts_with($digitos, '54')) {
            $digitos = '549'.substr($digitos, 2);
        }
        if (! str_starts_with($digitos, '54') && strlen($digitos) === 10) {
            $digitos = '54'.$digitos;
        }

        return $digitos;
    }
}
