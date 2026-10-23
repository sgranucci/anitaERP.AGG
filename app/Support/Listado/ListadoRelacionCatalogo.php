<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Compras\ComprobanteProveedorPagoRelacion;
use App\Support\Compras\PagoproveedorFacturaRelacion;
use App\Support\Compras\PagoproveedorListadoColumnas;
use App\Support\Stock\ArticuloListadoColumnas;
use App\Support\Stock\ArticuloListadoCuentasSupport;
use App\Support\Stock\ArticuloListadoFiltros;
use App\Support\Stock\ArticuloProveedorRelacion;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Relaciones entre grillas. Pagos → factura aplicada (y la misma en ingresos
 * y egresos solo para OPP y OPA ligados a una orden). Factura → orden de pago.
 */
final class ListadoRelacionCatalogo
{
    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(string $recurso): array
    {
        if (in_array($recurso, [PagoproveedorListadoColumnas::RECURSO, IngresoEgresoListadoColumnas::RECURSO], true)) {
            return PagoproveedorFacturaRelacion::campos();
        }
        if ($recurso === ComprobanteProveedorListadoColumnas::RECURSO) {
            return ComprobanteProveedorPagoRelacion::campos();
        }
        if ($recurso === ArticuloListadoColumnas::RECURSO) {
            return array_merge(
                ArticuloListadoCuentasSupport::camposRelacion(),
                ArticuloProveedorRelacion::campos()
            );
        }

        return [];
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
        if ($recurso === ComprobanteProveedorListadoColumnas::RECURSO) {
            ComprobanteProveedorPagoRelacion::aplicarFiltro($query, $campoKey, $operador, $valor, $valorHasta);

            return true;
        }
        if ($recurso === ArticuloListadoColumnas::RECURSO) {
            if (isset(ArticuloListadoCuentasSupport::TIPOS[$campoKey])) {
                ArticuloListadoFiltros::aplicarCampoCuenta($query, $campoKey, $operador, $valor);

                return true;
            }
            ArticuloProveedorRelacion::aplicarFiltro($query, $campoKey, $operador, $valor, $valorHasta);

            return true;
        }
        if ($recurso !== PagoproveedorListadoColumnas::RECURSO) {
            return false;
        }
        PagoproveedorFacturaRelacion::aplicar($query, $campoKey, $operador, $valor, $valorHasta, $origenLiteral);

        return true;
    }
}
