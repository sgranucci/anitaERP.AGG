<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;

/**
 * Guarda el ahorro de UNA línea de requisición ya grabada (aprobada, con OC, cumplida) sin reabrir el circuito.
 * Solo toca columnas de ahorro; la auditoría (OwenIt) deja el historial. Lo copia a las líneas de OC vinculadas.
 */
class RequisicionAhorroService
{
    /**
     * @param  array<string, mixed>  $datos  precio_anterior, moneda_anterior_id, origen_anterior, referencia_anterior, tipoahorro_id, observacion
     * @return array{linea: Requisicion_Articulo, ordenes_actualizadas: int}
     */
    public function guardar(Requisicion $req, Requisicion_Articulo $linea, array $datos): array
    {
        if ((int) $linea->requisicion_id !== (int) $req->id) {
            throw new DomainException('La línea no pertenece a esta requisición.');
        }

        $this->aplicarEnLinea($req, $linea, $datos, true);

        return [
            'linea' => $linea->refresh(),
            'ordenes_actualizadas' => $this->propagarAOrdenes($linea, $datos),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function aplicarEnLinea(Requisicion $req, Requisicion_Articulo $linea, array $datos, bool $estricto): bool
    {
        if (! AhorroLineaSupport::tablaTieneColumnasAhorro('requisicion_articulo')) {
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
            $req->fecha ? (string) $req->fecha : null,
        );

        if (($columnas['preciooriginal'] ?? 0) <= 0) {
            if ($estricto) {
                throw new DomainException('Indique el precio anterior.');
            }

            return false;
        }
        if (($columnas['ahorro_total_pesos'] ?? null) === null) {
            if ($estricto) {
                throw new DomainException('No hay cotización vigente para convertir el ahorro a pesos en la fecha de la requisición.');
            }

            return false;
        }

        $linea->fill($columnas);
        $linea->save();

        return true;
    }

    /**
     * Copia el dato a las líneas de OC que salieron de esta línea (cada una con su precio y la fecha de su OC).
     *
     * @param  array<string, mixed>  $datos
     */
    private function propagarAOrdenes(Requisicion_Articulo $linea, array $datos): int
    {
        if (! AhorroLineaSupport::tablaTieneColumnasAhorro('ordencompra_articulo')) {
            return 0;
        }

        $servicioOc = app(OrdencompraAhorroService::class);
        $n = 0;
        $lineasOc = Ordencompra_Articulo::query()->where('requisicion_articulo_id', $linea->id)->get();
        foreach ($lineasOc as $lineaOc) {
            $oc = Ordencompra::query()->find($lineaOc->ordencompra_id);
            if ($oc && $servicioOc->aplicarEnLinea($oc, $lineaOc, $datos, false)) {
                $n++;
            }
        }

        return $n;
    }
}
