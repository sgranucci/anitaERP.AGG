<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\SeteoSalidaProgramaSupport;

/**
 * Impresora del usuario para copias de papel de la sesión (sin salida fija en el programa).
 */
final class ComprobanteImpresionSalidaUsuarioSupport
{
    public static function programaUnificado(): string
    {
        return SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES;
    }

    public static function programaPorFormulario(string $formulario): string
    {
        return match ($formulario) {
            ComprobanteImpresionFormulario::PEDIDO => SeteoSalidaProgramaSupport::VENTAS_PEDIDO,
            ComprobanteImpresionFormulario::REMITO,
            ComprobanteImpresionFormulario::COT => SeteoSalidaProgramaSupport::VENTAS_REMITO,
            default => SeteoSalidaProgramaSupport::VENTAS_FACTURA,
        };
    }

    /**
     * Orden: unificado, el del formulario, y cualquier seteo viejo de factura/remito/pedido.
     *
     * @return list<string>
     */
    public static function programasBusqueda(?string $formulario = null): array
    {
        $programas = [self::programaUnificado()];
        if ($formulario !== null && $formulario !== '') {
            $programas[] = self::programaPorFormulario($formulario);
        }
        $programas[] = SeteoSalidaProgramaSupport::VENTAS_FACTURA;
        $programas[] = SeteoSalidaProgramaSupport::VENTAS_REMITO;
        $programas[] = SeteoSalidaProgramaSupport::VENTAS_PEDIDO;

        return array_values(array_unique($programas));
    }

    public static function heredaImpresoraUsuario(array $linea): bool
    {
        if ((int) ($linea['salida_id'] ?? 0) > 0) {
            return false;
        }

        return ($linea['medio'] ?? 'IMPRESORA') !== 'ARCHIVO';
    }
}
