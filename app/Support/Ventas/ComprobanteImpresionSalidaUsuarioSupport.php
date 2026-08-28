<?php

namespace App\Support\Ventas;

use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Support\Facades\Auth;

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

    /**
     * @return array{programa: string, salida_id: int|null, nombre: ?string, ubicacion: string}
     */
    public static function resumenImpresora(?int $usuarioId = null, ?string $formulario = null): array
    {
        $usuarioId = $usuarioId ?? (Auth::id() ? (int) Auth::id() : null);
        $seteo = self::seteoUsuario($usuarioId, $formulario);
        $salida = $seteo?->salidas;

        return [
            'programa' => self::programaUnificado(),
            'salida_id' => $salida?->id ? (int) $salida->id : null,
            'nombre' => $salida?->nombre,
            'ubicacion' => trim((string) ($salida?->ubicacion ?? '')),
        ];
    }

    public static function tieneImpresoraAsignada(?int $usuarioId = null, ?string $formulario = null): bool
    {
        return (int) (self::resumenImpresora($usuarioId, $formulario)['salida_id'] ?? 0) > 0;
    }

    private static function seteoUsuario(?int $usuarioId, ?string $formulario = null): mixed
    {
        if (! $usuarioId) {
            return null;
        }

        $repo = app(SeteosalidaRepositoryInterface::class);
        foreach (self::programasBusqueda($formulario) as $programa) {
            $seteo = $repo->buscaSeteo($usuarioId, $programa);
            if ($seteo?->salidas) {
                return $seteo;
            }
        }

        return $repo->buscaSeteo($usuarioId, self::programaUnificado());
    }
}
