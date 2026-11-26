<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;

/**
 * Guarda el ahorro de UNA línea de OC sin pasar por el circuito de aprobación.
 * Solo toca columnas de ahorro; la auditoría (OwenIt) deja el historial de cambios de la línea.
 *
 * Vale para cualquier estado de OC (incluida cerrada): el ahorro es un dato de gestión y se necesita
 * para cargar el histórico. Además lo copia a la línea de requisición vinculada.
 */
class OrdencompraAhorroService
{
    /**
     * @param  array<string, mixed>  $datos  precio_anterior, moneda_anterior_id, origen_anterior, referencia_anterior, tipoahorro_id, observacion
     * @return array{linea: Ordencompra_Articulo, requisicion_actualizada: bool}
     */
    public function guardar(Ordencompra $oc, Ordencompra_Articulo $linea, array $datos): array
    {
        if ((int) $linea->ordencompra_id !== (int) $oc->id) {
            throw new DomainException('La línea no pertenece a esta orden de compra.');
        }

        $this->aplicarEnLinea($oc, $linea, $datos, true);

        return [
            'linea' => $linea->refresh(),
            'requisicion_actualizada' => $this->propagarARequisicion($linea, $datos),
        ];
    }

    /**
     * Calcula con el precio / cantidad / moneda de la línea y la fecha de la OC, y graba.
     * Devuelve false (sin cortar) cuando no se puede calcular y $estricto es false.
     *
     * @param  array<string, mixed>  $datos
     */
    public function aplicarEnLinea(Ordencompra $oc, Ordencompra_Articulo $linea, array $datos, bool $estricto): bool
    {
        if (! AhorroLineaSupport::tablaTieneColumnasAhorro('ordencompra_articulo')) {
            throw new DomainException('Falta la migración de ahorro en este entorno.');
        }
        if ((float) $linea->precio <= 0) {
            if ($estricto) {
                throw new DomainException('La línea no tiene precio: no se puede calcular el ahorro.');
            }

            return false;
        }

        $columnas = AhorroLineaSupport::columnasParaGrabar(
            $datos,
            (float) $linea->precio,
            (int) $linea->moneda_id,
            (float) $linea->cantidad,
            $oc->fecha ? (string) $oc->fecha : null,
        );

        if (($columnas['preciooriginal'] ?? 0) <= 0) {
            if ($estricto) {
                throw new DomainException('Indique el precio anterior.');
            }

            return false;
        }
        if (($columnas['ahorro_total_pesos'] ?? null) === null) {
            if ($estricto) {
                throw new DomainException('No hay cotización vigente para convertir el ahorro a pesos en la fecha de la orden.');
            }

            return false;
        }

        $linea->fill($columnas);
        $linea->save();

        return true;
    }

    /**
     * Copia el dato del ahorro a la línea de requisición de origen (recalculado con el precio de la requisición).
     *
     * @param  array<string, mixed>  $datos
     */
    private function propagarARequisicion(Ordencompra_Articulo $linea, array $datos): bool
    {
        $raId = (int) ($linea->requisicion_articulo_id ?? 0);
        if ($raId <= 0 || ! AhorroLineaSupport::tablaTieneColumnasAhorro('requisicion_articulo')) {
            return false;
        }
        $ra = Requisicion_Articulo::query()->find($raId);
        $req = $ra ? Requisicion::query()->find($ra->requisicion_id) : null;
        if (! $ra || ! $req) {
            return false;
        }

        return app(RequisicionAhorroService::class)->aplicarEnLinea($req, $ra, $datos, false);
    }
}
