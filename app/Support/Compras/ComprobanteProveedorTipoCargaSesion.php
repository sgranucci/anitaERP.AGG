<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Session;

/**
 * En Crown, el tipo elegido al entrar a una factura nueva (sin OC, con OC,
 * con IA, precarga) se reutiliza para la siguiente hasta volver al listado.
 */
final class ComprobanteProveedorTipoCargaSesion
{
    public const SIN_OC = 'sin_oc';

    public const OC = 'oc';

    public const IA = 'ia';

    public const PRECARGA = 'precarga';

    private const CLAVE = 'comprobante_proveedor_tipo_carga';

    /** @return list<string> */
    public static function tipos(): array
    {
        return [self::SIN_OC, self::OC, self::IA, self::PRECARGA];
    }

    public static function esValido(?string $tipo): bool
    {
        return in_array($tipo, self::tipos(), true);
    }

    public static function recordar(?string $tipo): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! self::esValido($tipo)) {
            return;
        }

        Session::put(self::CLAVE, $tipo);
    }

    public static function olvidar(): void
    {
        Session::forget(self::CLAVE);
    }

    public static function actual(): ?string
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return null;
        }

        $tipo = Session::get(self::CLAVE);

        return self::esValido(is_string($tipo) ? $tipo : null) ? $tipo : null;
    }

    public static function desdeOrigenEntrada(?string $origen): ?string
    {
        return match ($origen) {
            ComprobanteProveedorOrigenEntrada::MANUAL => self::SIN_OC,
            ComprobanteProveedorOrigenEntrada::ORDENCOMPRA => self::OC,
            ComprobanteProveedorOrigenEntrada::PDF_IA => self::IA,
            ComprobanteProveedorOrigenEntrada::PRECARGA => self::PRECARGA,
            default => null,
        };
    }

    public static function urlContinuar(string $tipo): string
    {
        return match ($tipo) {
            self::SIN_OC => route('crear_comprobante_proveedor'),
            self::OC => route('comprobante_proveedor_opciones_carga', ['tipo' => self::OC]),
            self::IA => route('comprobante_proveedor_opciones_carga', ['tipo' => self::IA]),
            self::PRECARGA => route('precarga_comprobante_proveedor'),
            default => route('comprobante_proveedor_opciones_carga'),
        };
    }
}
