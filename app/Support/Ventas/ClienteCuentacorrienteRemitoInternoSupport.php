<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;

/**
 * El remito interno (RIN) no es movimiento de cuenta corriente.
 * Solo registra que un pedido se dio de baja y por qué causa.
 * El ajuste sintético «AJU Anita RIN» existe únicamente para cerrar
 * esos RIN en la ficha: sale junto con ellos.
 */
final class ClienteCuentacorrienteRemitoInternoSupport
{
    public static function es(mixed $codigoVenta, mixed $abreviaturaTipo = null): bool
    {
        $codigo = strtoupper(trim((string) $codigoVenta));
        if (str_starts_with($codigo, 'RIN ') || str_starts_with($codigo, 'AJU ANITA RIN ')) {
            return true;
        }

        return strtoupper(trim((string) $abreviaturaTipo)) === 'RIN';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function excluir(Builder $query): void
    {
        $query->where(function (Builder $alcance) {
            $alcance->whereDoesntHave('ventas', function (Builder $venta) {
                self::restringirVenta($venta);
            });
        });
    }

    /**
     * Aplicaciones cuya fila de cuenta corriente es un RIN o su ajuste.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function excluirAplicaciones(Builder $query): void
    {
        $query->whereDoesntHave('cliente_cuentacorrientes.ventas', function (Builder $venta) {
            self::restringirVenta($venta);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $venta
     */
    private static function restringirVenta(Builder $venta): void
    {
        $venta->where(function (Builder $rin) {
            $rin->where('codigo', 'like', 'RIN %')
                ->orWhere('codigo', 'like', 'AJU Anita RIN %')
                ->orWhereHas('tipotransacciones', function (Builder $tipo) {
                    $tipo->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['RIN']);
                });
        });
    }
}
