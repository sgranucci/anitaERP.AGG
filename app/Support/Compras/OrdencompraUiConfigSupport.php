<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Support\Facades\Schema;

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

    /** Detalle de cabecera obligatorio. El Bierzo / Surmar: false. */
    public static function detalleObligatorio(): bool
    {
        return (bool) config('compras.oc_detalle_obligatorio', true);
    }

    /**
     * Solicitante elegible por consulta de usuarios.
     * Fuera de El Bierzo queda siempre apagado, aunque exista el parámetro.
     * En El Bierzo lo prende Configuración general → Compras (default activo).
     */
    public static function solicitanteEditable(): bool
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return false;
        }

        if (! Schema::hasTable('ordencompra') || ! Schema::hasColumn('ordencompra', 'solicitante_usuario_id')) {
            return false;
        }

        return ParametroSistemaSupport::boolean(
            ParametroSistemaSupport::CLAVE_OC_SOLICITANTE_EDITABLE,
            true
        );
    }
}
