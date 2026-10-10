<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Compras\PagoproveedorFacturaRelacion;
use App\Support\Compras\PagoproveedorListadoColumnas;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Relaciones entre grillas. Pagos → factura aplicada, y la misma relación
 * en ingresos y egresos solo para OPP y OPA ligados a una orden de pago.
 */
final class ListadoRelacionCatalogo
{
    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(string $recurso): array
    {
        if (! in_array($recurso, [PagoproveedorListadoColumnas::RECURSO, IngresoEgresoListadoColumnas::RECURSO], true)) {
            return [];
        }

        return PagoproveedorFacturaRelacion::campos();
    }

    public static function aplica(string $recurso, string $campoKey): bool
    {
        return isset(self::campos($recurso)[$campoKey]);
    }

    public static function aplicar(
        Builder $query,
        string $recurso,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        string $origenLiteral
    ): bool {
        if (! self::aplica($recurso, $campoKey)) {
            return false;
        }
        if ($recurso === IngresoEgresoListadoColumnas::RECURSO) {
            PagoproveedorFacturaRelacion::aplicarEnCajaMovimiento($query, $campoKey, $operador, $valor, $valorHasta);

            return true;
        }
        if ($recurso !== PagoproveedorListadoColumnas::RECURSO) {
            return false;
        }
        PagoproveedorFacturaRelacion::aplicar($query, $campoKey, $operador, $valor, $valorHasta, $origenLiteral);

        return true;
    }
}
