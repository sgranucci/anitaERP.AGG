<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;

final class TransferenciaMercaderiaIntercompanySupport
{
    public const PERMISO = 'deposito-intercompany-movimientos-de-stock';

    public static function puedeUsar(): bool
    {
        return can(self::PERMISO, false);
    }

    public static function depositoSalidaAutorizado(int $depmaeId, int $empresaIdFormulario): bool
    {
        if ($depmaeId <= 0) {
            return false;
        }

        if (self::puedeUsar()) {
            return Depmae::autorizadoParaUsuario($depmaeId);
        }

        return Depmae::autorizadoParaUsuarioYEmpresa($depmaeId, $empresaIdFormulario);
    }

    /**
     * Destino de transferencia: cualquier depósito de la empresa del formulario
     * (o de otra empresa si hay permiso intercompany). No exige usuario_deposito.
     */
    public static function depositoEntradaAutorizado(int $depmaeId, int $empresaIdFormulario): bool
    {
        if ($depmaeId <= 0) {
            return false;
        }

        if (self::puedeUsar()) {
            return Depmae::query()->whereKey($depmaeId)->exists();
        }

        return Depmae::existeParaEmpresa($depmaeId, $empresaIdFormulario);
    }

    /**
     * Empresa de la transferencia / asiento TRCONT: depósito entrada, luego salida, luego formulario.
     */
    public static function resolverEmpresaId(
        int $empresaIdFormulario,
        ?Depmae $depositoEntrada = null,
        ?Depmae $depositoSalida = null,
    ): int {
        $desdeDepositos = self::empresaIdDesdeDepositos($depositoEntrada, $depositoSalida);
        if ($desdeDepositos > 0) {
            return $desdeDepositos;
        }

        return $empresaIdFormulario;
    }

    public static function empresaIdDesdeDepositos(?Depmae $depositoEntrada, ?Depmae $depositoSalida): int
    {
        if ($depositoEntrada !== null && (int) $depositoEntrada->empresa_id > 0) {
            return (int) $depositoEntrada->empresa_id;
        }

        if ($depositoSalida !== null && (int) $depositoSalida->empresa_id > 0) {
            return (int) $depositoSalida->empresa_id;
        }

        return 0;
    }
}
