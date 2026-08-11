<?php

namespace App\Support\Compras;

/**
 * Flags de UI/validación de orden de compra por entorno (.env).
 *
 * AGG: pedir partida/CAPEX = true, mostrar peso = false, entrega semanal = false.
 * El Bierzo: pedir partida/CAPEX = false, mostrar peso = true, entrega semanal = true.
 */
final class OrdencompraUiConfigSupport
{
    public static function pedirPartidaCapex(): bool
    {
        return (bool) config('compras.oc_pedir_partida_capex', true);
    }

    public static function mostrarPesoArticulo(): bool
    {
        return (bool) config('compras.oc_mostrar_peso_articulo', false);
    }

    /**
     * Modal de entregas semanales (fecha/cantidad) por línea de OC.
     * La suma de cantidades del modal alimenta la cantidad de la grilla.
     */
    public static function entregaSemanal(): bool
    {
        return (bool) config('compras.oc_entrega_semanal', false);
    }
}
