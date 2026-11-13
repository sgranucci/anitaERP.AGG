<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta de anticipo de clientes (`cliente.ANTICIPO_DE_CLIENTES`, código del plan).
 *
 * Con cuenta configurada, el sobrante de una cobranza (COA) imputa el Haber a
 * anticipos; la deuda abierta de esa COA cuadra con esa cuenta, no con deudores.
 * Sin cuenta configurada la COA sigue en la cuenta del cliente.
 */
final class ClienteAnticipoCuentaContableSupport
{
    /** @var array<int, int|null> */
    private static array $cachePorEmpresa = [];

    public static function cuentaAnticipoId(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }
        if (array_key_exists($empresaId, self::$cachePorEmpresa)) {
            return self::$cachePorEmpresa[$empresaId];
        }

        $codigo = (int) config('cliente.ANTICIPO_DE_CLIENTES', 0);
        if ($codigo <= 0) {
            return self::$cachePorEmpresa[$empresaId] = null;
        }

        $id = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', (string) $codigo)
            ->value('id');
        $id = $id !== null ? (int) $id : 0;

        return self::$cachePorEmpresa[$empresaId] = $id > 0 ? $id : null;
    }

    /**
     * Crédito de CC nacido como COA (pago de más / cobro anticipado).
     */
    public static function esCreditoAnticipo(Cliente_Cuentacorriente $credito, ?string $abreviaturaCobranza = null): bool
    {
        if ((float) $credito->total >= 0 || (int) ($credito->cobranza_id ?? 0) <= 0) {
            return false;
        }

        $abrev = strtoupper(trim((string) $abreviaturaCobranza));
        if ($abrev === '') {
            $abrev = strtoupper(trim((string) (
                $credito->cobranzas?->tipotransaccioncajas?->abreviatura ?? ''
            )));
        }

        return $abrev === 'COA';
    }

    /**
     * Cuenta a la que imputar la deuda abierta del crédito: anticipos si es COA
     * y la empresa tiene la cuenta; null = seguir con la cuenta del cliente.
     */
    public static function cuentaParaCreditoAbierto(
        Cliente_Cuentacorriente $credito,
        ?string $abreviaturaCobranza = null
    ): ?int {
        if (! self::esCreditoAnticipo($credito, $abreviaturaCobranza)) {
            return null;
        }

        return self::cuentaAnticipoId((int) $credito->empresa_id);
    }
}
