<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Escritura Anita de la factura de proveedor.
 * El flag global del bridge habilita o corta toda escritura.
 * Aplazar la grabación (cron 13 y 16) es solo Crown. El resto de las
 * instalaciones sigue grabando Anita al contabilizar.
 */
final class ComprobanteProveedorAnitaBridgeGate
{
    public static function escrituraHabilitada(): bool
    {
        return filter_var(config('anita.escritura_habilitada', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function grabacionEnLineaHabilitada(): bool
    {
        if (! self::escrituraHabilitada()) {
            return false;
        }

        if (! EntornoEmpresaSupport::esCrown()) {
            return true;
        }

        return filter_var(config('comprobante_proveedor_anita.grabacion_en_linea', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function syncBridgeProgramadoHabilitado(): bool
    {
        return EntornoEmpresaSupport::esCrown()
            && self::escrituraHabilitada()
            && filter_var(config('comprobante_proveedor_anita.sync_bridge.habilitada', true), FILTER_VALIDATE_BOOLEAN);
    }
}
