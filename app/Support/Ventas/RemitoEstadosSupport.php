<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Remito;
use App\Models\Ventas\Remito_Articulo;

/**
 * Estados operativos de remito Bierzo (espejo conceptual de pedido).
 * Fuente de verdad de “¿tiene factura?”: venta_id (no solo el texto de estado).
 */
class RemitoEstadosSupport
{
    public const ESTADOREMITO_PENDIENTE = 'Pendiente';

    public const ESTADOREMITO_ENTREGADO = 'Entregado';

    public const ESTADOREMITO_FACTURADO = 'Facturado';

    public const ESTADOREMITO_SUSPENDIDO = 'Suspendido';

    public const ESTADOREMITO_ANULADO = 'Anulado';

    /** Línea pendiente de facturar */
    public const LINEA_PENDIENTE = 'P';

    /** Línea facturada */
    public const LINEA_FACTURADA = 'F';

    /** Línea anulada */
    public const LINEA_ANULADA = 'A';

    public static function puedeFacturarCabecera(Remito $remito): bool
    {
        if (! empty($remito->venta_id)) {
            return false;
        }

        $estado = (string) ($remito->estadoremito ?? '');

        return ! in_array($estado, [
            self::ESTADOREMITO_FACTURADO,
            self::ESTADOREMITO_SUSPENDIDO,
            self::ESTADOREMITO_ANULADO,
        ], true);
    }

    public static function motivoNoFacturable(Remito $remito): ?string
    {
        if (! empty($remito->venta_id)) {
            return 'Remito ya tiene factura asociada';
        }

        $estado = (string) ($remito->estadoremito ?? '');
        if ($estado === self::ESTADOREMITO_FACTURADO) {
            return 'Remito ya facturado';
        }
        if ($estado === self::ESTADOREMITO_SUSPENDIDO) {
            return 'Remito suspendido: no se puede facturar';
        }
        if ($estado === self::ESTADOREMITO_ANULADO) {
            return 'Remito anulado: no se puede facturar';
        }

        return null;
    }

    public static function lineaPendienteDeFacturar(Remito_Articulo $linea): bool
    {
        $estado = $linea->estado;

        return $estado === null || $estado === '' || $estado === self::LINEA_PENDIENTE;
    }
}
